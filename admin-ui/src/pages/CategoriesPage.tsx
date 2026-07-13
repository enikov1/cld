import { Button, Form, Input, InputNumber, Modal, Popconfirm, Space, Switch, Table, Tag, Typography, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { CategoryItem } from '../types'

export default function CategoriesPage() {
  const { isDark } = useAdminTheme()
  const [items, setItems] = useState<CategoryItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CategoryItem | null>(null)
  const [form] = Form.useForm()

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ items: CategoryItem[] }>('/api/admin/categories')
      setItems(data.items)
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
    form.setFieldsValue({ is_active: true, is_hidden: false, noindex: false, sort_order: 0, seo_html: '' })
    setModalOpen(true)
  }

  function openEdit(row: CategoryItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
    })
    setModalOpen(true)
  }

  async function save(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      await api('/api/admin/categories/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Категория обновлена' : 'Категория создана')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(slug: string) {
    try {
      await api(`/api/admin/categories/${slug}`, { method: 'DELETE' })
      message.success('Категория удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<CategoryItem> = [
    { title: 'Slug (URL)', dataIndex: 'slug', key: 'slug', width: 200 },
    { title: 'Название', dataIndex: 'title', key: 'title' },
    { title: 'Порядок', dataIndex: 'sort_order', key: 'sort_order', width: 90 },
    {
      title: 'SEO-блок',
      dataIndex: 'seo_html',
      key: 'seo_html',
      width: 100,
      render: (v) => (v?.trim() ? <Tag color="blue">Есть</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 150,
      render: (_, row) => (
        <Space size={4} wrap>
          {row.is_active ? <Tag color="green">Активна</Tag> : <Tag>Выключена</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыта</Tag> : null}
          {row.noindex ? <Tag>noindex</Tag> : null}
        </Space>
      ),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 180,
      render: (_, row) => (
        <Space>
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить категорию?" onConfirm={() => remove(row.slug)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <p className="admin-empty-hint">Slug используется в URL: /{'{slug}'}/</p>
        <Button type="primary" onClick={openCreate}>Добавить категорию</Button>
      </div>

      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={{ pageSize: 15 }} />

      <Modal
        title={editing ? 'Редактировать категорию' : 'Новая категория'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Form form={form} layout="vertical" onFinish={save}>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={editing ? 'Slug нельзя изменить после создания' : 'Если пусто — будет создан автоматически из названия'}
          >
            <Input placeholder="zarubezhnye-serialy" disabled={!!editing} />
          </Form.Item>
          <Form.Item label="Название" name="title" rules={[{ required: true, message: 'Укажите название' }]}>
            <Input placeholder="Зарубежные сериалы" />
          </Form.Item>
          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — «{название} — смотреть онлайн бесплатно»">
            <Input />
          </Form.Item>
          <Form.Item label="Meta description" name="description" extra="Краткое описание категории (meta description и подзаголовок)">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item
            label="SEO-блок (HTML)"
            name="seo_html"
            extra="Выводится внизу страницы категории, как SEO-текст на главной. Поддерживается HTML."
          >
            <TemplateCodeEditor filePath="category-seo.html" isDark={isDark} height="260px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            Редактор с подсветкой HTML: номера строк, сворачивание блоков, автодополнение тегов.
          </Typography.Paragraph>
          <Form.Item label="Порядок сортировки" name="sort_order">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Активна" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Скрыть страницу" name="is_hidden" valuePropName="checked" extra="Страница недоступна посетителям (404)">
            <Switch />
          </Form.Item>
          <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="Добавляет meta robots noindex">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
