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

const APP_SUFFIX = 'LordSerial Admin'

const BUSY_FAVICON_HREF =
  'data:image/svg+xml,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
      <circle cx="16" cy="16" r="12" fill="none" stroke="#1677ff" stroke-width="3" stroke-linecap="round" stroke-dasharray="56 24">
        <animateTransform attributeName="transform" type="rotate" from="0 16 16" to="360 16 16" dur="0.75s" repeatCount="indefinite"/>
      </circle>
    </svg>`.replace(/\s+/g, ' ').trim(),
  )

type OverlayEntry = { id: number; title: string }

type AdminDocumentMetaContextValue = {
  setBaseTitle: (title: string) => void
  setOverlayTitle: (id: number, title: string | null) => void
  acquireBusy: () => () => void
}

const AdminDocumentMetaContext = createContext<AdminDocumentMetaContextValue | null>(null)

let nextOverlayId = 1

function ensureFaviconLink(): HTMLLinkElement {
  let link = document.querySelector<HTMLLinkElement>("link[rel='icon']")
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  return link
}

function applyDocumentTitle(title: string) {
  document.title = title.trim() ? `${title.trim()} — ${APP_SUFFIX}` : APP_SUFFIX
}

export function AdminDocumentMetaProvider({ children }: { children: ReactNode }) {
  const [baseTitle, setBaseTitleState] = useState('')
  const [overlays, setOverlays] = useState<OverlayEntry[]>([])
  const [busyCount, setBusyCount] = useState(0)
  const originalFaviconRef = useRef<string | null>(null)

  const setBaseTitle = useCallback((title: string) => {
    setBaseTitleState(title)
  }, [])

  const setOverlayTitle = useCallback((id: number, title: string | null) => {
    setOverlays((prev) => {
      const without = prev.filter((entry) => entry.id !== id)
      const nextTitle = title?.trim() || null
      if (!nextTitle) return without
      return [...without, { id, title: nextTitle }]
    })
  }, [])

  const acquireBusy = useCallback(() => {
    setBusyCount((count) => count + 1)
    let released = false
    return () => {
      if (released) return
      released = true
      setBusyCount((count) => Math.max(0, count - 1))
    }
  }, [])

  const activeTitle = overlays.length > 0 ? overlays[overlays.length - 1].title : baseTitle

  useEffect(() => {
    applyDocumentTitle(activeTitle)
  }, [activeTitle])

  useEffect(() => {
    const link = ensureFaviconLink()
    if (busyCount > 0) {
      if (originalFaviconRef.current === null) {
        originalFaviconRef.current = link.getAttribute('href')
      }
      if (link.getAttribute('href') !== BUSY_FAVICON_HREF) {
        link.setAttribute('href', BUSY_FAVICON_HREF)
        link.setAttribute('type', 'image/svg+xml')
      }
      return
    }

    if (originalFaviconRef.current !== null) {
      const restore = originalFaviconRef.current
      originalFaviconRef.current = null
      if (restore) {
        link.setAttribute('href', restore)
      } else {
        link.removeAttribute('href')
      }
    }
  }, [busyCount])

  const value = useMemo(
    () => ({ setBaseTitle, setOverlayTitle, acquireBusy }),
    [setBaseTitle, setOverlayTitle, acquireBusy],
  )

  return <AdminDocumentMetaContext.Provider value={value}>{children}</AdminDocumentMetaContext.Provider>
}

function useAdminDocumentMeta() {
  const ctx = useContext(AdminDocumentMetaContext)
  if (!ctx) {
    throw new Error('AdminDocumentMetaProvider is required')
  }
  return ctx
}

/** Базовый title страницы (маршрут). */
export function useBaseDocumentTitle(title: string) {
  const { setBaseTitle } = useAdminDocumentMeta()
  useEffect(() => {
    setBaseTitle(title)
    return () => setBaseTitle('')
  }, [setBaseTitle, title])
}

/**
 * Overlay title для модалок/drawer.
 * Передайте `null`/`undefined`/пустую строку, чтобы снять overlay.
 */
export function useDocumentTitle(title: string | null | undefined) {
  const { setOverlayTitle } = useAdminDocumentMeta()
  const idRef = useRef(0)
  if (idRef.current === 0) {
    idRef.current = nextOverlayId++
  }

  useEffect(() => {
    setOverlayTitle(idRef.current, title ?? null)
    return () => setOverlayTitle(idRef.current, null)
  }, [setOverlayTitle, title])
}

/** Показать анимированный favicon, пока `busy === true`. */
export function useBusyFavicon(busy: boolean) {
  const { acquireBusy } = useAdminDocumentMeta()
  useEffect(() => {
    if (!busy) return
    return acquireBusy()
  }, [acquireBusy, busy])
}
