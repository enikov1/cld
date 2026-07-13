import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  PlusOutlined,
} from '@ant-design/icons'
import {
  Button,
  Card,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Space,
  Switch,
  Table,
  Tag,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'

type ReactionItem = {
  id: number
  emoji: string
  label: string
  sort_order: number
  is_active: boolean
}

export default function ReactionsPage() {
  const [items, setItems] = useState<ReactionItem[]>([])
  const [enabled, setEnabled] = useState(true)
  const [badge, setBadge] = useState('ОЦЕНИТЕ')
  const [title, setTitle] = useState('Как вам этот сериал?')
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<ReactionItem | null>(null)
  const [form] = Form.useForm()

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{
        enabled: boolean
        badge: string
        title: string
        items: ReactionItem[]
      }>('/api/admin/reactions')
      setItems(data.items)
      setEnabled(data.enabled)
      setBadge(data.badge)
      setTitle(data.title)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function saveSettings() {
    try {
      await api('/api/admin/reactions/settings', {
        method: 'POST',
        body: JSON.stringify({ enabled, badge, title }),
      })
      message.success('Настройки виджета сохранены')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({
      is_active: true,
      sort_order: (items.length + 1) * 10,
      emoji: '🙂',
    })
    setModalOpen(true)
  }

  function openEdit(row: ReactionItem) {
    setEditing(row)
    form.setFieldsValue(row)
    setModalOpen(true)
  }

  async function saveItem(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      await api('/api/admin/reactions/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Реакция обновлена' : 'Реакция добавлена')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    await api(`/api/admin/reactions/${id}`, { method: 'DELETE' })
    message.success('Реакция удалена')
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

    await api('/api/admin/reactions/reorder', {
      method: 'POST',
      body: JSON.stringify({ ids: next.map((i) => i.id) }),
    })
    await load()
  }

  const columns: ColumnsType<ReactionItem> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    {
      title: 'Эмодзи',
      dataIndex: 'emoji',
      width: 80,
      render: (v) => <span style={{ fontSize: 28 }}>{v}</span>,
    },
    { title: 'Название кнопки', dataIndex: 'label' },
    {
      title: 'Статус',
      dataIndex: 'is_active',
      width: 100,
      render: (v) => (v ? <Tag color="green">Вкл</Tag> : <Tag>Выкл</Tag>),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 240,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => move(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => move(row.id, 1)} />
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить реакцию?" onConfirm={() => remove(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <Card title="Виджет реакций под плеером" style={{ marginBottom: 16 }}>
        <Space direction="vertical" style={{ width: '100%' }} size="middle">
          <Space wrap align="center">
            <span>Показывать на сайте</span>
            <Switch checked={enabled} onChange={setEnabled} />
          </Space>
          <Input
            addonBefore="Бейдж"
            value={badge}
            onChange={(e) => setBadge(e.target.value)}
            maxLength={40}
          />
          <Input
            addonBefore="Заголовок"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            maxLength={200}
          />
          <Button type="primary" onClick={saveSettings}>Сохранить настройки виджета</Button>
        </Space>
      </Card>

      <div className="admin-toolbar">
        <p className="admin-empty-hint">
          Настройте эмодзи и подписи кнопок. Гости и пользователи голосуют через AJAX без перезагрузки.
        </p>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Добавить реакцию</Button>
      </div>

      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={false} />

      <Modal
        title={editing ? 'Редактирование реакции' : 'Новая реакция'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
      >
        <Form form={form} layout="vertical" onFinish={saveItem}>
          <Form.Item label="Эмодзи" name="emoji" rules={[{ required: true }]}>
            <Input placeholder="👍" maxLength={16} />
          </Form.Item>
          <Form.Item label="Название кнопки" name="label" rules={[{ required: true }]}>
            <Input placeholder="Понравилось" maxLength={120} />
          </Form.Item>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Активна" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
