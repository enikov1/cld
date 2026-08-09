import { Button, Form, Input, Modal, Popconfirm, Segmented, Space, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import type { AdminStats, CommentItem } from '../types'
import { ADMIN_ROUTES } from '../routes/adminRoutes'
import { siteOrigin } from '../utils/mediaUrl'
import { seriesPublicPath } from '../utils/seriesPublicPath'

const statusLabels: Record<string, string> = {
  all: 'Все',
  pending: 'На модерации',
  approved: 'Одобренные',
  rejected: 'Скрытые',
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function userAdminPath(user: NonNullable<CommentItem['user']>): string {
  if (user.email) {
    return `${ADMIN_ROUTES.users}?email=${encodeURIComponent(user.email)}`
  }
  return `${ADMIN_ROUTES.users}?name=${encodeURIComponent(user.name)}`
}

export default function CommentsPage() {
  const [items, setItems] = useState<CommentItem[]>([])
  const [loading, setLoading] = useState(false)
  const [status, setStatus] = useState<string | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [editing, setEditing] = useState<CommentItem | null>(null)
  const [saving, setSaving] = useState(false)
  const [form] = Form.useForm<{ body: string }>()

  useDocumentTitle(
    editOpen
      ? editing
        ? `Редактируем комментарий #${editing.id}`
        : 'Редактируем комментарий'
      : null,
  )
  useBusyFavicon(saving)

  const load = useCallback(async (nextStatus: string) => {
    setLoading(true)
    try {
      const data = await api<{ items: CommentItem[] }>(`/api/admin/comments?status=${nextStatus}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        const stats = await api<AdminStats>('/api/admin/stats')
        if (!cancelled) {
          setStatus(stats.comments_pending > 0 ? 'pending' : 'all')
        }
      } catch {
        if (!cancelled) {
          setStatus('all')
        }
      }
    })()
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    if (status === null) return
    void load(status)
  }, [status, load])

  async function updateStatus(id: number, next: string) {
    try {
      await api(`/api/admin/comments/${id}/status`, {
        method: 'POST',
        body: JSON.stringify({ status: next }),
      })
      message.success('Статус обновлён')
      if (status) await load(status)
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
      if (status) await load(status)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/comments/${id}`, { method: 'DELETE' })
      message.success('Комментарий удалён')
      if (status) await load(status)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openEdit(row: CommentItem) {
    setEditing(row)
    form.setFieldsValue({ body: row.body })
    setEditOpen(true)
  }

  async function saveEdit() {
    if (!editing) return
    try {
      const values = await form.validateFields()
      setSaving(true)
      await api(`/api/admin/comments/${editing.id}`, {
        method: 'POST',
        body: JSON.stringify({ body: values.body }),
      })
      message.success('Комментарий обновлён')
      setEditOpen(false)
      setEditing(null)
      if (status) await load(status)
    } catch (e) {
      if (e && typeof e === 'object' && 'errorFields' in e) return
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  const columns: ColumnsType<CommentItem> = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    {
      title: 'Сериал',
      key: 'series',
      width: 180,
      ellipsis: true,
      render: (_, r) => {
        if (!r.series) return '—'
        return (
          <a href={`${siteOrigin()}${seriesPublicPath(r.series)}`} target="_blank" rel="noopener noreferrer">
            {r.series.title}
          </a>
        )
      },
    },
    {
      title: 'Пользователь',
      key: 'user',
      width: 160,
      render: (_, r) => {
        if (r.user) {
          return <Link to={userAdminPath(r.user)}>{r.user.name}</Link>
        }
        return r.author_name ?? '—'
      },
    },
    { title: 'Текст', dataIndex: 'body', key: 'body', ellipsis: true },
    {
      title: 'Дата',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 150,
      render: (v) => formatDateTime(v),
    },
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
      width: 380,
      render: (_, r) => (
        <Space wrap>
          <Button size="small" onClick={() => openEdit(r)}>
            Изменить
          </Button>
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
          <Popconfirm title="Удалить комментарий?" onConfirm={() => remove(r.id)} okText="Удалить" cancelText="Отмена" okButtonProps={{ danger: true }}>
            <Button size="small" danger>
              Удалить
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Segmented
          value={status ?? 'all'}
          disabled={status === null}
          onChange={(v) => setStatus(String(v))}
          options={Object.entries(statusLabels).map(([value, label]) => ({ value, label }))}
        />
      </div>
      <Table
        rowKey="id"
        loading={loading || status === null}
        columns={columns}
        dataSource={items}
        pagination={{ pageSize: 20 }}
      />
      <Modal
        title={editing ? `Изменить комментарий #${editing.id}` : 'Изменить комментарий'}
        open={editOpen}
        onCancel={() => {
          setEditOpen(false)
          setEditing(null)
        }}
        onOk={() => void saveEdit()}
        confirmLoading={saving}
        okText="Сохранить"
        cancelText="Отмена"
        destroyOnHidden
      >
        <Form form={form} layout="vertical">
          <Form.Item
            name="body"
            label="Текст"
            rules={[{ required: true, message: 'Введите текст комментария' }]}
          >
            <Input.TextArea rows={6} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
