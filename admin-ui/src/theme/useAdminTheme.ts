import { useCallback, useEffect, useState } from 'react'

const STORAGE_KEY = 'lordserial_admin_theme'

export function useAdminTheme() {
  const [isDark, setIsDark] = useState(() => localStorage.getItem(STORAGE_KEY) === 'dark')

  useEffect(() => {
    document.documentElement.dataset.adminTheme = isDark ? 'dark' : 'light'
    localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light')
  }, [isDark])

  const toggle = useCallback(() => {
    setIsDark((value) => !value)
  }, [])

  return { isDark, toggle, setIsDark }
}
