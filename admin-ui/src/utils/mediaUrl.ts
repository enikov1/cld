const DEFAULT_BACKEND = 'http://127.0.0.1:8085'

export function siteOrigin(): string {
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

function backendOrigin(): string {
  return siteOrigin()
}

/** Превращает /storage/... в полный URL при dev-сервере Vite (админка на другом порту). */
export function resolveMediaUrl(
  url: string | null | undefined,
  cacheBust?: string | number | null,
): string {
  if (!url) return ''
  const trimmed = url.trim()
  if (!trimmed) return ''
  let resolved = trimmed
  if (/^https?:\/\//i.test(trimmed)) {
    resolved = trimmed
  } else if (trimmed.startsWith('/') && import.meta.env.DEV) {
    const origin = backendOrigin()
    if (origin) resolved = `${origin}${trimmed}`
  }
  if (cacheBust != null && cacheBust !== '') {
    const sep = resolved.includes('?') ? '&' : '?'
    resolved = `${resolved}${sep}v=${encodeURIComponent(String(cacheBust))}`
  }
  return resolved
}
