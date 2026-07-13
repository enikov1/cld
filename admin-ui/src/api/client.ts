import { adminAuthHeaders } from '../auth/tokenStorage'
import { notifyUnauthorized } from '../auth/unauthorized'

export async function api<T>(url: string, opts?: RequestInit): Promise<T> {
  const res = await fetch(url, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...adminAuthHeaders(),
      ...(opts?.headers ?? {}),
    },
    ...opts,
  })

  if (res.status === 401) {
    notifyUnauthorized()
  }

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = await res.json()
      if (data.errors) {
        const parts = Object.values(data.errors).flat().filter(Boolean)
        if (parts.length) message = parts.join(' ')
      } else {
        message = data.error || data.message || message
      }
    } catch {
      const text = await res.text().catch(() => '')
      if (text) message = text
    }
    throw new Error(message)
  }

  if (res.status === 204) {
    return undefined as T
  }

  return (await res.json()) as T
}

export async function apiUpload<T>(url: string, formData: FormData): Promise<T> {
  const res = await fetch(url, {
    method: 'POST',
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
      if (data.errors) {
        const parts = Object.values(data.errors).flat().filter(Boolean)
        if (parts.length) message = parts.join(' ')
      } else {
        message = data.error || data.message || message
      }
    } catch {
      const text = await res.text().catch(() => '')
      if (text) message = text
    }
    throw new Error(message)
  }

  return (await res.json()) as T
}
