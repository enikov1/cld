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

function withCacheBust(url: string, cacheBust?: string | number | null): string {
  if (cacheBust == null || cacheBust === '') return url
  const sep = url.includes('?') ? '&' : '?'
  return `${url}${sep}v=${encodeURIComponent(String(cacheBust))}`
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
  return withCacheBust(resolved, cacheBust)
}

/**
 * URL for cropper/canvas: keep /storage on the same origin (Vite proxy in DEV)
 * so the image loads and can be exported without CORS issues.
 */
export function resolveCropperImageUrl(
  url: string | null | undefined,
  cacheBust?: string | number | null,
): string {
  if (!url) return ''
  const trimmed = url.trim()
  if (!trimmed) return ''
  if (trimmed.startsWith('blob:') || trimmed.startsWith('data:')) {
    return trimmed
  }

  if (/^https?:\/\//i.test(trimmed)) {
    try {
      const parsed = new URL(trimmed)
      const path = parsed.pathname + parsed.search
      if (path.startsWith('/storage/') || path.startsWith('/theme-assets/')) {
        return withCacheBust(path, cacheBust)
      }
    } catch {
      // keep as-is
    }
    return withCacheBust(trimmed, cacheBust)
  }

  if (trimmed.startsWith('/')) {
    return withCacheBust(trimmed, cacheBust)
  }

  return withCacheBust(trimmed, cacheBust)
}
