import { Button, Form, Input, Modal, Popconfirm, Rate, Segmented, Space, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import SeriesSearchSelect from '../components/SeriesSearchSelect'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import type { ReviewItem } from '../types'
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

function userAdminPath(user: NonNullable<ReviewItem['user']>): string {
  if (user.email) {
    return `${ADMIN_ROUTES.users}?email=${encodeURIComponent(user.email)}`
  }
  return `${ADMIN_ROUTES.users}?name=${encodeURIComponent(user.name)}`
}

type CreateForm = {
  series_id: number | null
  rating: number
  author_name: string
  body: string
}

type EditForm = {
  rating: number
  author_name: string
  body: string
}

export default function ReviewsPage() {
  const [items, setItems] = useState<ReviewItem[]>([])
  const [loading, setLoading] = useState(false)
  const [status, setStatus] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [createOpen, setCreateOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [editing, setEditing] = useState<ReviewItem | null>(null)
  const [saving, setSaving] = useState(false)
  const [createForm] = Form.useForm<CreateForm>()
  const [editForm] = Form.useForm<EditForm>()

  useDocumentTitle(
    createOpen
      ? 'Новая рецензия'
      : editOpen
        ? editing
          ? `Редактируем рецензию #${editing.id}`
          : 'Редактируем рецензию'
        : null,
  )
  useBusyFavicon(saving)

  const load = useCallback(async (nextStatus: string, nextPage: number) => {
    setLoading(true)
    try {
      const data = await api<{ items: ReviewItem[]; total: number }>(
        `/api/admin/reviews?status=${nextStatus}&page=${nextPage}&per_page=50`,
      )
      setItems(data.items)
      setTotal(data.total ?? data.items.length)
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
        const counts = await api<{ reviews_pending: number }>('/api/admin/moderation-counts')
        if (!cancelled) {
          setStatus(counts.reviews_pending > 0 ? 'pending' : 'all')
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
    void load(status, page)
  }, [status, page, load])

  useEffect(() => {
    if (status !== 'pending' || loading || items.length > 0 || total > 0) return
    setStatus('all')
    setPage(1)
  }, [status, loading, items.length, total])

  async function updateStatus(id: number, next: string) {
    try {
      await api(`/api/admin/reviews/${id}/status`, {
        method: 'POST',
        body: JSON.stringify({ status: next }),
      })
      message.success('Статус обновлён')
      if (status) await load(status, page)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/reviews/${id}`, { method: 'DELETE' })
      message.success('Рецензия удалена')
      if (status) await load(status, page)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openCreate() {
    createForm.setFieldsValue({
      series_id: null,
      rating: 8,
      author_name: 'Редакция',
      body: '',
    })
    setCreateOpen(true)
  }

  function openEdit(row: ReviewItem) {
    setEditing(row)
    editForm.setFieldsValue({
      rating: row.rating,
      author_name: row.author_name || '',
      body: row.body,
    })
    setEditOpen(true)
  }

  async function saveCreate() {
    try {
      const values = await createForm.validateFields()
      if (!values.series_id) {
        message.error('Выберите сериал')
        return
      }
      setSaving(true)
      await api('/api/admin/reviews', {
        method: 'POST',
        body: JSON.stringify({
          series_id: values.series_id,
          rating: values.rating,
          author_name: values.author_name?.trim() || null,
          body: values.body,
          status: 'approved',
        }),
      })
      message.success('Рецензия добавлена')
      setCreateOpen(false)
      if (status) await load(status, page)
    } catch (e) {
      if (e && typeof e === 'object' && 'errorFields' in e) return
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  async function saveEdit() {
    if (!editing) return
    try {
      const values = await editForm.validateFields()
      setSaving(true)
      await api(`/api/admin/reviews/${editing.id}`, {
        method: 'POST',
        body: JSON.stringify({
          rating: values.rating,
          author_name: values.author_name?.trim() || null,
          body: values.body,
        }),
      })
      message.success('Рецензия обновлена')
      setEditOpen(false)
      setEditing(null)
      if (status) await load(status, page)
    } catch (e) {
      if (e && typeof e === 'object' && 'errorFields' in e) return
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  const columns: ColumnsType<ReviewItem> = [
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
      title: 'Автор',
      key: 'user',
      width: 160,
      render: (_, r) => {
        if (r.user) {
          return <Link to={userAdminPath(r.user)}>{r.user.name}</Link>
        }
        return r.author_display || r.author_name || '—'
      },
    },
    {
      title: 'Оценка',
      dataIndex: 'rating',
      key: 'rating',
      width: 90,
      render: (v: number) => `${v}/10`,
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
      title: 'Действия',
      key: 'actions',
      width: 320,
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
          <Popconfirm title="Удалить рецензию?" onConfirm={() => remove(r.id)} okText="Удалить" cancelText="Отмена" okButtonProps={{ danger: true }}>
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
      <div className="admin-toolbar" style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
        <Segmented
          value={status ?? 'all'}
          disabled={status === null}
          onChange={(v) => {
            setPage(1)
            setStatus(String(v))
          }}
          options={Object.entries(statusLabels).map(([value, label]) => ({ value, label }))}
        />
        <Button type="primary" onClick={openCreate}>
          Добавить рецензию
        </Button>
      </div>
      <Table
        rowKey="id"
        loading={loading || status === null}
        columns={columns}
        dataSource={items}
        pagination={{
          current: page,
          pageSize: 50,
          total,
          showSizeChanger: false,
          onChange: (next) => setPage(next),
        }}
      />

      <Modal
        title="Новая рецензия"
        open={createOpen}
        onCancel={() => setCreateOpen(false)}
        onOk={() => void saveCreate()}
        confirmLoading={saving}
        okText="Опубликовать"
        cancelText="Отмена"
        width={720}
        destroyOnHidden
      >
        <Form form={createForm} layout="vertical">
          <Form.Item name="series_id" label="Сериал" rules={[{ required: true, message: 'Выберите сериал' }]}>
            <SeriesSearchSelect />
          </Form.Item>
          <Form.Item name="rating" label="Оценка (1–10)" rules={[{ required: true, message: 'Укажите оценку' }]}>
            <Rate count={10} allowClear={false} />
          </Form.Item>
          <Form.Item name="author_name" label="Автор" extra="Для редакционных рецензий. По умолчанию — «Редакция».">
            <Input maxLength={120} placeholder="Редакция" />
          </Form.Item>
          <Form.Item
            name="body"
            label="Текст рецензии"
            rules={[
              { required: true, message: 'Введите текст' },
              { min: 20, message: 'Слишком короткий текст' },
            ]}
          >
            <Input.TextArea rows={8} placeholder="Развёрнутое мнение о сериале…" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={editing ? `Изменить рецензию #${editing.id}` : 'Изменить рецензию'}
        open={editOpen}
        onCancel={() => {
          setEditOpen(false)
          setEditing(null)
        }}
        onOk={() => void saveEdit()}
        confirmLoading={saving}
        okText="Сохранить"
        cancelText="Отмена"
        width={720}
        destroyOnHidden
      >
        <Form form={editForm} layout="vertical">
          <Form.Item name="rating" label="Оценка (1–10)" rules={[{ required: true, message: 'Укажите оценку' }]}>
            <Rate count={10} allowClear={false} />
          </Form.Item>
          <Form.Item name="author_name" label="Автор">
            <Input maxLength={120} />
          </Form.Item>
          <Form.Item name="body" label="Текст" rules={[{ required: true, message: 'Введите текст' }]}>
            <Input.TextArea rows={8} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
