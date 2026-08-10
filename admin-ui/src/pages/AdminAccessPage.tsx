import {
  CopyOutlined,
  DeleteOutlined,
  EditOutlined,
  KeyOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons'
import {
  Alert,
  Button,
  Checkbox,
  Divider,
  Drawer,
  Form,
  Input,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { useDocumentTitle } from '../documentMeta/AdminDocumentMeta'

type AbilityItem = {
  key: string
  label: string
  group: string
  pages: string[]
}

type AdminTokenItem = {
  id: number
  name: string
  role: 'full' | 'content' | 'moderation' | 'custom'
  abilities: string[]
  is_active: boolean
  last_used_at?: string | null
  created_at?: string | null
}

type TokenMeta = {
  catalog: AbilityItem[]
  presets: Record<string, string[]>
  roles: Array<{ value: string; label: string }>
}

type TokenFormValues = {
  name: string
  preset: string
  abilities: string[]
  is_active: boolean
}

const ROLE_LABELS: Record<string, string> = {
  full: 'Полный',
  content: 'Контент',
  moderation: 'Модерация',
  custom: 'Свой',
}

const ROLE_COLORS: Record<string, string> = {
  full: 'gold',
  content: 'blue',
  moderation: 'purple',
  custom: 'cyan',
}

function formatDate(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleString('ru-RU')
}

function abilitiesEqual(a: string[], b: string[]): boolean {
  const left = [...a].sort()
  const right = [...b].sort()
  if (left.length !== right.length) return false
  return left.every((v, i) => v === right[i])
}

export default function AdminAccessPage() {
  const [items, setItems] = useState<AdminTokenItem[]>([])
  const [meta, setMeta] = useState<TokenMeta | null>(null)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [editing, setEditing] = useState<AdminTokenItem | null>(null)
  const [createdToken, setCreatedToken] = useState<string | null>(null)
  const [form] = Form.useForm<TokenFormValues>()
  const watchedAbilities = Form.useWatch('abilities', form) || []
  const watchedPreset = Form.useWatch('preset', form)

  useDocumentTitle('Токены доступа')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [listData, metaData] = await Promise.all([
        api<{ items: AdminTokenItem[] }>('/api/admin/admin-tokens'),
        api<TokenMeta>('/api/admin/admin-tokens/meta'),
      ])
      setItems(listData.items)
      setMeta(metaData)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const groupedCatalog = useMemo(() => {
    const groups = new Map<string, AbilityItem[]>()
    for (const item of meta?.catalog ?? []) {
      const list = groups.get(item.group) ?? []
      list.push(item)
      groups.set(item.group, list)
    }
    return [...groups.entries()]
  }, [meta])

  const detectPreset = useCallback(
    (abilities: string[]): string => {
      if (!meta) return 'custom'
      if (abilities.includes('*') || abilitiesEqual(abilities, meta.presets.full || ['*'])) return 'full'
      if (abilitiesEqual(abilities, meta.presets.content || [])) return 'content'
      if (abilitiesEqual(abilities, meta.presets.moderation || [])) return 'moderation'
      return 'custom'
    },
    [meta],
  )

  function openCreate() {
    setEditing(null)
    form.setFieldsValue({
      name: '',
      preset: 'content',
      abilities: meta?.presets.content ? [...meta.presets.content] : [],
      is_active: true,
    })
    setDrawerOpen(true)
  }

  function openEdit(row: AdminTokenItem) {
    setEditing(row)
    form.setFieldsValue({
      name: row.name,
      preset: detectPreset(row.abilities),
      abilities: row.abilities.includes('*')
        ? ['*']
        : [...row.abilities],
      is_active: row.is_active,
    })
    setDrawerOpen(true)
  }

  function applyPreset(preset: string) {
    if (!meta) return
    if (preset === 'full') {
      form.setFieldsValue({ abilities: ['*'], preset: 'full' })
      return
    }
    if (preset === 'custom') {
      form.setFieldsValue({ preset: 'custom' })
      return
    }
    form.setFieldsValue({
      preset,
      abilities: [...(meta.presets[preset] || [])],
    })
  }

  function onAbilitiesChange(next: string[]) {
    let abilities = next
    if (next.includes('*')) {
      abilities = ['*']
    }
    form.setFieldsValue({
      abilities,
      preset: detectPreset(abilities),
    })
  }

  async function onSubmit(values: TokenFormValues) {
    setSaving(true)
    try {
      const payload = {
        name: values.name.trim(),
        abilities: values.abilities.includes('*') ? ['*'] : values.abilities,
        is_active: values.is_active,
      }

      if (editing) {
        await api(`/api/admin/admin-tokens/${editing.id}`, {
          method: 'PUT',
          body: JSON.stringify(payload),
        })
        message.success('Токен обновлён')
        setDrawerOpen(false)
      } else {
        const res = await api<{ ok: boolean; token: string; item: AdminTokenItem }>('/api/admin/admin-tokens', {
          method: 'POST',
          body: JSON.stringify(payload),
        })
        setCreatedToken(res.token)
        setDrawerOpen(false)
        message.success('Токен создан — скопируйте секрет сейчас')
      }
      form.resetFields()
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  async function onDelete(id: number) {
    try {
      await api(`/api/admin/admin-tokens/${id}`, { method: 'DELETE' })
      message.success('Токен удалён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function onToggleActive(row: AdminTokenItem, active: boolean) {
    try {
      await api(`/api/admin/admin-tokens/${row.id}`, {
        method: 'PUT',
        body: JSON.stringify({ is_active: active }),
      })
      message.success(active ? 'Токен включён' : 'Токен отключён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function onRegenerate(row: AdminTokenItem) {
    try {
      const res = await api<{ token: string }>(`/api/admin/admin-tokens/${row.id}/regenerate`, {
        method: 'POST',
      })
      setCreatedToken(res.token)
      message.success('Новый секрет создан — скопируйте его')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<AdminTokenItem> = [
    { title: 'Имя', dataIndex: 'name', key: 'name' },
    {
      title: 'Роль',
      dataIndex: 'role',
      key: 'role',
      width: 120,
      render: (role: string) => <Tag color={ROLE_COLORS[role] || 'default'}>{ROLE_LABELS[role] || role}</Tag>,
    },
    {
      title: 'Права',
      dataIndex: 'abilities',
      key: 'abilities',
      render: (abilities: string[]) => {
        if (abilities.includes('*')) return <Typography.Text type="secondary">все разделы</Typography.Text>
        return (
          <Typography.Text type="secondary">
            {abilities.length} {abilities.length === 1 ? 'право' : abilities.length < 5 ? 'права' : 'прав'}
          </Typography.Text>
        )
      },
    },
    {
      title: 'Статус',
      dataIndex: 'is_active',
      key: 'is_active',
      width: 110,
      render: (active: boolean, row) => (
        <Switch
          checked={active}
          checkedChildren="Вкл"
          unCheckedChildren="Выкл"
          onChange={(checked) => void onToggleActive(row, checked)}
        />
      ),
    },
    {
      title: 'Последнее использование',
      dataIndex: 'last_used_at',
      key: 'last_used_at',
      render: formatDate,
    },
    {
      title: 'Создан',
      dataIndex: 'created_at',
      key: 'created_at',
      render: formatDate,
    },
    {
      title: '',
      key: 'actions',
      width: 140,
      render: (_, row) => (
        <Space>
          <Button type="text" icon={<EditOutlined />} aria-label="Редактировать" onClick={() => openEdit(row)} />
          <Popconfirm
            title="Перевыпустить секрет?"
            description="Старый токен сразу перестанет работать."
            onConfirm={() => void onRegenerate(row)}
          >
            <Button type="text" icon={<KeyOutlined />} aria-label="Перевыпустить" />
          </Popconfirm>
          <Popconfirm title="Удалить токен?" onConfirm={() => void onDelete(row.id)}>
            <Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
          </Popconfirm>
        </Space>
      ),
    },
  ]

  const isFullAccess = watchedAbilities.includes('*')

  return (
    <div>
      <Space style={{ marginBottom: 16 }} wrap>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
          Создать токен
        </Button>
        <Button icon={<ReloadOutlined />} onClick={() => void load()} loading={loading}>
          Обновить
        </Button>
      </Space>

      <Alert
        type="info"
        showIcon
        style={{ marginBottom: 16 }}
        message="ADMIN_TOKEN из .env всегда имеет полный доступ. Ниже — дополнительные токены: можно выбрать пресет или отметить конкретные разделы. Секрет показывается только при создании или перевыпуске."
      />

      <Table
        rowKey="id"
        loading={loading}
        columns={columns}
        dataSource={items}
        pagination={false}
        size="middle"
      />

      <Drawer
        title={editing ? `Редактирование: ${editing.name}` : 'Новый токен'}
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        width={520}
        destroyOnHidden
        extra={
          <Space>
            <Button onClick={() => setDrawerOpen(false)}>Отмена</Button>
            <Button type="primary" loading={saving} onClick={() => form.submit()}>
              {editing ? 'Сохранить' : 'Создать'}
            </Button>
          </Space>
        }
      >
        <Form form={form} layout="vertical" onFinish={onSubmit}>
          <Form.Item name="name" label="Название" rules={[{ required: true, message: 'Укажите имя' }]}>
            <Input placeholder="Например: Редактор сериалов" maxLength={120} />
          </Form.Item>

          <Form.Item name="preset" label="Пресет">
            <Select
              options={[
                { value: 'full', label: 'Полный доступ — всё' },
                { value: 'content', label: 'Контент — сериалы, подборки, шаблоны, sync…' },
                { value: 'moderation', label: 'Модерация — комментарии, жалобы, пользователи' },
                { value: 'custom', label: 'Свой набор — отметьте права ниже' },
              ]}
              onChange={(value) => applyPreset(String(value))}
            />
          </Form.Item>

          <Form.Item name="is_active" label="Активен" valuePropName="checked">
            <Switch />
          </Form.Item>

          <Divider style={{ margin: '12px 0' }}>Права доступа</Divider>

          <Form.Item
            name="abilities"
            rules={[
              {
                validator: async (_, value: string[]) => {
                  if (!value || value.length === 0) {
                    throw new Error('Выберите хотя бы одно право')
                  }
                },
              },
            ]}
          >
            <Checkbox.Group style={{ width: '100%' }} onChange={(vals) => onAbilitiesChange(vals as string[])}>
              <Space direction="vertical" style={{ width: '100%' }} size={16}>
                <Checkbox value="*" disabled={watchedPreset === 'full' && isFullAccess && watchedAbilities.length === 1}>
                  <Typography.Text strong>Полный доступ ко всем разделам</Typography.Text>
                </Checkbox>

                {groupedCatalog.map(([group, rows]) => (
                  <div key={group}>
                    <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 8 }}>
                      {group}
                    </Typography.Text>
                    <Space direction="vertical" style={{ width: '100%' }}>
                      {rows.map((row) => (
                        <Checkbox key={row.key} value={row.key} disabled={isFullAccess}>
                          {row.label}
                        </Checkbox>
                      ))}
                    </Space>
                  </div>
                ))}
              </Space>
            </Checkbox.Group>
          </Form.Item>
        </Form>
      </Drawer>

      <Modal
        title="Секрет токена"
        open={!!createdToken}
        onCancel={() => setCreatedToken(null)}
        footer={[
          <Button
            key="copy"
            icon={<CopyOutlined />}
            onClick={async () => {
              if (!createdToken) return
              await navigator.clipboard.writeText(createdToken)
              message.success('Скопировано')
            }}
          >
            Копировать
          </Button>,
          <Button key="ok" type="primary" onClick={() => setCreatedToken(null)}>
            Готово
          </Button>,
        ]}
      >
        <Typography.Paragraph type="secondary">
          Сохраните токен сейчас — повторно посмотреть его нельзя. Старый секрет (при перевыпуске) больше не действует.
        </Typography.Paragraph>
        <Input.TextArea
          value={createdToken ?? ''}
          readOnly
          autoSize={{ minRows: 3, maxRows: 4 }}
          style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace', marginBottom: 8 }}
        />
        <Typography.Text type="secondary" copyable={createdToken ? { text: createdToken } : false}>
          Длина: {createdToken?.length ?? 0} символов
        </Typography.Text>
      </Modal>
    </div>
  )
}
