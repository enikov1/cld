import { adminAuthHeaders } from '../auth/tokenStorage'
import { notifyUnauthorized } from '../auth/unauthorized'

const knownValidationMessages: Record<string, string> = {
  'The email field must be a valid email address.': 'Укажите корректный адрес email.',
  'The email field is required.': 'Укажите email.',
  'The password field is required.': 'Укажите пароль.',
  'The name field is required.': 'Укажите имя.',
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

export async function api<T>(url: string, opts?: RequestInit): Promise<T> {
  const { headers: optHeaders, ...restOpts } = opts ?? {}
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...restOpts,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...adminAuthHeaders(),
      ...(optHeaders ?? {}),
    },
  })

  if (res.status === 401) {
    notifyUnauthorized()
  }

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = await res.json()
      message = formatApiErrorMessage(data, message)
    } catch {
      // Body already consumed by res.json() — do not call res.text().
    }
    throwApiError(res, message)
  }

  if (res.status === 204) {
    return undefined as T
  }

  return (await res.json()) as T
}

export async function apiUpload<T>(url: string, formData: FormData): Promise<T> {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      ...adminAuthHeaders(),
    },
    body: formData,
  })

  if (res.status === 401) {
    notifyUnauthorized()
  }

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = await res.json()
      message = formatApiErrorMessage(data, message)
    } catch {
      // Body already consumed.
    }
    throwApiError(res, message)
  }

  return (await res.json()) as T
}
