import { Spin } from 'antd'
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { clearAdminToken, getAdminToken, setAdminToken } from './tokenStorage'
import { setUnauthorizedHandler } from './unauthorized'

async function syncSiteAccess(method: 'POST' | 'DELETE', token?: string): Promise<void> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (token) {
    headers['X-ADMIN-TOKEN'] = token
  }

  await fetch(`/api/admin/site-access`, {
    method,
    headers,
    credentials: 'same-origin',
  })
}

export type AuthStatus = 'loading' | 'authenticated' | 'login'

type AuthContextValue = {
  status: AuthStatus
  tokenRequired: boolean
  login: (token: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

async function verifyToken(token?: string): Promise<boolean> {
  const res = await fetch('/api/admin/stats', {
    headers: {
      Accept: 'application/json',
      ...(token ? { 'X-ADMIN-TOKEN': token } : {}),
    },
  })
  return res.ok
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [tokenRequired, setTokenRequired] = useState(false)

  const bootstrap = useCallback(async () => {
    const token = getAdminToken()
    if (token && (await verifyToken(token))) {
      setTokenRequired(true)
      setStatus('authenticated')
      await syncSiteAccess('POST', token).catch(() => {})
      return
    }

    if (token) {
      clearAdminToken()
    }

    if (await verifyToken('')) {
      setTokenRequired(false)
      setStatus('authenticated')
      await syncSiteAccess('POST').catch(() => {})
      return
    }

    setTokenRequired(true)
    setStatus('login')
  }, [])

  useEffect(() => {
    bootstrap().catch(() => {
      setTokenRequired(true)
      setStatus('login')
    })
  }, [bootstrap])

  useEffect(() => {
    setUnauthorizedHandler(() => {
      clearAdminToken()
      setTokenRequired(true)
      setStatus('login')
    })
    return () => setUnauthorizedHandler(null)
  }, [])

  const login = useCallback(async (token: string) => {
    const trimmed = token.trim()
    if (!trimmed) {
      throw new Error('Введите токен')
    }

    setAdminToken(trimmed)
    const ok = await verifyToken(trimmed)
    if (!ok) {
      clearAdminToken()
      throw new Error('Неверный токен')
    }

    setTokenRequired(true)
    setStatus('authenticated')
    await syncSiteAccess('POST', trimmed).catch(() => {})
  }, [])

  const logout = useCallback(async () => {
    await syncSiteAccess('DELETE', getAdminToken() || undefined).catch(() => {})
    clearAdminToken()
    if (await verifyToken('')) {
      setTokenRequired(false)
      setStatus('authenticated')
      return
    }
    setTokenRequired(true)
    setStatus('login')
  }, [])

  const value = useMemo(
    () => ({ status, tokenRequired, login, logout }),
    [status, tokenRequired, login, logout],
  )

  if (status === 'loading') {
    return (
      <div className="admin-auth-loading">
        <Spin size="large" />
      </div>
    )
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return ctx
}
