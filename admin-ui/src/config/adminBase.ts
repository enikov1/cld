declare global {
  interface Window {
    __ADMIN_BASE__?: string
  }
}

function normalizeBase(base: string): string {
  return base.replace(/\/+$/, '') || '/'
}

export function getInjectedAdminBase(): string | null {
  const base = window.__ADMIN_BASE__
  if (typeof base === 'string' && base.startsWith('/')) {
    return normalizeBase(base) === '/' ? null : normalizeBase(base)
  }
  return null
}

function getViteDevBase(): string | null {
  const raw = import.meta.env.BASE_URL
  if (typeof raw !== 'string') {
    return null
  }
  const base = raw.replace(/\/+$/, '')
  if (base.startsWith('/') && base !== '/' && base !== '.') {
    return base
  }
  return null
}

/** Last-resort guess from the URL when opening a deep admin link. */
function inferBaseFromLocation(): string | null {
  const segments = window.location.pathname.split('/').filter(Boolean)
  if (segments.length === 0) {
    return null
  }
  return `/${segments[0]}`
}

export async function resolveAdminBase(): Promise<string> {
  const injected = getInjectedAdminBase()
  if (injected) {
    return injected
  }

  const viteBase = getViteDevBase()
  if (viteBase) {
    return viteBase
  }

  const controller = new AbortController()
  const timer = window.setTimeout(() => controller.abort(), 2500)

  try {
    const res = await fetch('/api/site/admin-path', { signal: controller.signal })
    if (res.ok) {
      const data = (await res.json()) as { base?: string; path?: string }
      if (data.base?.startsWith('/')) {
        return normalizeBase(data.base)
      }
      if (data.path) {
        return `/${data.path.replace(/^\/+|\/+$/g, '')}`
      }
    }
  } catch {
    // Cold PHP / offline API — fall through to path inference.
  } finally {
    window.clearTimeout(timer)
  }

  return inferBaseFromLocation() ?? '/admin'
}
