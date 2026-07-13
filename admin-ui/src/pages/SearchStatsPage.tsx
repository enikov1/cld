import { DeleteOutlined, LinkOutlined } from '@ant-design/icons'
import { Alert, Button, Card, Col, Input, Popconfirm, Row, Space, Statistic, Table, Typography, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { SearchStatItem, SearchStatsResponse, SearchStatsSummary } from '../types'

const emptySummary: SearchStatsSummary = {
  unique_queries: 0,
  total_hits: 0,
  suggest_hits: 0,
  full_hits: 0,
  hits_today: 0,
  hits_week: 0,
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

export default function SearchStatsPage() {
  const [items, setItems] = useState<SearchStatItem[]>([])
  const [summary, setSummary] = useState<SearchStatsSummary>(emptySummary)
  const [ready, setReady] = useState(true)
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      if (search.trim()) params.set('q', search.trim())
      const data = await api<SearchStatsResponse>(`/api/admin/search-stats?${params.toString()}`)
      setReady(data.ready)
      setSummary(data.summary)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [search])

  useEffect(() => {
    load()
  }, [load])

  async function removeItem(id: number) {
    try {
      await api(`/api/admin/search-stats/${id}`, { method: 'DELETE' })
      message.success('Запрос удалён из статистики')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<SearchStatItem> = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    {
      title: 'Запрос',
      dataIndex: 'query',
      key: 'query',
      ellipsis: true,
      render: (value: string) => (
        <a href={`/search?q=${encodeURIComponent(value)}`} target="_blank" rel="noreferrer">
          {value}
        </a>
      ),
    },
    { title: 'Всего', dataIndex: 'hits', key: 'hits', width: 90, sorter: (a, b) => a.hits - b.hits },
    { title: 'Быстрый', dataIndex: 'suggest_hits', key: 'suggest_hits', width: 100 },
    { title: 'Полный', dataIndex: 'full_hits', key: 'full_hits', width: 90 },
    {
      title: 'Последний раз',
      dataIndex: 'last_searched_at',
      key: 'last_searched_at',
      width: 160,
      render: (value: string) => formatDateTime(value),
    },
    {
      title: 'Первый раз',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 160,
      render: (value: string) => formatDateTime(value),
    },
    {
      title: '',
      key: 'actions',
      width: 70,
      render: (_, row) => (
        <Popconfirm title="Удалить запрос из статистики?" onConfirm={() => removeItem(row.id)}>
          <Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
        </Popconfirm>
      ),
    },
  ]

  return (
    <div>
      {!ready ? (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message="Таблица статистики поиска ещё не создана"
          description="Выполните миграцию в папке site: php artisan migrate"
        />
      ) : null}

      <Typography.Paragraph type="secondary" style={{ marginBottom: 20 }}>
        Учитываются только успешные запросы — те, по которым на сайте что-то найдено. Быстрый поиск и страница полного поиска
        увеличивают счётчик не чаще одного раза за сессию для каждого запроса.
      </Typography.Paragraph>

      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} lg={8}>
          <Card loading={loading}>
            <Statistic title="Уникальных запросов" value={summary.unique_queries} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={8}>
          <Card loading={loading}>
            <Statistic title="Всего обращений" value={summary.total_hits} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={8}>
          <Card loading={loading}>
            <Statistic
              title="За сегодня"
              value={summary.hits_today}
              suffix={<Typography.Text type="secondary">/ неделя {summary.hits_week}</Typography.Text>}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={8}>
          <Card loading={loading}>
            <Statistic title="Из быстрого поиска" value={summary.suggest_hits} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={8}>
          <Card loading={loading}>
            <Statistic title="Из полного поиска" value={summary.full_hits} />
          </Card>
        </Col>
      </Row>

      <div className="admin-page-card">
        <div className="admin-toolbar">
          <Space wrap>
            <Input.Search
              allowClear
              placeholder="Фильтр по запросу"
              style={{ width: 320 }}
              onSearch={setSearch}
            />
            <Button icon={<LinkOutlined />} href="/search" target="_blank">
              Открыть поиск на сайте
            </Button>
          </Space>
        </div>

        <Table
          rowKey="id"
          loading={loading}
          columns={columns}
          dataSource={items}
          pagination={{ pageSize: 50, showSizeChanger: false }}
          scroll={{ x: 960 }}
        />
      </div>
    </div>
  )
}
