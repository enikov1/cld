import { Button, Result, Spin } from 'antd'
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { setApiAuthEpoch } from '../api/client'
import { clearAdminToken, getAdminToken, purgeLegacyTokenStorage, setAdminToken } from './tokenStorage'
import { setUnauthorizedHandler } from './unauthorized'

export type AdminMe = {
  actor_type: 'master' | 'token' | string
  name: string
  role: 'full' | 'content' | 'moderation' | 'custom' | string
  token_id?: number | null
  abilities?: string[]
  pages: string[]
}

async function syncSiteAccess(method: 'POST' | 'DELETE', token?: string): Promise<void> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  if (token) {
    headers['X-ADMIN-TOKEN'] = token
  }

  const res = await fetch(`/api/admin/site-access`, {
    method,
    headers,
    credentials: 'same-origin',
  })

  if (!res.ok) {
    let message = `Ошибка ${res.status}`
    try {
      const data = (await res.json()) as { error?: string; message?: string }
      message = data.error || data.message || message
    } catch {
      /* ignore */
    }
    throw new Error(message)
  }
}

export type AuthStatus = 'loading' | 'authenticated' | 'login'

type AuthContextValue = {
  status: AuthStatus
  tokenRequired: boolean
  me: AdminMe | null
  meError: string | null
  login: (token: string) => Promise<void>
  logout: () => Promise<void>
  refreshMe: () => Promise<AdminMe | null>
  authEpoch: number
}

const AuthContext = createContext<AuthContextValue | null>(null)

async function verifyToken(token?: string): Promise<{ ok: boolean; status: number; error?: string }> {
  try {
    const res = await fetch('/api/admin/me', {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-ADMIN-TOKEN': token } : {}),
      },
      credentials: 'same-origin',
    })
    if (res.ok) {
      return { ok: true, status: res.status }
    }

    let error: string | undefined
    try {
      const data = (await res.json()) as { error?: string; message?: string }
      error = data.error || data.message
    } catch {
      /* ignore body parse */
    }

    return { ok: false, status: res.status, error }
  } catch {
    return { ok: false, status: 0, error: 'Нет связи с сервером' }
  }
}

function authErrorMessage(result: { status: number; error?: string }): string {
  if (result.status === 0) {
    return result.error || 'Нет связи с сервером'
  }
  if (result.status === 503) {
    return result.error || 'ADMIN_TOKEN не задан на сервере'
  }
  if (result.status === 401) {
    return 'Неверный токен. Для полного доступа — ADMIN_TOKEN из .env. Секрет из «Токены доступа» показывается только при создании или перевыпуске.'
  }
  if (result.status === 403) {
    return result.error || 'Недостаточно прав'
  }
  return result.error || `Ошибка входа (${result.status})`
}

async function fetchMe(): Promise<AdminMe> {
  const res = await fetch('/api/admin/me', {
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  })
  if (res.status === 401) {
    throw Object.assign(new Error('Unauthorized'), { status: 401 })
  }
  if (!res.ok) {
    throw new Error(`Не удалось загрузить профиль (${res.status})`)
  }
  return (await res.json()) as AdminMe
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [tokenRequired, setTokenRequired] = useState(true)
  const [me, setMe] = useState<AdminMe | null>(null)
  const [meError, setMeError] = useState<string | null>(null)
  const [authEpoch, setAuthEpoch] = useState(0)
  const authEpochRef = useRef(0)

  const bumpEpoch = useCallback(() => {
    authEpochRef.current += 1
    setApiAuthEpoch(authEpochRef.current)
    setAuthEpoch(authEpochRef.current)
    return authEpochRef.current
  }, [])

  const refreshMe = useCallback(async (): Promise<AdminMe | null> => {
    try {
      const profile = await fetchMe()
      setMe(profile)
      setMeError(null)
      return profile
    } catch (e) {
      const statusCode = (e as { status?: number }).status
      if (statusCode === 401) {
        setMe(null)
        setMeError(null)
        throw e
      }
      setMe(null)
      setMeError(String((e as Error).message || 'Не удалось загрузить профиль'))
      return null
    }
  }, [])

  const resetToLogin = useCallback(() => {
    clearAdminToken()
    setTokenRequired(true)
    setMe(null)
    setMeError(null)
    setStatus('login')
    bumpEpoch()
  }, [bumpEpoch])

  const bootstrap = useCallback(async () => {
    purgeLegacyTokenStorage()

    if ((await verifyToken()).ok) {
      setTokenRequired(true)
      try {
        const profile = await refreshMe()
        if (!profile) {
          setStatus('authenticated')
          return
        }
        setStatus('authenticated')
        bumpEpoch()
      } catch (e) {
        if ((e as { status?: number }).status === 401) {
          resetToLogin()
          return
        }
        setStatus('authenticated')
      }
      return
    }

    const token = getAdminToken()
    if (token && (await verifyToken(token)).ok) {
      setTokenRequired(true)
      try {
        await syncSiteAccess('POST', token)
        clearAdminToken()
        const profile = await refreshMe()
        setStatus('authenticated')
        if (profile) bumpEpoch()
      } catch (e) {
        clearAdminToken()
        if ((e as { status?: number }).status === 401) {
          resetToLogin()
          return
        }
        setMeError(String((e as Error).message || 'Не удалось сохранить сессию'))
        setStatus('login')
      }
      return
    }

    if (token) {
      clearAdminToken()
    }

    setTokenRequired(true)
    setMe(null)
    setMeError(null)
    setStatus('login')
  }, [bumpEpoch, refreshMe, resetToLogin])

  useEffect(() => {
    let cancelled = false
    bootstrap().catch(() => {
      if (!cancelled) {
        setTokenRequired(true)
        setMe(null)
        setMeError(null)
        setStatus('login')
      }
    })
    return () => {
      cancelled = true
    }
  }, [bootstrap])

  useEffect(() => {
    setUnauthorizedHandler((requestEpoch) => {
      if (requestEpoch !== undefined && requestEpoch !== authEpochRef.current) {
        return
      }
      void syncSiteAccess('DELETE').catch(() => {})
      resetToLogin()
    })
    return () => setUnauthorizedHandler(null)
  }, [resetToLogin])

  const login = useCallback(
    async (token: string) => {
      const trimmed = token.trim().replace(/\s+/g, '')
      if (!trimmed) {
        throw new Error('Введите токен')
      }

      const check = await verifyToken(trimmed)
      if (!check.ok) {
        clearAdminToken()
        throw new Error(authErrorMessage(check))
      }

      setAdminToken(trimmed)
      try {
        await syncSiteAccess('POST', trimmed)
      } catch (e) {
        clearAdminToken()
        throw e
      }
      clearAdminToken()

      const sessionCheck = await verifyToken()
      if (!sessionCheck.ok) {
        throw new Error('Не удалось сохранить сессию админки. Проверьте cookies для этого сайта.')
      }

      const profile = await refreshMe().catch((e) => {
        if ((e as { status?: number }).status === 401) {
          throw new Error('Сессия недействительна после входа')
        }
        return null
      })

      setTokenRequired(true)
      bumpEpoch()
      setStatus('authenticated')
      if (!profile) {
        setMeError('Не удалось загрузить профиль. Повторите попытку.')
      }
    },
    [bumpEpoch, refreshMe],
  )

  const logout = useCallback(async () => {
    try {
      await syncSiteAccess('DELETE', getAdminToken() || undefined)
    } catch (e) {
      // Still clear UI, but surface that cookie may linger.
      console.warn('logout site-access failed', e)
    }
    clearAdminToken()
    setTokenRequired(true)
    setMe(null)
    setMeError(null)
    bumpEpoch()
    setStatus('login')
  }, [bumpEpoch])

  const value = useMemo(
    () => ({ status, tokenRequired, me, meError, login, logout, refreshMe, authEpoch }),
    [status, tokenRequired, me, meError, login, logout, refreshMe, authEpoch],
  )

  if (status === 'loading') {
    return (
      <div className="admin-auth-loading">
        <Spin size="large" />
      </div>
    )
  }

  if (status === 'authenticated' && !me) {
    return (
      <div className="admin-auth-loading">
        <Result
          status="warning"
          title="Не удалось загрузить права доступа"
          subTitle={meError || 'Профиль администратора недоступен. Обновите страницу или войдите снова.'}
          extra={
            <Button
              type="primary"
              onClick={() => {
                setMeError(null)
                void refreshMe()
                  .then((profile) => {
                    if (!profile) return
                  })
                  .catch(() => {
                    void logout()
                  })
              }}
            >
              Повторить
            </Button>
          }
        />
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
