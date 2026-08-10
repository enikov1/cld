import {
  DeleteOutlined,
  EyeOutlined,
  MenuOutlined,
  SaveOutlined,
  SearchOutlined,
  VideoCameraAddOutlined,
} from '@ant-design/icons'
import { AllohaIcon, RutubeIcon } from './brandIcons'
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DraggableAttributes,
  type DraggableSyntheticListeners,
} from '@dnd-kit/core'
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Button, Empty, Input, Modal, Space, Spin, Switch, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import {
  createContext,
  forwardRef,
  useCallback,
  useContext,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type HTMLAttributes,
} from 'react'
import { api, isApiNotFound } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'

const CDN_VIDEOHUB_SCRIPT = 'https://player.cdnvideohub.com/s2/stable/video-player.umd.js'
const IFRAME_ALLOW =
  'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'

type PlayerRow = {
  key: string
  id?: number | null
  provider: string
  iframe_url: string
  is_active: boolean
}

type PlayerPreview = {
  title: string
  content: string
}

type RutubeTrailerCandidate = {
  id: string
  title: string
  embed_url: string
  video_url: string
  thumbnail_url: string
  duration: number
  hits: number
  author: string
  score: number
  is_recommended: boolean
}

type DragHandleContextValue = {
  attributes: DraggableAttributes
  listeners: DraggableSyntheticListeners
  setActivatorNodeRef: (element: HTMLElement | null) => void
}

const DragHandleContext = createContext<DragHandleContextValue | null>(null)

function SortableRow(props: HTMLAttributes<HTMLTableRowElement> & { 'data-row-key': string }) {
  const { 'data-row-key': rowKey, style, ...restProps } = props
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: rowKey,
  })

  const rowStyle: CSSProperties = {
    ...style,
    transform: CSS.Transform.toString(transform),
    transition,
    ...(isDragging ? { position: 'relative', zIndex: 1 } : {}),
  }

  const contextValue = useMemo(
    () => ({ attributes, listeners, setActivatorNodeRef }),
    [attributes, listeners, setActivatorNodeRef],
  )

  return (
    <DragHandleContext.Provider value={contextValue}>
      <tr {...restProps} data-row-key={rowKey} ref={setNodeRef} style={rowStyle} />
    </DragHandleContext.Provider>
  )
}

function DragHandle() {
  const context = useContext(DragHandleContext)
  if (!context) return null

  return (
    <Button
      ref={context.setActivatorNodeRef}
      type="text"
      icon={<MenuOutlined />}
      title="Перетащить плеер"
      aria-label="Изменить порядок плеера"
      style={{ cursor: 'grab', touchAction: 'none' }}
      {...context.attributes}
      {...context.listeners}
    />
  )
}

function isHttpUrl(value: string): boolean {
  return /^https?:\/\//i.test(value.trim())
}

function isEmbedHtml(value: string): boolean {
  return /^<(?:video-player|iframe)\b/i.test(value.trim())
}

/** Mirrors App\Services\RutubeTrailerService::toEmbedUrl for preview. */
function toRutubeEmbedUrl(input: string): string {
  const value = input.trim()
  if (!value) return ''

  if (/^[a-f0-9]{32}$/i.test(value) || /^\d{5,12}$/.test(value)) {
    return `https://rutube.ru/play/embed/${value}`
  }

  if (!isHttpUrl(value)) return ''

  try {
    const url = new URL(value)
    if (!url.hostname.toLowerCase().endsWith('rutube.ru')) return ''

    const path = url.pathname
    let id = ''
    const embedMatch = path.match(/\/(?:play\/)?embed\/([a-zA-Z0-9]+)(?:\/|$)/)
    const videoMatch = path.match(/\/video\/(?:private\/)?([a-zA-Z0-9]+)(?:\/|$)/)
    const shortsMatch = path.match(/\/shorts\/([a-zA-Z0-9]+)(?:\/|$)/)
    if (embedMatch) id = embedMatch[1]
    else if (videoMatch) id = videoMatch[1]
    else if (shortsMatch) id = shortsMatch[1]

    if (!id) return ''
    return `https://rutube.ru/play/embed/${id}`
  } catch {
    return ''
  }
}

function formatDuration(seconds: number): string {
  if (!seconds || seconds < 0) return '—'
  const total = Math.floor(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  if (h > 0) {
    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  }
  return `${m}:${String(s).padStart(2, '0')}`
}

function formatHits(hits: number): string {
  if (!hits || hits < 0) return ''
  if (hits >= 1_000_000) return `${(hits / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`
  if (hits >= 1_000) return `${(hits / 1_000).toFixed(1).replace(/\.0$/, '')}K`
  return String(hits)
}

function extractIframeSrc(html: string): string | null {
  const match = html.match(/\bsrc\s*=\s*(["'])(.*?)\1/i)
  return match?.[2]?.trim() || null
}

const SAFE_VIDEO_PLAYER_ATTRS = new Set([
  'src',
  'poster',
  'title',
  'data-id',
  'data-hash',
  'data-season',
  'data-episode',
  'style',
  'class',
  'width',
  'height',
])

function createPreviewIframe(src: string): HTMLIFrameElement {
  const iframe = document.createElement('iframe')
  iframe.src = src
  iframe.className = 'player-preview__iframe'
  iframe.setAttribute('allow', IFRAME_ALLOW)
  iframe.setAttribute('allowfullscreen', 'true')
  iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
  iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups')
  return iframe
}

/** Mount video-player without executing any user-supplied scripts. */
function mountSafeVideoPlayer(container: HTMLElement, html: string): boolean {
  const parsed = new DOMParser().parseFromString(html, 'text/html')
  const source = parsed.querySelector('video-player')
  if (!source) return false

  const player = document.createElement('video-player')
  for (const attr of Array.from(source.attributes)) {
    const name = attr.name.toLowerCase()
    if (!SAFE_VIDEO_PLAYER_ATTRS.has(name) && !name.startsWith('data-')) continue
    // Block javascript: / data: URLs on src-like attrs
    if ((name === 'src' || name.endsWith('-src')) && /^\s*(javascript|data):/i.test(attr.value)) {
      continue
    }
    player.setAttribute(attr.name, attr.value)
  }

  const wrap = document.createElement('div')
  wrap.className = 'player-preview__embed'
  wrap.appendChild(player)
  container.appendChild(wrap)

  if (!document.querySelector(`script[data-admin-videohub="1"]`)) {
    const script = document.createElement('script')
    script.async = true
    script.src = CDN_VIDEOHUB_SCRIPT
    script.dataset.adminVideohub = '1'
    document.head.appendChild(script)
  }

  return true
}

function PlayerPreviewFrame({ content }: { content: string }) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const el = containerRef.current
    if (!el) return

    el.replaceChildren()
    setError(null)

    const trimmed = content.trim()
    if (!trimmed) {
      setError('Укажите URL или embed-код плеера')
      return
    }

    try {
      if (isEmbedHtml(trimmed)) {
        if (/^<iframe\b/i.test(trimmed)) {
          const src = extractIframeSrc(trimmed)
          if (src && isHttpUrl(src)) {
            el.appendChild(createPreviewIframe(src))
            return () => {
              el.replaceChildren()
            }
          }
          setError('В iframe нужен безопасный http(s) src')
          return
        }

        if (/^<video-player\b/i.test(trimmed)) {
          if (mountSafeVideoPlayer(el, trimmed)) {
            return () => {
              el.replaceChildren()
            }
          }
          setError('Не удалось разобрать video-player для предпросмотра')
          return
        }

        setError('Предпросмотр поддерживает только iframe и video-player')
        return
      }

      const rutube = toRutubeEmbedUrl(trimmed)
      const src = rutube || (isHttpUrl(trimmed) ? trimmed.trim() : '')
      if (!src) {
        setError('Не удалось разобрать URL / embed-код для предпросмотра')
        return
      }

      el.appendChild(createPreviewIframe(src))
      return () => {
        el.replaceChildren()
      }
    } catch {
      setError('Ошибка при сборке предпросмотра')
    }
  }, [content])

  return (
    <div className="player-preview">
      {error ? <p className="admin-empty-hint">{error}</p> : null}
      <div ref={containerRef} className="player-preview__frame" />
    </div>
  )
}

export type SeriesPlayersEditorHandle = {
  save: (options?: { silent?: boolean; kpId?: string }) => Promise<boolean>
  isDirty: () => boolean
}

function serializePlayers(rows: PlayerRow[]): string {
  return JSON.stringify(
    rows.map((row) => ({
      id: row.id ?? null,
      provider: row.provider,
      iframe_url: row.iframe_url,
      is_active: row.is_active,
    })),
  )
}

type Props = {
  kpId?: string | null
  drawerOpen: boolean
  refreshKey?: number
  onCountChange?: (count: number) => void
}

const SeriesPlayersEditor = forwardRef<SeriesPlayersEditorHandle, Props>(function SeriesPlayersEditor(
  { kpId, drawerOpen, refreshKey = 0, onCountChange },
  ref,
) {
  const [rows, setRows] = useState<PlayerRow[]>([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [addingAlloha, setAddingAlloha] = useState(false)
  const [addingRutube, setAddingRutube] = useState(false)
  const [preview, setPreview] = useState<PlayerPreview | null>(null)
  const [rutubePickerOpen, setRutubePickerOpen] = useState(false)
  const [rutubeQuery, setRutubeQuery] = useState('')
  const [rutubeDefaultQuery, setRutubeDefaultQuery] = useState('')
  const [rutubeCandidates, setRutubeCandidates] = useState<RutubeTrailerCandidate[]>([])
  const [rutubeSelectedId, setRutubeSelectedId] = useState<string | null>(null)
  const [rutubeSearching, setRutubeSearching] = useState(false)
  const baselineRef = useRef('')
  const loadSeqRef = useRef(0)
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  useBusyFavicon(saving || addingAlloha || addingRutube)

  const selectedRutube = useMemo(
    () => rutubeCandidates.find((item) => item.id === rutubeSelectedId) ?? null,
    [rutubeCandidates, rutubeSelectedId],
  )

  const applyRows = useCallback((nextRows: PlayerRow[], asBaseline = false) => {
    setRows(nextRows)
    if (asBaseline) {
      baselineRef.current = serializePlayers(nextRows)
    }
  }, [])

  const mapApiPlayers = useCallback((players: Array<Omit<PlayerRow, 'key'>>): PlayerRow[] => {
    return (players ?? []).map((item, index) => ({
      ...item,
      key: String(item.id ?? `new-${index}`),
      provider: item.provider ?? '',
      iframe_url: item.iframe_url ?? '',
      is_active: item.is_active ?? true,
    }))
  }, [])

  const load = useCallback(async () => {
    if (!kpId || !drawerOpen) return
    const seq = ++loadSeqRef.current
    setLoading(true)
    try {
      const data = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(`/api/admin/series/${kpId}/players`)
      if (seq !== loadSeqRef.current) return
      applyRows(mapApiPlayers(data.players ?? []), true)
    } catch (e) {
      if (seq !== loadSeqRef.current) return
      // New series: KP ID already set, but record not saved yet — keep empty list without toast.
      if (isApiNotFound(e)) {
        applyRows([], true)
      } else {
        message.error(String((e as Error).message))
      }
    } finally {
      if (seq === loadSeqRef.current) setLoading(false)
    }
  }, [kpId, drawerOpen, applyRows, mapApiPlayers])

  useEffect(() => {
    if (!drawerOpen || !kpId) {
      applyRows([], true)
      setPreview(null)
      setRutubePickerOpen(false)
      return
    }
    load()
  }, [kpId, drawerOpen, refreshKey, load, applyRows])

  useEffect(() => {
    onCountChange?.(rows.length)
  }, [rows.length, onCountChange])

  const savePlayers = useCallback(
    async (options?: { silent?: boolean; kpId?: string }) => {
      const targetKpId = options?.kpId ?? kpId
      if (!targetKpId) return true

      const validRows = rows.filter((row) => row.iframe_url.trim() !== '')
      const players = validRows.map((row, index) => ({
        id: row.id ?? undefined,
        provider: row.provider.trim(),
        iframe_url: row.iframe_url.trim(),
        is_active: row.is_active,
        priority: (validRows.length - index) * 10,
      }))

      setSaving(true)
      try {
        const res = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(`/api/admin/series/${targetKpId}/players`, {
          method: 'POST',
          body: JSON.stringify({ players }),
        })
        applyRows(mapApiPlayers(res.players ?? []), true)
        if (!options?.silent) {
          message.success('Плееры сохранены')
        }
        return true
      } catch (e) {
        if (!options?.silent) {
          message.error(String((e as Error).message))
        }
        return false
      } finally {
        setSaving(false)
      }
    },
    [kpId, rows, applyRows, mapApiPlayers],
  )

  useImperativeHandle(
    ref,
    () => ({
      save: savePlayers,
      isDirty: () => serializePlayers(rows) !== baselineRef.current,
    }),
    [savePlayers, rows],
  )

  function addPlayer() {
    setRows([
      ...rows,
      {
        key: `new-${Date.now()}`,
        provider: `Плеер ${rows.length + 1}`,
        iframe_url: '',
        is_active: true,
      },
    ])
  }

  async function addAllohaPlayer() {
    if (!kpId) return

    setAddingAlloha(true)
    try {
      const res = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(
        `/api/admin/series/${kpId}/players/add-alloha`,
        {
          method: 'POST',
          body: JSON.stringify({ tab_name: `Плеер ${rows.length + 1}` }),
        },
      )
      applyRows(mapApiPlayers(res.players ?? []), true)
      message.success('Плеер Alloha добавлен в конец списка')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setAddingAlloha(false)
    }
  }

  const searchRutubeTrailers = useCallback(
    async (query?: string) => {
      if (!kpId) return

      setRutubeSearching(true)
      try {
        const params = new URLSearchParams()
        const trimmed = (query ?? '').trim()
        if (trimmed) params.set('query', trimmed)
        params.set('limit', '15')

        const res = await api<{
          query: string
          default_query: string
          candidates: RutubeTrailerCandidate[]
        }>(`/api/admin/series/${kpId}/players/rutube-trailer/search?${params.toString()}`)

        const candidates = res.candidates ?? []
        setRutubeCandidates(candidates)
        setRutubeQuery(res.query ?? trimmed)
        setRutubeDefaultQuery(res.default_query ?? '')
        setRutubeSelectedId((prev) => {
          if (prev && candidates.some((item) => item.id === prev)) return prev
          return candidates[0]?.id ?? null
        })
      } catch (e) {
        message.error(String((e as Error).message))
      } finally {
        setRutubeSearching(false)
      }
    },
    [kpId],
  )

  async function openRutubePicker() {
    if (!kpId) return
    setRutubePickerOpen(true)
    setRutubeCandidates([])
    setRutubeSelectedId(null)
    setRutubeQuery('')
    await searchRutubeTrailers()
  }

  function closeRutubePicker() {
    if (addingRutube) return
    setRutubePickerOpen(false)
  }

  async function confirmRutubeTrailer() {
    if (!kpId || !selectedRutube) {
      message.warning('Выберите трейлер для добавления')
      return
    }

    setAddingRutube(true)
    try {
      const res = await api<{
        players: Array<Omit<PlayerRow, 'key'>>
        trailer?: { title?: string }
      }>(`/api/admin/series/${kpId}/players/add-rutube-trailer`, {
        method: 'POST',
        body: JSON.stringify({
          tab_name: 'Трейлер',
          embed_url: selectedRutube.embed_url,
          title: selectedRutube.title,
        }),
      })
      applyRows(mapApiPlayers(res.players ?? []), true)
      const trailerTitle = (res.trailer?.title ?? selectedRutube.title)?.trim()
      message.success(
        trailerTitle ? `Трейлер Rutube добавлен: ${trailerTitle}` : 'Трейлер Rutube добавлен в конец списка',
      )
      setRutubePickerOpen(false)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setAddingRutube(false)
    }
  }

  function updateRow(key: string, patch: Partial<PlayerRow>) {
    setRows(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)))
  }

  function removeRow(key: string) {
    setRows(rows.filter((row) => row.key !== key))
  }

  function openPreview(row: PlayerRow) {
    const content = row.iframe_url.trim()
    if (!content) {
      message.warning('Сначала укажите URL или embed-код')
      return
    }
    setPreview({
      title: row.provider.trim() || 'Плеер',
      content,
    })
  }

  function handleDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return

    setRows((currentRows) => {
      const oldIndex = currentRows.findIndex((row) => row.key === active.id)
      const newIndex = currentRows.findIndex((row) => row.key === over.id)
      return oldIndex < 0 || newIndex < 0 ? currentRows : arrayMove(currentRows, oldIndex, newIndex)
    })
  }

  const columns: ColumnsType<PlayerRow> = [
    {
      title: '',
      key: 'sort',
      width: 48,
      align: 'center',
      render: () => <DragHandle />,
    },
    {
      title: 'Название вкладки',
      dataIndex: 'provider',
      width: 180,
      render: (_, row) => (
        <Input
          value={row.provider}
          placeholder="Смотреть онлайн"
          onChange={(e) => updateRow(row.key, { provider: e.target.value })}
        />
      ),
    },
    {
      title: 'URL / embed-код',
      dataIndex: 'iframe_url',
      render: (_, row) => (
        <Input
          value={row.iframe_url}
          placeholder="https://... или <video-player ..."
          onChange={(e) => updateRow(row.key, { iframe_url: e.target.value })}
        />
      ),
    },
    {
      title: 'Активен',
      dataIndex: 'is_active',
      width: 90,
      render: (_, row) => (
        <Switch checked={row.is_active} onChange={(checked) => updateRow(row.key, { is_active: checked })} />
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 148,
      render: (_, row) => (
        <Space size={4} wrap={false}>
          <Button
            size="small"
            icon={<EyeOutlined />}
            onClick={() => openPreview(row)}
            disabled={!row.iframe_url.trim()}
            title="Предпросмотр"
            aria-label="Предпросмотр плеера"
          />
          <Button
            danger
            size="small"
            icon={<DeleteOutlined />}
            onClick={() => removeRow(row.key)}
            title="Удалить"
            aria-label="Удалить плеер"
          />
        </Space>
      ),
    },
  ]

  if (!kpId) {
    return <p className="admin-empty-hint">Сначала укажите KP ID или TMDB ID и сохраните сериал, затем добавьте плееры.</p>
  }

  return (
    <Spin spinning={loading}>
      <Space direction="vertical" size="middle" style={{ width: '100%' }}>
        <p className="admin-empty-hint">
          Добавьте один или несколько embed-плееров. Перетаскивайте строки за маркер слева: верхний плеер отображается
          первой вкладкой. «Добавить трейлер Rutube» открывает поиск с предпросмотром — вы сами выбираете нужный ролик.
          Ссылки вида rutube.ru/video/... при сохранении превращаются в play/embed. Кнопка с глазом открывает
          предпросмотр во всплывающем окне. Изменения также сохраняются кнопкой «Сохранить» в шапке редактора.
        </p>
        <Space wrap>
          <Button icon={<VideoCameraAddOutlined />} onClick={addPlayer}>
            Добавить плеер
          </Button>
          <Button icon={<AllohaIcon />} loading={addingAlloha} onClick={addAllohaPlayer}>
            Добавить плеер Alloha
          </Button>
          <Button icon={<RutubeIcon />} onClick={() => void openRutubePicker()}>
            Добавить трейлер Rutube
          </Button>
          <Button icon={<SaveOutlined />} type="primary" loading={saving} onClick={() => savePlayers()}>
            Сохранить только плееры
          </Button>
        </Space>
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={rows.map((row) => row.key)} strategy={verticalListSortingStrategy}>
            <Table
              rowKey="key"
              columns={columns}
              dataSource={rows}
              pagination={false}
              scroll={{ x: 960 }}
              components={{ body: { row: SortableRow } }}
            />
          </SortableContext>
        </DndContext>
      </Space>

      <Modal
        title={preview ? `Предпросмотр: ${preview.title}` : 'Предпросмотр плеера'}
        open={Boolean(preview)}
        onCancel={() => setPreview(null)}
        footer={null}
        width={920}
        destroyOnHidden
        centered
        styles={{ body: { paddingTop: 12 } }}
      >
        {preview ? <PlayerPreviewFrame content={preview.content} /> : null}
      </Modal>

      <Modal
        title="Выбор трейлера Rutube"
        open={rutubePickerOpen}
        onCancel={closeRutubePicker}
        width={1080}
        destroyOnHidden
        centered
        okText="Добавить выбранный"
        cancelText="Отмена"
        confirmLoading={addingRutube}
        okButtonProps={{ disabled: !selectedRutube, icon: <RutubeIcon /> }}
        onOk={() => void confirmRutubeTrailer()}
        styles={{ body: { paddingTop: 12 } }}
      >
        <Space direction="vertical" size="middle" style={{ width: '100%' }}>
          <Input.Search
            value={rutubeQuery}
            onChange={(e) => setRutubeQuery(e.target.value)}
            onSearch={(value) => void searchRutubeTrailers(value)}
            placeholder="Название + трейлер + год"
            enterButton={
              <Button icon={<SearchOutlined />} loading={rutubeSearching}>
                Найти
              </Button>
            }
            allowClear
            disabled={addingRutube}
          />
          {rutubeDefaultQuery && rutubeQuery.trim() !== rutubeDefaultQuery ? (
            <Button
              type="link"
              size="small"
              style={{ paddingInline: 0 }}
              disabled={rutubeSearching || addingRutube}
              onClick={() => void searchRutubeTrailers(rutubeDefaultQuery)}
            >
              Сбросить к запросу: {rutubeDefaultQuery}
            </Button>
          ) : null}

          <div className="rutube-picker">
            <div className="rutube-picker__list">
              <Spin spinning={rutubeSearching}>
                {rutubeCandidates.length === 0 && !rutubeSearching ? (
                  <Empty description="Ничего не найдено — измените запрос" image={Empty.PRESENTED_IMAGE_SIMPLE} />
                ) : (
                  <div className="rutube-picker__items" role="listbox" aria-label="Результаты поиска Rutube">
                    {rutubeCandidates.map((item) => {
                      const active = item.id === rutubeSelectedId
                      return (
                        <button
                          key={item.id}
                          type="button"
                          role="option"
                          aria-selected={active}
                          className={`rutube-picker__item${active ? ' is-active' : ''}`}
                          onClick={() => setRutubeSelectedId(item.id)}
                          disabled={addingRutube}
                        >
                          <span className="rutube-picker__thumb">
                            {item.thumbnail_url ? (
                              <img src={item.thumbnail_url} alt="" loading="lazy" />
                            ) : (
                              <span className="rutube-picker__thumb-fallback" />
                            )}
                            <span className="rutube-picker__duration">{formatDuration(item.duration)}</span>
                          </span>
                          <span className="rutube-picker__meta">
                            <span className="rutube-picker__title">{item.title || 'Без названия'}</span>
                            <span className="rutube-picker__sub">
                              {[item.author, formatHits(item.hits) ? `${formatHits(item.hits)} просм.` : '']
                                .filter(Boolean)
                                .join(' · ')}
                            </span>
                            {item.is_recommended ? <Tag color="green">Рекомендуемый</Tag> : null}
                          </span>
                        </button>
                      )
                    })}
                  </div>
                )}
              </Spin>
            </div>

            <div className="rutube-picker__preview">
              {selectedRutube ? (
                <>
                  <div className="rutube-picker__preview-title">{selectedRutube.title}</div>
                  <PlayerPreviewFrame content={selectedRutube.embed_url} />
                  {selectedRutube.video_url ? (
                    <a href={selectedRutube.video_url} target="_blank" rel="noreferrer">
                      Открыть на Rutube
                    </a>
                  ) : null}
                </>
              ) : (
                <Empty description="Выберите ролик слева, чтобы посмотреть превью" image={Empty.PRESENTED_IMAGE_SIMPLE} />
              )}
            </div>
          </div>
        </Space>
      </Modal>
    </Spin>
  )
})

export default SeriesPlayersEditor
