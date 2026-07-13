import { Button, Segmented, Space, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { CommentItem } from '../types'

const statusLabels: Record<string, string> = {
  all: 'Все',
  pending: 'На модерации',
  approved: 'Одобренные',
  rejected: 'Скрытые',
}

export default function CommentsPage() {
  const [items, setItems] = useState<CommentItem[]>([])
  const [loading, setLoading] = useState(false)
  const [status, setStatus] = useState<string>('pending')

  const load = useCallback(async (nextStatus = status) => {
    setLoading(true)
    try {
      const data = await api<{ items: CommentItem[] }>(`/api/admin/comments?status=${nextStatus}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [status])

  useEffect(() => {
    load()
  }, [load])

  async function updateStatus(id: number, next: string) {
    try {
      await api(`/api/admin/comments/${id}/status`, {
        method: 'POST',
        body: JSON.stringify({ status: next }),
      })
      message.success('Статус обновлён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function togglePin(row: CommentItem) {
    try {
      await api(`/api/admin/comments/${row.id}/pin`, {
        method: 'POST',
        body: JSON.stringify({ pinned: !row.is_pinned }),
      })
      message.success(row.is_pinned ? 'Комментарий откреплён' : 'Комментарий закреплён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/comments/${id}`, { method: 'DELETE' })
      message.success('Комментарий удалён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<CommentItem> = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    { title: 'Сериал', key: 'series', width: 180, ellipsis: true, render: (_, r) => r.series?.title ?? '—' },
    { title: 'Пользователь', key: 'user', width: 160, render: (_, r) => r.user?.name ?? r.author_name ?? '—' },
    { title: 'Текст', dataIndex: 'body', key: 'body', ellipsis: true },
    { title: 'Дата', dataIndex: 'created_at', key: 'created_at', width: 170 },
    {
      title: 'Статус',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (v) => (
        <Tag color={v === 'approved' ? 'green' : v === 'rejected' ? 'red' : 'gold'}>
          {statusLabels[v] ?? v}
        </Tag>
      ),
    },
    {
      title: 'Закреп',
      key: 'pinned',
      width: 100,
      render: (_, r) => (r.is_pinned ? <Tag color="orange">Да</Tag> : null),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 320,
      render: (_, r) => (
        <Space wrap>
          {r.status !== 'approved' ? (
            <Button size="small" type="primary" ghost onClick={() => updateStatus(r.id, 'approved')}>
              Одобрить
            </Button>
          ) : null}
          {r.status !== 'rejected' ? (
            <Button size="small" onClick={() => updateStatus(r.id, 'rejected')}>
              Скрыть
            </Button>
          ) : null}
          {r.status === 'approved' && !r.parent_id ? (
            <Button size="small" onClick={() => togglePin(r)}>
              {r.is_pinned ? 'Открепить' : 'Закрепить'}
            </Button>
          ) : null}
          <Button size="small" danger onClick={() => remove(r.id)}>
            Удалить
          </Button>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Segmented
          value={status}
          onChange={(v) => {
            const next = String(v)
            setStatus(next)
            load(next)
          }}
          options={Object.entries(statusLabels).map(([value, label]) => ({ value, label }))}
        />
      </div>
      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={{ pageSize: 20 }} />
    </div>
  )
}
