import { ReloadOutlined } from '@ant-design/icons'
import { Button, Input, Select, Space, Table, Tag } from 'antd'
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import { useDocumentTitle } from '../documentMeta/AdminDocumentMeta'

type AuditLogItem = {
  id: number
  actor_type: string
  actor_name?: string | null
  actor_role?: string | null
  action: string
  entity_type?: string | null
  entity_id?: string | null
  summary?: string | null
  ip?: string | null
  created_at?: string | null
}

function formatDate(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('ru-RU')
}

export default function AuditLogPage() {
  const [items, setItems] = useState<AuditLogItem[]>([])
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(50)
  const [total, setTotal] = useState(0)
  const [action, setAction] = useState<string | undefined>()
  const [actorRole, setActorRole] = useState<string | undefined>()
  const [q, setQ] = useState('')

  useDocumentTitle('Журнал аудита')

  const load = useCallback(
    async (nextPage = page, nextPerPage = perPage) => {
      setLoading(true)
      try {
        const params = new URLSearchParams({
          page: String(nextPage),
          per_page: String(nextPerPage),
        })
        if (action) params.set('action', action)
        if (actorRole) params.set('actor_role', actorRole)
        if (q.trim()) params.set('q', q.trim())

        const data = await api<{
          items: AuditLogItem[]
          total: number
          page: number
          per_page: number
        }>(`/api/admin/audit-logs?${params}`)
        setItems(data.items)
        setTotal(data.total)
        setPage(data.page)
        setPerPage(data.per_page)
      } finally {
        setLoading(false)
      }
    },
    [action, actorRole, page, perPage, q],
  )

  useEffect(() => {
    void load(1, perPage)
    // eslint-disable-next-line react-hooks/exhaustive-deps -- initial + filter changes via buttons
  }, [])

  const columns: ColumnsType<AuditLogItem> = [
    {
      title: 'Когда',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 160,
      render: formatDate,
    },
    {
      title: 'Кто',
      key: 'actor',
      width: 200,
      render: (_, row) => (
        <Space direction="vertical" size={0}>
          <span>{row.actor_name || '—'}</span>
          <Space size={4}>
            <Tag>{row.actor_type}</Tag>
            {row.actor_role ? <Tag color="blue">{row.actor_role}</Tag> : null}
          </Space>
        </Space>
      ),
    },
    {
      title: 'Действие',
      dataIndex: 'action',
      key: 'action',
      width: 160,
      render: (value: string) => <Tag>{value}</Tag>,
    },
    {
      title: 'Сущность',
      key: 'entity',
      width: 140,
      render: (_, row) =>
        row.entity_type ? `${row.entity_type}${row.entity_id ? ` #${row.entity_id}` : ''}` : '—',
    },
    {
      title: 'Описание',
      dataIndex: 'summary',
      key: 'summary',
      ellipsis: true,
    },
    {
      title: 'IP',
      dataIndex: 'ip',
      key: 'ip',
      width: 120,
      render: (v?: string | null) => v || '—',
    },
  ]

  function onTableChange(pagination: TablePaginationConfig) {
    const nextPage = pagination.current ?? 1
    const nextPerPage = pagination.pageSize ?? perPage
    void load(nextPage, nextPerPage)
  }

  return (
    <div>
      <Space style={{ marginBottom: 16 }} wrap>
        <Input.Search
          allowClear
          placeholder="Поиск по описанию / имени"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onSearch={() => void load(1, perPage)}
          style={{ width: 260 }}
        />
        <Select
          allowClear
          placeholder="Действие"
          style={{ width: 200 }}
          value={action}
          onChange={(value) => setAction(value)}
          options={[
            { value: 'series.create', label: 'series.create' },
            { value: 'series.update', label: 'series.update' },
            { value: 'series.delete', label: 'series.delete' },
            { value: 'settings.save', label: 'settings.save' },
            { value: 'admin_token.create', label: 'admin_token.create' },
            { value: 'admin_token.delete', label: 'admin_token.delete' },
          ]}
        />
        <Select
          allowClear
          placeholder="Роль"
          style={{ width: 140 }}
          value={actorRole}
          onChange={(value) => setActorRole(value)}
          options={[
            { value: 'full', label: 'full' },
            { value: 'content', label: 'content' },
            { value: 'moderation', label: 'moderation' },
          ]}
        />
        <Button type="primary" onClick={() => void load(1, perPage)} loading={loading}>
          Применить
        </Button>
        <Button icon={<ReloadOutlined />} onClick={() => void load(page, perPage)} loading={loading}>
          Обновить
        </Button>
      </Space>

      <Table
        rowKey="id"
        loading={loading}
        columns={columns}
        dataSource={items}
        size="middle"
        pagination={{
          current: page,
          pageSize: perPage,
          total,
          showSizeChanger: true,
          pageSizeOptions: ['20', '50', '100'],
        }}
        onChange={onTableChange}
      />
    </div>
  )
}
