import { MenuOutlined } from '@ant-design/icons'
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
import { Button, Input, Space, Spin, Switch, Table, message } from 'antd'
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

type PlayerRow = {
  key: string
  id?: number | null
  provider: string
  iframe_url: string
  is_active: boolean
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
  const baselineRef = useRef('')
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  useBusyFavicon(saving || addingAlloha)

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

  function updateRow(key: string, patch: Partial<PlayerRow>) {
    setRows(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)))
  }

  function removeRow(key: string) {
    setRows(rows.filter((row) => row.key !== key))
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
      width: 90,
      render: (_, row) => (
        <Button danger size="small" onClick={() => removeRow(row.key)}>
          Удалить
        </Button>
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
          первой вкладкой. Изменения сохраняются кнопкой «Сохранить» в шапке редактора.
        </p>
        <Space wrap>
          <Button onClick={addPlayer}>Добавить плеер</Button>
          <Button loading={addingAlloha} onClick={addAllohaPlayer}>
            Добавить плеер Alloha
          </Button>
          <Button loading={saving} onClick={() => savePlayers()}>
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
              scroll={{ x: 900 }}
              components={{ body: { row: SortableRow } }}
            />
          </SortableContext>
        </DndContext>
      </Space>
    </Spin>
  )
})

export default SeriesPlayersEditor
