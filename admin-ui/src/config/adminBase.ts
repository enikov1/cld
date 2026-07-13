declare global {
  interface Window {
    __ADMIN_BASE__?: string
  }
}

export function getInjectedAdminBase(): string | null {
  const base = window.__ADMIN_BASE__
  if (typeof base === 'string' && base.startsWith('/')) {
    return base.replace(/\/+$/, '') || null
  }
  return null
}

export async function resolveAdminBase(): Promise<string> {
  const injected = getInjectedAdminBase()
  if (injected) {
    return injected
  }

  const res = await fetch('/api/site/admin-path')
  if (!res.ok) {
    throw new Error('Не удалось получить URL админки')
  }

  const data = (await res.json()) as { base?: string; path?: string }
  if (data.base?.startsWith('/')) {
    return data.base.replace(/\/+$/, '')
  }
  if (data.path) {
    return `/${data.path.replace(/^\/+|\/+$/g, '')}`
  }

  return '/admin'
}
