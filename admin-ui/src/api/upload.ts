import { adminAuthHeaders } from '../auth/tokenStorage'
import { notifyUnauthorized } from '../auth/unauthorized'

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
      message = data.error || data.message || message
    } catch {
      /* ignore */
    }
    throw new Error(message)
  }

  return (await res.json()) as T
}
