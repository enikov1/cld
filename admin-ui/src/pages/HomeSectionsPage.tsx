import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  PlusOutlined,
} from '@ant-design/icons'
import {
  Button,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import type { CategoryItem } from '../types'

type HomeSectionItem = {
  id: number
  category_id?: number | null
  category?: { id: number; slug: string; title: string } | null
  title: string
  sort_order: number
  is_active: boolean
  item_limit: number
  show_tabs: boolean
  default_sort: 'latest' | 'popular' | 'rating'
}

const SORT_OPTIONS = [
  { value: 'latest', label: 'Последние' },
  { value: 'popular', label: 'Популярные' },
  { value: 'rating', label: 'По рейтингу' },
]

export default function HomeSectionsPage() {
  const [items, setItems] = useState<HomeSectionItem[]>([])
  const [categories, setCategories] = useState<CategoryItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<HomeSectionItem | null>(null)
  const [form] = Form.useForm()

  const categoryOptions = useMemo(
    () => categories.map((c) => ({ value: c.id, label: c.title })),
    [categories],
  )

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [sectionsData, categoriesData] = await Promise.all([
        api<{ items: HomeSectionItem[] }>('/api/admin/home-sections'),
        api<{ items: CategoryItem[] }>('/api/admin/categories'),
      ])
      setItems(sectionsData.items)
      setCategories(categoriesData.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({
      is_active: true,
      item_limit: 18,
      show_tabs: true,
      default_sort: 'latest',
      sort_order: (items.length + 1) * 10,
    })
    setModalOpen(true)
  }

  function openEdit(row: HomeSectionItem) {
    setEditing(row)
    form.setFieldsValue(row)
    setModalOpen(true)
  }

  async function save(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      await api('/api/admin/home-sections/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Секция обновлена' : 'Секция добавлена')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    await api(`/api/admin/home-sections/${id}`, { method: 'DELETE' })
    message.success('Секция удалена')
    await load()
  }

  async function move(id: number, direction: -1 | 1) {
    const index = items.findIndex((i) => i.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= items.length) return

    const next = [...items]
    const tmp = next[index]
    next[index] = next[target]
    next[target] = tmp

    await api('/api/admin/home-sections/reorder', {
      method: 'POST',
      body: JSON.stringify({ ids: next.map((i) => i.id) }),
    })
    await load()
  }

  const columns: ColumnsType<HomeSectionItem> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    {
      title: 'Заголовок',
      dataIndex: 'title',
      render: (title, row) => (
        <Space direction="vertical" size={0}>
          <span>{title}</span>
          {row.category ? <span className="admin-empty-hint">/{row.category.slug}/</span> : null}
        </Space>
      ),
    },
    {
      title: 'Категория',
      key: 'category',
      width: 180,
      render: (_, row) => row.category?.title ?? '—',
    },
    { title: 'Лимит', dataIndex: 'item_limit', width: 70 },
    {
      title: 'Вкладки',
      dataIndex: 'show_tabs',
      width: 90,
      render: (v) => (v ? <Tag color="blue">AJAX</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Сортировка',
      dataIndex: 'default_sort',
      width: 120,
      render: (v) => SORT_OPTIONS.find((o) => o.value === v)?.label ?? v,
    },
    {
      title: 'Статус',
      dataIndex: 'is_active',
      width: 100,
      render: (v) => (v ? <Tag color="green">Показ</Tag> : <Tag>Скрыта</Tag>),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 260,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => move(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => move(row.id, 1)} />
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить секцию?" onConfirm={() => remove(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <p className="admin-empty-hint">
          Блоки на главной: категория, заголовок, лимит карточек и вкладки «Последние / Популярные / По рейтингу».
        </p>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Добавить секцию</Button>
      </div>

      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={false} />

      <Modal
        title={editing ? 'Редактирование секции' : 'Новая секция главной'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        width={560}
      >
        <Form form={form} layout="vertical" onFinish={save}>
          <Form.Item label="Заголовок на главной" name="title" rules={[{ required: true }]}>
            <Input placeholder="Зарубежные сериалы" />
          </Form.Item>
          <Form.Item label="Категория" name="category_id" rules={[{ required: true }]}>
            <Select
              allowClear
              placeholder="Выберите категорию"
              options={categoryOptions}
              onChange={(id) => {
                const cat = categories.find((c) => c.id === id)
                if (cat && !form.getFieldValue('title')) {
                  form.setFieldValue('title', cat.title)
                }
              }}
            />
          </Form.Item>
          <Form.Item label="Карточек в блоке" name="item_limit">
            <InputNumber min={1} max={60} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Сортировка по умолчанию" name="default_sort">
            <Select options={SORT_OPTIONS} />
          </Form.Item>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Вкладки AJAX" name="show_tabs" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Показывать на сайте" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
