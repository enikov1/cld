const DEFAULT_BACKEND = 'http://127.0.0.1:8085'

function backendOrigin(): string {
  const raw = import.meta.env.VITE_BACKEND_URL as string | undefined
  if (raw) {
    return raw.replace(/\/+$/, '')
  }
  if (import.meta.env.DEV) {
    return DEFAULT_BACKEND
  }
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  return ''
}

/** Превращает /storage/... в полный URL при dev-сервере Vite (админка на другом порту). */
export function resolveMediaUrl(url: string | null | undefined): string {
  if (!url) return ''
  const trimmed = url.trim()
  if (!trimmed) return ''
  if (/^https?:\/\//i.test(trimmed)) return trimmed
  if (trimmed.startsWith('/') && import.meta.env.DEV) {
    const origin = backendOrigin()
    if (origin) return `${origin}${trimmed}`
  }
  return trimmed
}
