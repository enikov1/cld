import {
  DeleteOutlined,
  EyeOutlined,
  MenuOutlined,
  SaveOutlined,
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
import { Button, Input, Modal, Space, Spin, Switch, Table, message } from 'antd'
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

function extractIframeSrc(html: string): string | null {
  const match = html.match(/\bsrc\s*=\s*(["'])(.*?)\1/i)
  return match?.[2]?.trim() || null
}

function ensureVideoPlayerScript(html: string): string {
  if (!/<video-player\b/i.test(html)) return html
  if (/<script\b[^>]*\bsrc\s*=/i.test(html)) return html
  return `${html.trim()}\n<script async src="${CDN_VIDEOHUB_SCRIPT}"></script>`
}

function createPreviewIframe(src: string): HTMLIFrameElement {
  const iframe = document.createElement('iframe')
  iframe.src = src
  iframe.className = 'player-preview__iframe'
  iframe.setAttribute('allow', IFRAME_ALLOW)
  iframe.setAttribute('allowfullscreen', 'true')
  iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
  return iframe
}

function mountEmbedHtml(container: HTMLElement, html: string): void {
  const prepared = ensureVideoPlayerScript(html)
  const template = document.createElement('template')
  template.innerHTML = prepared

  const scripts: HTMLScriptElement[] = []
  template.content.querySelectorAll('script').forEach((oldScript) => {
    const script = document.createElement('script')
    for (const attr of Array.from(oldScript.attributes)) {
      script.setAttribute(attr.name, attr.value)
    }
    if (oldScript.textContent) {
      script.textContent = oldScript.textContent
    }
    oldScript.remove()
    scripts.push(script)
  })

  const wrap = document.createElement('div')
  wrap.className = 'player-preview__embed'
  wrap.appendChild(template.content)
  container.appendChild(wrap)
  for (const script of scripts) {
    wrap.appendChild(script)
  }
}

function PlayerPreviewFrame({ content }: { content: string }) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const el = containerRef.current
    if (!el) return

    el.innerHTML = ''
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
              el.innerHTML = ''
            }
          }
        }
        mountEmbedHtml(el, trimmed)
        return () => {
          el.innerHTML = ''
        }
      }

      const rutube = toRutubeEmbedUrl(trimmed)
      const src = rutube || (isHttpUrl(trimmed) ? trimmed.trim() : '')
      if (!src) {
        setError('Не удалось разобрать URL / embed-код для предпросмотра')
        return
      }

      el.appendChild(createPreviewIframe(src))
      return () => {
        el.innerHTML = ''
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
  const baselineRef = useRef('')
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  useBusyFavicon(saving || addingAlloha || addingRutube)

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
    setLoading(true)
    try {
      const data = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(`/api/admin/series/${kpId}/players`)
      applyRows(mapApiPlayers(data.players ?? []), true)
    } catch (e) {
      // New series: KP ID already set, but record not saved yet — keep empty list without toast.
      if (isApiNotFound(e)) {
        applyRows([], true)
      } else {
        message.error(String((e as Error).message))
      }
    } finally {
      setLoading(false)
    }
  }, [kpId, drawerOpen, applyRows, mapApiPlayers])

  useEffect(() => {
    if (!drawerOpen || !kpId) {
      applyRows([], true)
      setPreview(null)
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
      const players = validRows
        .map((row, index) => ({
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

  async function addRutubeTrailer() {
    if (!kpId) return

    setAddingRutube(true)
    try {
      const res = await api<{
        players: Array<Omit<PlayerRow, 'key'>>
        trailer?: { title?: string }
      }>(`/api/admin/series/${kpId}/players/add-rutube-trailer`, {
        method: 'POST',
        body: JSON.stringify({ tab_name: 'Трейлер' }),
      })
      applyRows(mapApiPlayers(res.players ?? []), true)
      const trailerTitle = res.trailer?.title?.trim()
      message.success(
        trailerTitle ? `Трейлер Rutube добавлен: ${trailerTitle}` : 'Трейлер Rutube добавлен в конец списка',
      )
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
          первой вкладкой. «Добавить трейлер Rutube» ищет трейлер по названию сериала и ставит embed в конец списка.
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
          <Button icon={<RutubeIcon />} loading={addingRutube} onClick={addRutubeTrailer}>
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
    </Spin>
  )
})

export default SeriesPlayersEditor
