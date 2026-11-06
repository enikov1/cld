import { useCallback, useEffect, useState } from 'react'

const STORAGE_KEY = 'omro_admin_theme'
const LEGACY_STORAGE_KEY = 'lordserial_admin_theme'

export function useAdminTheme() {
  const [isDark, setIsDark] = useState(
    () => localStorage.getItem(STORAGE_KEY) === 'dark'
      || (localStorage.getItem(STORAGE_KEY) === null && localStorage.getItem(LEGACY_STORAGE_KEY) === 'dark'),
  )

  useEffect(() => {
    document.documentElement.dataset.adminTheme = isDark ? 'dark' : 'light'
    localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light')
    try {
      localStorage.removeItem(LEGACY_STORAGE_KEY)
    } catch {
      // ignore
    }
  }, [isDark])

  const toggle = useCallback(() => {
    setIsDark((value) => !value)
  }, [])

  return { isDark, toggle, setIsDark }
}
