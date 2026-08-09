import {
  AppstoreOutlined,
  BankOutlined,
  BookOutlined,
  FolderOpenOutlined,
  SearchOutlined,
  SettingOutlined,
  TeamOutlined,
  UserOutlined,
  VideoCameraOutlined,
} from '@ant-design/icons'
import { Input, Spin } from 'antd'
import type { InputRef } from 'antd'
import type { KeyboardEvent as ReactKeyboardEvent, ReactNode } from 'react'
import { useEffect, useId, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { resolveMediaUrl } from '../utils/mediaUrl'

type GlobalSearchItem = {
  type: string
  id: string
  title: string
  subtitle?: string | null
  path: string
  image?: string | null
  image_kind?: 'poster' | 'cover' | 'logo' | 'avatar' | null
  meta?: Record<string, string>
}

type GlobalSearchGroup = {
  key: string
  label: string
  items: GlobalSearchItem[]
}

type GlobalSearchResponse = {
  q: string
  groups: GlobalSearchGroup[]
}

const TYPE_ICON: Record<string, ReactNode> = {
  setting: <SettingOutlined />,
  page: <AppstoreOutlined />,
  series: <VideoCameraOutlined />,
  collection: <FolderOpenOutlined />,
  studio: <BankOutlined />,
  taxonomy: <BookOutlined />,
  user: <TeamOutlined />,
}

function itemKey(item: GlobalSearchItem): string {
  return `${item.type}:${item.id}:${item.path}`
}

function previewKind(item: GlobalSearchItem): 'poster' | 'cover' | 'logo' | 'avatar' | 'icon' {
  if (item.image_kind === 'poster' || item.image_kind === 'cover' || item.image_kind === 'logo' || item.image_kind === 'avatar') {
    return item.image_kind
  }
  if (item.type === 'series') return 'poster'
  if (item.type === 'collection') return 'cover'
  if (item.type === 'studio') return 'logo'
  if (item.type === 'user' || item.meta?.taxonomy_type === 'people') return 'avatar'
  return 'icon'
}

function SearchPreview({ item }: { item: GlobalSearchItem }) {
  const kind = previewKind(item)
  const src = resolveMediaUrl(item.image)
  const [broken, setBroken] = useState(false)

  if (src && !broken) {
    return (
      <span className={`admin-global-search__preview admin-global-search__preview--${kind}`}>
        <img src={src} alt="" loading="lazy" onError={() => setBroken(true)} />
      </span>
    )
  }

  return (
    <span className={`admin-global-search__preview admin-global-search__preview--${kind} is-empty`}>
      {kind === 'avatar' ? <UserOutlined /> : (TYPE_ICON[item.type] ?? <SearchOutlined />)}
    </span>
  )
}

export default function AdminGlobalSearch() {
  const navigate = useNavigate()
  const listId = useId()
  const rootRef = useRef<HTMLDivElement | null>(null)
  const inputRef = useRef<InputRef | null>(null)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const requestIdRef = useRef(0)

  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(false)
  const [groups, setGroups] = useState<GlobalSearchGroup[]>([])
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)

  const flatItems = useMemo(
    () => groups.flatMap((group) => group.items),
    [groups],
  )

  const trimmed = query.trim()
  const showPanel = open && trimmed.length > 0

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        inputRef.current?.focus()
        setOpen(true)
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  useEffect(() => {
    if (!showPanel) return
    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
        setActiveIndex(-1)
      }
    }
    document.addEventListener('mousedown', onPointerDown)
    return () => document.removeEventListener('mousedown', onPointerDown)
  }, [showPanel])

  useEffect(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
    }

    if (trimmed.length < 1) {
      setGroups([])
      setLoading(false)
      setActiveIndex(-1)
      return
    }

    setLoading(true)
    setActiveIndex(-1)
    timerRef.current = setTimeout(() => {
      const requestId = ++requestIdRef.current
      api<GlobalSearchResponse>(`/api/admin/global-search?q=${encodeURIComponent(trimmed)}&limit=8`)
        .then((data) => {
          if (requestId !== requestIdRef.current) return
          setGroups(data.groups ?? [])
        })
        .catch(() => {
          if (requestId !== requestIdRef.current) return
          setGroups([])
        })
        .finally(() => {
          if (requestId !== requestIdRef.current) return
          setLoading(false)
        })
    }, 220)

    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current)
      }
    }
  }, [trimmed])

  function goTo(item: GlobalSearchItem) {
    setOpen(false)
    setQuery('')
    setGroups([])
    setActiveIndex(-1)
    navigate(item.path)
  }

  function onInputKeyDown(event: ReactKeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Escape') {
      event.preventDefault()
      setOpen(false)
      setActiveIndex(-1)
      inputRef.current?.blur()
      return
    }

    if (!showPanel || flatItems.length === 0) {
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((prev) => (prev + 1) % flatItems.length)
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((prev) => (prev <= 0 ? flatItems.length - 1 : prev - 1))
      return
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault()
      const item = flatItems[activeIndex]
      if (item) goTo(item)
    }
  }

  let flatOffset = 0

  return (
    <div className="admin-global-search" ref={rootRef}>
      <Input
        allowClear
        size="middle"
        ref={inputRef}
        value={query}
        prefix={<SearchOutlined className="admin-global-search__icon" />}
        placeholder="Поиск…"
        suffix={query ? undefined : <kbd className="admin-global-search__kbd">Ctrl+K</kbd>}
        aria-label="Глобальный поиск по админке"
        aria-autocomplete="list"
        aria-controls={listId}
        aria-expanded={showPanel}
        onFocus={() => setOpen(true)}
        onChange={(event) => {
          setQuery(event.target.value)
          setOpen(true)
        }}
        onKeyDown={onInputKeyDown}
      />

      {showPanel ? (
        <div className="admin-global-search__panel" id={listId} role="listbox">
          {loading ? (
            <div className="admin-global-search__empty">
              <Spin size="small" />
              <span>Поиск…</span>
            </div>
          ) : flatItems.length === 0 ? (
            <div className="admin-global-search__empty">Ничего не найдено</div>
          ) : (
            groups.map((group) => {
              const groupStart = flatOffset
              const nodes = (
                <div key={group.key} className="admin-global-search__section">
                  <div className="admin-global-search__group">{group.label}</div>
                  {group.items.map((item, index) => {
                    const flatIndex = groupStart + index
                    const active = flatIndex === activeIndex
                    return (
                      <button
                        key={itemKey(item)}
                        type="button"
                        role="option"
                        aria-selected={active}
                        className={`admin-global-search__option${active ? ' is-active' : ''}`}
                        onMouseEnter={() => setActiveIndex(flatIndex)}
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => goTo(item)}
                      >
                        <SearchPreview item={item} />
                        <span className="admin-global-search__option-text">
                          <span className="admin-global-search__option-title">{item.title}</span>
                          {item.subtitle ? (
                            <span className="admin-global-search__option-sub">{item.subtitle}</span>
                          ) : null}
                        </span>
                      </button>
                    )
                  })}
                </div>
              )
              flatOffset += group.items.length
              return nodes
            })
          )}
        </div>
      ) : null}
    </div>
  )
}
