import { Input, Select, Space, Switch, Table, Tag, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { UserItem } from '../types'

const roleLabels: Record<string, string> = {
  user: 'Пользователь',
  admin: 'Администратор',
}

export default function UsersPage() {
  const [items, setItems] = useState<UserItem[]>([])
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('all')
  const [blocked, setBlocked] = useState('all')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      if (search.trim()) params.set('q', search.trim())
      if (role !== 'all') params.set('role', role)
      if (blocked !== 'all') params.set('blocked', blocked)
      const data = await api<{ items: UserItem[] }>(`/api/admin/users?${params.toString()}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [search, role, blocked])

  useEffect(() => {
    load()
  }, [load])

  async function updateUser(id: number, patch: { role?: string; is_blocked?: boolean }) {
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
    { title: 'Имя', dataIndex: 'name', key: 'name', width: 160, ellipsis: true },
    { title: 'Email', dataIndex: 'email', key: 'email', ellipsis: true },
    {
      title: 'Роль',
      dataIndex: 'role',
      key: 'role',
      width: 180,
      render: (value, row) => (
        <Select
          size="small"
          value={value}
          style={{ width: '100%' }}
          options={[
            { value: 'user', label: roleLabels.user },
            { value: 'admin', label: roleLabels.admin },
          ]}
          onChange={(next) => updateUser(row.id, { role: next })}
        />
      ),
    },
    {
      title: 'Статус',
      key: 'blocked',
      width: 140,
      render: (_, row) => (
        row.is_blocked ? <Tag color="red">Заблокирован</Tag> : <Tag color="green">Активен</Tag>
      ),
    },
    {
      title: 'Блокировка',
      key: 'block_action',
      width: 120,
      render: (_, row) => (
        <Switch
          checked={row.is_blocked}
          checkedChildren="Да"
          unCheckedChildren="Нет"
          onChange={(checked) => updateUser(row.id, { is_blocked: checked })}
        />
      ),
    },
    { title: 'Регистрация', dataIndex: 'created_at', key: 'created_at', width: 170 },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Space wrap>
          <Input.Search
            allowClear
            placeholder="Поиск по имени или email"
            style={{ width: 280 }}
            onSearch={setSearch}
          />
          <Select
            value={role}
            style={{ width: 180 }}
            onChange={setRole}
            options={[
              { value: 'all', label: 'Все роли' },
              { value: 'user', label: 'Пользователи' },
              { value: 'admin', label: 'Администраторы' },
            ]}
          />
          <Select
            value={blocked}
            style={{ width: 180 }}
            onChange={setBlocked}
            options={[
              { value: 'all', label: 'Все статусы' },
              { value: '0', label: 'Активные' },
              { value: '1', label: 'Заблокированные' },
            ]}
          />
        </Space>
      </div>
      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={{ pageSize: 20 }} />
    </div>
  )
}
