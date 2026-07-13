import { Button, Input, InputNumber, Space, Spin, Switch, Table, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'

type PlayerRow = {
  key: string
  id?: number | null
  provider: string
  iframe_url: string
  is_active: boolean
  priority: number
}

type Props = {
  kpId?: string | null
  open: boolean
}

export default function SeriesPlayersEditor({ kpId, open }: Props) {
  const [rows, setRows] = useState<PlayerRow[]>([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!kpId || !open) return
    setLoading(true)
    try {
      const data = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(`/api/admin/series/${kpId}/players`)
      setRows(
        (data.players ?? []).map((item, index) => ({
          ...item,
          key: String(item.id ?? `new-${index}`),
          provider: item.provider ?? '',
          iframe_url: item.iframe_url ?? '',
          is_active: item.is_active ?? true,
          priority: item.priority ?? 100 - index,
        })),
      )
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [kpId, open])

  useEffect(() => {
    load()
  }, [load])

  function addPlayer() {
    const nextPriority = rows.length ? Math.min(...rows.map((r) => r.priority)) - 10 : 100
    setRows([
      ...rows,
      {
        key: `new-${Date.now()}`,
        provider: `Плеер ${rows.length + 1}`,
        iframe_url: '',
        is_active: true,
        priority: nextPriority,
      },
    ])
  }

  function updateRow(key: string, patch: Partial<PlayerRow>) {
    setRows(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)))
  }

  function removeRow(key: string) {
    setRows(rows.filter((row) => row.key !== key))
  }

  async function save() {
    if (!kpId) return
    const players = rows
      .map((row) => ({
        id: row.id ?? undefined,
        provider: row.provider.trim(),
        iframe_url: row.iframe_url.trim(),
        is_active: row.is_active,
        priority: row.priority,
      }))
      .filter((row) => row.iframe_url !== '')

    setSaving(true)
    try {
      const res = await api<{ players: Array<Omit<PlayerRow, 'key'>> }>(`/api/admin/series/${kpId}/players`, {
        method: 'POST',
        body: JSON.stringify({ players }),
      })
      setRows(
        (res.players ?? []).map((item, index) => ({
          ...item,
          key: String(item.id ?? `new-${index}`),
          provider: item.provider ?? '',
          iframe_url: item.iframe_url ?? '',
          is_active: item.is_active ?? true,
          priority: item.priority ?? 100 - index,
        })),
      )
      message.success('Плееры сохранены')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  const columns: ColumnsType<PlayerRow> = [
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
      title: 'URL iframe',
      dataIndex: 'iframe_url',
      render: (_, row) => (
        <Input
          value={row.iframe_url}
          placeholder="https://..."
          onChange={(e) => updateRow(row.key, { iframe_url: e.target.value })}
        />
      ),
    },
    {
      title: 'Приоритет',
      dataIndex: 'priority',
      width: 110,
      render: (_, row) => (
        <InputNumber
          style={{ width: '100%' }}
          value={row.priority}
          onChange={(value) => updateRow(row.key, { priority: Number(value ?? 0) })}
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
    return <p className="admin-empty-hint">Сначала сохраните сериал с KP ID, затем добавьте плееры.</p>
  }

  return (
    <Spin spinning={loading}>
      <Space direction="vertical" size="middle" style={{ width: '100%' }}>
        <p className="admin-empty-hint">
          Добавьте один или несколько embed-плееров. На сайте они отображаются вкладками. Чем выше приоритет — тем левее вкладка.
        </p>
        <Space wrap>
          <Button onClick={addPlayer}>Добавить плеер</Button>
          <Button type="primary" loading={saving} onClick={save}>
            Сохранить плееры
          </Button>
        </Space>
        <Table rowKey="key" columns={columns} dataSource={rows} pagination={false} scroll={{ x: 900 }} />
      </Space>
    </Spin>
  )
}
