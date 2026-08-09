import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons'
import {
  Button,
  Checkbox,
  Col,
  DatePicker,
  Form,
  Input,
  Modal,
  Popconfirm,
  Row,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import dayjs, { type Dayjs } from 'dayjs'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import type { UserItem } from '../types'

const { RangePicker } = DatePicker

const roleLabels: Record<string, string> = {
  user: 'Пользователь',
  admin: 'Администратор',
}

type Filters = {
  name: string
  email: string
  ip: string
  role: 'all' | 'user' | 'admin'
  blocked: 'all' | '0' | '1'
  exactName: boolean
  exactEmail: boolean
  registeredFrom: string
  registeredTo: string
  lastLoginFrom: string
  lastLoginTo: string
}

const defaultFilters: Filters = {
  name: '',
  email: '',
  ip: '',
  role: 'all',
  blocked: 'all',
  exactName: false,
  exactEmail: false,
  registeredFrom: '',
  registeredTo: '',
  lastLoginFrom: '',
  lastLoginTo: '',
}

type UserFormValues = {
  name: string
  email: string
  password?: string
  role: 'user' | 'admin'
  is_blocked: boolean
  registration_ip?: string
  last_ip?: string
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
    second: '2-digit',
  })
}

function buildParams(filters: Filters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.name.trim()) params.set('name', filters.name.trim())
  if (filters.email.trim()) params.set('email', filters.email.trim())
  if (filters.ip.trim()) params.set('ip', filters.ip.trim())
  if (filters.role !== 'all') params.set('role', filters.role)
  if (filters.blocked !== 'all') params.set('blocked', filters.blocked)
  if (filters.exactName) params.set('exact_name', '1')
  if (filters.exactEmail) params.set('exact_email', '1')
  if (filters.registeredFrom) params.set('registered_from', filters.registeredFrom)
  if (filters.registeredTo) params.set('registered_to', filters.registeredTo)
  if (filters.lastLoginFrom) params.set('last_login_from', filters.lastLoginFrom)
  if (filters.lastLoginTo) params.set('last_login_to', filters.lastLoginTo)
  params.set('limit', '200')
  return params
}

function filtersFromSearchParams(params: URLSearchParams): Filters | null {
  const email = params.get('email')?.trim() ?? ''
  const name = params.get('name')?.trim() ?? ''
  if (!email && !name) return null
  return {
    ...defaultFilters,
    email,
    name,
    exactEmail: Boolean(email),
    exactName: Boolean(name) && !email,
  }
}

export default function UsersPage() {
  const [searchParams] = useSearchParams()
  const initialFilters = useMemo(
    () => filtersFromSearchParams(searchParams) ?? defaultFilters,
    // Apply deep-link filters only on first mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  )
  const [items, setItems] = useState<UserItem[]>([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [draft, setDraft] = useState<Filters>(initialFilters)
  const [applied, setApplied] = useState<Filters>(initialFilters)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<UserItem | null>(null)
  const [form] = Form.useForm<UserFormValues>()

  useDocumentTitle(
    modalOpen
      ? editing
        ? `Редактируем пользователя — ${editing.name}`
        : 'Добавить пользователя'
      : null,
  )
  useBusyFavicon(saving)

  const registeredRange = useMemo((): [Dayjs | null, Dayjs | null] | null => {
    if (!draft.registeredFrom && !draft.registeredTo) return null
    return [
      draft.registeredFrom ? dayjs(draft.registeredFrom) : null,
      draft.registeredTo ? dayjs(draft.registeredTo) : null,
    ]
  }, [draft.registeredFrom, draft.registeredTo])

  const lastLoginRange = useMemo((): [Dayjs | null, Dayjs | null] | null => {
    if (!draft.lastLoginFrom && !draft.lastLoginTo) return null
    return [
      draft.lastLoginFrom ? dayjs(draft.lastLoginFrom) : null,
      draft.lastLoginTo ? dayjs(draft.lastLoginTo) : null,
    ]
  }, [draft.lastLoginFrom, draft.lastLoginTo])

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params = buildParams(applied)
      const data = await api<{ items: UserItem[] }>(`/api/admin/users?${params.toString()}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [applied])

  useEffect(() => {
    load()
  }, [load])

  function applyFilters() {
    setApplied({ ...draft })
  }

  function resetFilters() {
    setDraft(defaultFilters)
    setApplied(defaultFilters)
  }

  function openCreate() {
    setEditing(null)
    form.setFieldsValue({
      name: '',
      email: '',
      password: '',
      role: 'user',
      is_blocked: false,
      registration_ip: '',
      last_ip: '',
    })
    setModalOpen(true)
  }

  function openEdit(user: UserItem) {
    setEditing(user)
    form.setFieldsValue({
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
      is_blocked: user.is_blocked,
      registration_ip: user.registration_ip ?? '',
      last_ip: user.last_ip ?? '',
    })
    setModalOpen(true)
  }

  async function saveUser() {
    try {
      const values = await form.validateFields()
      setSaving(true)

      const payload: Record<string, unknown> = {
        name: values.name.trim(),
        email: values.email.trim(),
        role: values.role,
        is_blocked: values.is_blocked,
        registration_ip: values.registration_ip?.trim() || null,
        last_ip: values.last_ip?.trim() || null,
      }

      if (values.password?.trim()) {
        payload.password = values.password
      }

      if (editing) {
        await api(`/api/admin/users/${editing.id}`, {
          method: 'POST',
          body: JSON.stringify(payload),
        })
        message.success('Пользователь обновлён')
      } else {
        if (!values.password?.trim()) {
          message.error('Укажите пароль')
          return
        }
        await api('/api/admin/users', {
          method: 'POST',
          body: JSON.stringify(payload),
        })
        message.success('Пользователь создан')
      }

      setModalOpen(false)
      await load()
    } catch (e) {
      if (e instanceof Error && e.message) {
        message.error(e.message)
      }
    } finally {
      setSaving(false)
    }
  }

  async function removeUser(id: number) {
    try {
      await api(`/api/admin/users/${id}`, { method: 'DELETE' })
      message.success('Пользователь удалён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function quickUpdate(id: number, patch: { role?: string; is_blocked?: boolean }) {
    try {
      await api(`/api/admin/users/${id}`, {
        method: 'POST',
        body: JSON.stringify(patch),
      })
      message.success('Пользователь обновлён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<UserItem> = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    {
      title: 'Логин',
      dataIndex: 'name',
      key: 'name',
      width: 160,
      ellipsis: true,
      render: (value: string, row) => (
        <Space direction="vertical" size={0}>
          <Typography.Text strong>{value}</Typography.Text>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {roleLabels[row.role] ?? row.role}
          </Typography.Text>
        </Space>
      ),
    },
    { title: 'Email', dataIndex: 'email', key: 'email', ellipsis: true },
    {
      title: 'Роль',
      dataIndex: 'role',
      key: 'role',
      width: 160,
      render: (value, row) => (
        <Select
          size="small"
          value={value}
          style={{ width: '100%' }}
          options={[
            { value: 'user', label: roleLabels.user },
            { value: 'admin', label: roleLabels.admin },
          ]}
          onChange={(next) => quickUpdate(row.id, { role: next })}
        />
      ),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 130,
      render: (_, row) => (
        row.is_blocked ? <Tag color="red">Заблокирован</Tag> : <Tag color="green">Активен</Tag>
      ),
    },
    {
      title: 'Блокировка',
      key: 'block_action',
      width: 110,
      render: (_, row) => (
        <Switch
          checked={row.is_blocked}
          checkedChildren="Да"
          unCheckedChildren="Нет"
          onChange={(checked) => quickUpdate(row.id, { is_blocked: checked })}
        />
      ),
    },
    {
      title: 'Последний IP',
      dataIndex: 'last_ip',
      key: 'last_ip',
      width: 130,
      render: (value: string | null) => value || '—',
    },
    {
      title: 'IP регистрации',
      dataIndex: 'registration_ip',
      key: 'registration_ip',
      width: 130,
      render: (value: string | null) => value || '—',
    },
    {
      title: 'Последний вход',
      dataIndex: 'last_login_at',
      key: 'last_login_at',
      width: 170,
      render: (value: string | null) => formatDateTime(value),
    },
    {
      title: 'Регистрация',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 170,
      render: (value: string) => formatDateTime(value),
    },
    {
      title: '',
      key: 'actions',
      width: 96,
      fixed: 'right',
      render: (_, row) => (
        <Space size={0}>
          <Button
            type="text"
            icon={<EditOutlined />}
            aria-label="Редактировать"
            onClick={() => openEdit(row)}
          />
          <Popconfirm
            title="Удалить пользователя?"
            description="Действие необратимо"
            onConfirm={() => removeUser(row.id)}
          >
            <Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <div className="admin-page-card" style={{ marginBottom: 16 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, marginBottom: 12, flexWrap: 'wrap' }}>
          <Typography.Title level={5} style={{ margin: 0 }}>Поиск пользователя</Typography.Title>
          <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
            Добавить пользователя
          </Button>
        </div>

        <Row gutter={[12, 12]}>
          <Col xs={24} md={8} lg={6}>
            <Input
              allowClear
              placeholder="Логин"
              value={draft.name}
              onChange={(e) => setDraft((prev) => ({ ...prev, name: e.target.value }))}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24} md={8} lg={6}>
            <Input
              allowClear
              placeholder="E-mail"
              value={draft.email}
              onChange={(e) => setDraft((prev) => ({ ...prev, email: e.target.value }))}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24} md={8} lg={6}>
            <Input
              allowClear
              placeholder="IP"
              value={draft.ip}
              onChange={(e) => setDraft((prev) => ({ ...prev, ip: e.target.value }))}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24} md={8} lg={6}>
            <Select
              value={draft.role}
              style={{ width: '100%' }}
              onChange={(value) => setDraft((prev) => ({ ...prev, role: value }))}
              options={[
                { value: 'all', label: 'Все роли' },
                { value: 'user', label: 'Пользователи' },
                { value: 'admin', label: 'Администраторы' },
              ]}
            />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <RangePicker
              style={{ width: '100%' }}
              value={registeredRange}
              format="DD.MM.YYYY"
              placeholder={['Регистрация от', 'до']}
              onChange={(values) => {
                setDraft((prev) => ({
                  ...prev,
                  registeredFrom: values?.[0]?.format('YYYY-MM-DD') ?? '',
                  registeredTo: values?.[1]?.format('YYYY-MM-DD') ?? '',
                }))
              }}
            />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <RangePicker
              style={{ width: '100%' }}
              value={lastLoginRange}
              format="DD.MM.YYYY"
              placeholder={['Последний вход от', 'до']}
              onChange={(values) => {
                setDraft((prev) => ({
                  ...prev,
                  lastLoginFrom: values?.[0]?.format('YYYY-MM-DD') ?? '',
                  lastLoginTo: values?.[1]?.format('YYYY-MM-DD') ?? '',
                }))
              }}
            />
          </Col>
          <Col xs={24} md={8} lg={4}>
            <Select
              value={draft.blocked}
              style={{ width: '100%' }}
              onChange={(value) => setDraft((prev) => ({ ...prev, blocked: value }))}
              options={[
                { value: 'all', label: 'Все статусы' },
                { value: '0', label: 'Активные' },
                { value: '1', label: 'Заблокированные' },
              ]}
            />
          </Col>
          <Col xs={24} md={16} lg={12}>
            <Space wrap>
              <Checkbox
                checked={draft.exactName}
                onChange={(e) => setDraft((prev) => ({ ...prev, exactName: e.target.checked }))}
              >
                Точное совпадение логина
              </Checkbox>
              <Checkbox
                checked={draft.exactEmail}
                onChange={(e) => setDraft((prev) => ({ ...prev, exactEmail: e.target.checked }))}
              >
                Точное совпадение email
              </Checkbox>
            </Space>
          </Col>
          <Col xs={24}>
            <Space wrap>
              <Button type="primary" onClick={applyFilters} loading={loading}>
                Найти
              </Button>
              <Button onClick={resetFilters}>Очистить</Button>
              <Button icon={<ReloadOutlined />} onClick={load} loading={loading}>
                Обновить
              </Button>
            </Space>
          </Col>
        </Row>
      </div>

      <div className="admin-page-card">
        <Table
          rowKey="id"
          loading={loading}
          columns={columns}
          dataSource={items}
          pagination={{ pageSize: 25, showSizeChanger: true, pageSizeOptions: ['25', '50', '100'] }}
          scroll={{ x: 1400 }}
        />
      </div>

      <Modal
        title={editing ? `Редактирование: ${editing.name}` : 'Добавить пользователя'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => void saveUser()}
        confirmLoading={saving}
        okText={editing ? 'Сохранить' : 'Создать'}
        destroyOnHidden
        width={560}
      >
        <Form form={form} layout="vertical" style={{ marginTop: 12 }}>
          <Form.Item
            name="name"
            label="Логин"
            rules={[{ required: true, message: 'Укажите логин' }]}
          >
            <Input maxLength={120} />
          </Form.Item>
          <Form.Item
            name="email"
            label="E-mail"
            rules={[
              { required: true, message: 'Укажите email' },
              { type: 'email', message: 'Некорректный email' },
            ]}
          >
            <Input maxLength={255} />
          </Form.Item>
          <Form.Item
            name="password"
            label={editing ? 'Новый пароль' : 'Пароль'}
            rules={editing ? [] : [{ required: true, message: 'Укажите пароль' }]}
            extra={editing ? 'Оставьте пустым, чтобы не менять пароль' : undefined}
          >
            <Input.Password autoComplete="new-password" />
          </Form.Item>
          <Form.Item
            name="role"
            label="Роль"
            rules={[{ required: true, message: 'Выберите роль' }]}
          >
            <Select
              options={[
                { value: 'user', label: roleLabels.user },
                { value: 'admin', label: roleLabels.admin },
              ]}
            />
          </Form.Item>
          <Form.Item name="is_blocked" label="Заблокирован" valuePropName="checked">
            <Switch checkedChildren="Да" unCheckedChildren="Нет" />
          </Form.Item>
          <Row gutter={12}>
            <Col span={12}>
              <Form.Item name="registration_ip" label="IP регистрации">
                <Input placeholder="Необязательно" maxLength={45} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="last_ip" label="Последний IP">
                <Input placeholder="Необязательно" maxLength={45} />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </div>
  )
}
