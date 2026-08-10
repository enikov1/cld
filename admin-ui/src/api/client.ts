import { adminAuthHeaders } from '../auth/tokenStorage'
import { notifyUnauthorized } from '../auth/unauthorized'
import { compressFormDataImages } from '../utils/compressImage'

const knownValidationMessages: Record<string, string> = {
  'The email field must be a valid email address.': 'Укажите корректный адрес email.',
  'The email field is required.': 'Укажите email.',
  'The password field is required.': 'Укажите пароль.',
  'The name field is required.': 'Укажите имя.',
}

/** Bumped by AuthProvider; stamped on each request to ignore stale 401s. */
let currentAuthEpoch = 0

export function setApiAuthEpoch(epoch: number): void {
  currentAuthEpoch = epoch
}

export function getApiAuthEpoch(): number {
  return currentAuthEpoch
}

export class ApiError extends Error {
  readonly status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

export function isApiNotFound(error: unknown): boolean {
  return error instanceof ApiError && error.status === 404
}

function humanizeValidationMessage(message: string): string {
  return knownValidationMessages[message] ?? message
}

function formatApiErrorMessage(data: Record<string, unknown>, fallback: string): string {
  if (data.errors && typeof data.errors === 'object') {
    const parts = Object.values(data.errors as Record<string, unknown>)
      .flatMap((value) => (Array.isArray(value) ? value : [value]))
      .filter(Boolean)
      .map((part) => humanizeValidationMessage(String(part)))
    if (parts.length) return parts.join(' ')
  }

  const message = data.error || data.message
  if (typeof message === 'string' && message) {
    return humanizeValidationMessage(message)
  }

  return fallback
}

function throwApiError(res: Response, message: string): never {
  throw new ApiError(message, res.status)
}

async function parseJsonBody<T>(res: Response): Promise<T> {
  const text = await res.text()
  if (!text) {
    return undefined as T
  }
  try {
    return JSON.parse(text) as T
  } catch {
    throw new ApiError('Сервер вернул некорректный JSON', res.status)
  }
}

function defaultHeaders(hasJsonBody: boolean): Record<string, string> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...adminAuthHeaders(),
  }
  if (hasJsonBody) {
    headers['Content-Type'] = 'application/json'
  }
  return headers
}

export async function api<T>(url: string, opts?: RequestInit): Promise<T> {
  const requestEpoch = currentAuthEpoch
  const { headers: optHeaders, body, ...restOpts } = opts ?? {}
  const hasJsonBody = typeof body === 'string'
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...restOpts,
    body,
    headers: {
      ...defaultHeaders(hasJsonBody),
      ...(optHeaders ?? {}),
    },
  })

  if (res.status === 401) {
    notifyUnauthorized(requestEpoch)
  }

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = (await parseJsonBody<Record<string, unknown>>(res)) ?? {}
      message = formatApiErrorMessage(data, message)
    } catch (e) {
      if (e instanceof ApiError) throw e
    }
    throwApiError(res, message)
  }

  if (res.status === 204) {
    return undefined as T
  }

  return parseJsonBody<T>(res)
}

export async function apiUpload<T>(url: string, formData: FormData): Promise<T> {
  const requestEpoch = currentAuthEpoch
  const body = await compressFormDataImages(formData)
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...adminAuthHeaders(),
    },
    body,
  })

  if (res.status === 401) {
    notifyUnauthorized(requestEpoch)
  }

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = (await parseJsonBody<Record<string, unknown>>(res)) ?? {}
      message = formatApiErrorMessage(data, message)
    } catch (e) {
      if (e instanceof ApiError) throw e
    }
    throwApiError(res, message)
  }

  return parseJsonBody<T>(res)
}
