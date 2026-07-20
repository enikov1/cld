import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  DeleteOutlined,
  LinkOutlined,
  ReloadOutlined,
} from '@ant-design/icons'
import {
  Alert,
  Button,
  Card,
  Col,
  DatePicker,
  Input,
  Popconfirm,
  Progress,
  Row,
  Select,
  Space,
  Statistic,
  Table,
  Tabs,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import dayjs, { type Dayjs } from 'dayjs'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import type {
  SearchLogItem,
  SearchStatItem,
  SearchStatsResponse,
  SearchStatsSummary,
  SearchTopQuery,
} from '../types'

const { RangePicker } = DatePicker

const emptySummary: SearchStatsSummary = {
  unique_queries: 0,
  total_hits: 0,
  suggest_hits: 0,
  full_hits: 0,
  hits_today: 0,
  hits_week: 0,
  total_events: 0,
  found_events: 0,
  not_found_events: 0,
  log_unique_queries: 0,
  suggest_events: 0,
  full_events: 0,
  events_today: 0,
  events_week: 0,
}

type Filters = {
  q: string
  dateFrom: string
  dateTo: string
  found: '' | '1' | '0'
  source: '' | 'suggest' | 'full'
  ip: string
}

const defaultFilters: Filters = {
  q: '',
  dateFrom: '',
  dateTo: '',
  found: '',
  source: '',
  ip: '',
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

function buildParams(filters: Filters, view: 'log' | 'aggregated'): URLSearchParams {
  const params = new URLSearchParams()
  params.set('view', view)
  if (filters.q.trim()) params.set('q', filters.q.trim())
  if (filters.dateFrom) params.set('date_from', filters.dateFrom)
  if (filters.dateTo) params.set('date_to', filters.dateTo)
  if (filters.found) params.set('found', filters.found)
  if (filters.source) params.set('source', filters.source)
  if (filters.ip.trim()) params.set('ip', filters.ip.trim())
  return params
}

function TopQueriesCard({
  title,
  items,
  loading,
}: {
  title: string
  items: SearchTopQuery[]
  loading: boolean
}) {
  return (
    <Card title={title} loading={loading} style={{ height: '100%' }}>
      {items.length === 0 ? (
        <Typography.Text type="secondary">Нет данных за выбранный период</Typography.Text>
      ) : (
        <Space direction="vertical" size={12} style={{ width: '100%' }}>
          {items.map((item) => (
            <div key={item.query}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, marginBottom: 4 }}>
                <Typography.Text ellipsis style={{ maxWidth: '70%' }}>
                  <a href={`/search?q=${encodeURIComponent(item.query)}`} target="_blank" rel="noreferrer">
                    {item.query}
                  </a>
                </Typography.Text>
                <Typography.Text type="secondary">{item.count} ({item.share}%)</Typography.Text>
              </div>
              <Progress
                percent={item.share}
                showInfo={false}
                size="small"
                strokeColor={item.not_found_count > 0 ? '#faad14' : '#1677ff'}
              />
              <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                найдено {item.found_count}, не найдено {item.not_found_count}
              </Typography.Text>
            </div>
          ))}
        </Space>
      )}
    </Card>
  )
}

export default function SearchStatsPage() {
  const [items, setItems] = useState<SearchLogItem[]>([])
  const [aggregated, setAggregated] = useState<SearchStatItem[]>([])
  const [topQueries, setTopQueries] = useState<SearchTopQuery[]>([])
  const [summary, setSummary] = useState<SearchStatsSummary>(emptySummary)
  const [ready, setReady] = useState(true)
  const [logsReady, setLogsReady] = useState(false)
  const [loading, setLoading] = useState(false)
  const [view, setView] = useState<'log' | 'aggregated'>('log')
  const [draft, setDraft] = useState<Filters>(defaultFilters)
  const [applied, setApplied] = useState<Filters>(defaultFilters)

  const dateRangeValue = useMemo((): [Dayjs | null, Dayjs | null] | null => {
    if (!draft.dateFrom && !draft.dateTo) return null
    return [
      draft.dateFrom ? dayjs(draft.dateFrom) : null,
      draft.dateTo ? dayjs(draft.dateTo) : null,
    ]
  }, [draft.dateFrom, draft.dateTo])

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params = buildParams(applied, view)
      const data = await api<SearchStatsResponse>(`/api/admin/search-stats?${params.toString()}`)
      setReady(data.ready)
      setLogsReady(data.logs_ready)
      setSummary(data.summary)
      setTopQueries(data.top_queries)
      setItems(data.items)
      setAggregated(data.aggregated)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [applied, view])

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

  async function removeLogItem(id: number) {
    try {
      await api(`/api/admin/search-stats/logs/${id}`, { method: 'DELETE' })
      message.success('Запись удалена из журнала')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeAggregatedItem(id: number) {
    try {
      await api(`/api/admin/search-stats/${id}`, { method: 'DELETE' })
      message.success('Запрос удалён из сводки')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function clearStats(scope: 'logs' | 'aggregated' | 'all') {
    try {
      await api('/api/admin/search-stats/clear', {
        method: 'POST',
        body: JSON.stringify({ scope }),
      })
      message.success('Статистика очищена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const foundPercent = summary.total_events > 0
    ? Math.round((summary.found_events / summary.total_events) * 100)
    : 0

  const logColumns: ColumnsType<SearchLogItem> = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    {
      title: 'Дата',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 150,
      render: (value: string) => formatDateTime(value),
    },
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
    {
      title: 'Источник',
      dataIndex: 'source',
      key: 'source',
      width: 110,
      render: (value: SearchLogItem['source']) => (
        <Tag color={value === 'suggest' ? 'blue' : 'purple'}>
          {value === 'suggest' ? 'Быстрый' : 'Полный'}
        </Tag>
      ),
    },
    {
      title: 'Результат',
      dataIndex: 'found',
      key: 'found',
      width: 120,
      render: (value: boolean, row) => (
        value ? (
          <Tag icon={<CheckCircleOutlined />} color="success">
            Найдено ({row.results_count})
          </Tag>
        ) : (
          <Tag icon={<CloseCircleOutlined />} color="error">
            Не найдено
          </Tag>
        )
      ),
    },
    {
      title: 'IP',
      dataIndex: 'ip',
      key: 'ip',
      width: 130,
      render: (value: string | null) => value || '—',
    },
    {
      title: '',
      key: 'actions',
      width: 56,
      fixed: 'right',
      render: (_, row) => (
        <Popconfirm title="Удалить запись из журнала?" onConfirm={() => removeLogItem(row.id)}>
          <Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
        </Popconfirm>
      ),
    },
  ]

  const aggregatedColumns: ColumnsType<SearchStatItem> = [
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
    { title: 'Всего', dataIndex: 'hits', key: 'hits', width: 90 },
    { title: 'Быстрый', dataIndex: 'suggest_hits', key: 'suggest_hits', width: 100 },
    { title: 'Полный', dataIndex: 'full_hits', key: 'full_hits', width: 90 },
    {
      title: 'Последний раз',
      dataIndex: 'last_searched_at',
      key: 'last_searched_at',
      width: 150,
      render: (value: string) => formatDateTime(value),
    },
    {
      title: 'Первый раз',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 150,
      render: (value: string) => formatDateTime(value),
    },
    {
      title: '',
      key: 'actions',
      width: 56,
      fixed: 'right',
      render: (_, row) => (
        <Popconfirm title="Удалить запрос из сводки?" onConfirm={() => removeAggregatedItem(row.id)}>
          <Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
        </Popconfirm>
      ),
    },
  ]

  return (
    <div>
      {!ready && !logsReady ? (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message="Таблицы статистики поиска ещё не созданы"
          description="Выполните миграцию в папке site: php artisan migrate"
        />
      ) : null}

      {!logsReady ? (
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Журнал поиска ещё не создан"
          description="Выполните миграцию для расширенной статистики (найдено / не найдено). Пока доступна только сводка успешных запросов."
        />
      ) : null}

      <Typography.Paragraph type="secondary" style={{ marginBottom: 20 }}>
        Учитываются все поисковые обращения: успешные и без результатов. Быстрый поиск и страница полного поиска
        фиксируются не чаще одного раза за сессию для каждого запроса и источника.
      </Typography.Paragraph>

      <div className="admin-page-card" style={{ marginBottom: 16 }}>
        <Typography.Title level={5} style={{ marginTop: 0 }}>Фильтры</Typography.Title>
        <Row gutter={[12, 12]}>
          <Col xs={24} md={12} lg={8}>
            <Input
              allowClear
              placeholder="Запрос"
              value={draft.q}
              onChange={(e) => setDraft((prev) => ({ ...prev, q: e.target.value }))}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <RangePicker
              style={{ width: '100%' }}
              value={dateRangeValue}
              onChange={(values) => {
                setDraft((prev) => ({
                  ...prev,
                  dateFrom: values?.[0]?.format('YYYY-MM-DD') ?? '',
                  dateTo: values?.[1]?.format('YYYY-MM-DD') ?? '',
                }))
              }}
              format="DD.MM.YYYY"
              placeholder={['Дата от', 'Дата до']}
            />
          </Col>
          <Col xs={24} md={12} lg={4}>
            <Select
              allowClear
              placeholder="Результат"
              style={{ width: '100%' }}
              value={draft.found || undefined}
              onChange={(value) => setDraft((prev) => ({ ...prev, found: (value ?? '') as Filters['found'] }))}
              options={[
                { value: '1', label: 'Только найденные' },
                { value: '0', label: 'Только не найденные' },
              ]}
            />
          </Col>
          <Col xs={24} md={12} lg={4}>
            <Select
              allowClear
              placeholder="Источник"
              style={{ width: '100%' }}
              value={draft.source || undefined}
              onChange={(value) => setDraft((prev) => ({ ...prev, source: (value ?? '') as Filters['source'] }))}
              options={[
                { value: 'suggest', label: 'Быстрый поиск' },
                { value: 'full', label: 'Полный поиск' },
              ]}
            />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Input
              allowClear
              placeholder="IP-адрес"
              value={draft.ip}
              onChange={(e) => setDraft((prev) => ({ ...prev, ip: e.target.value }))}
              onPressEnter={applyFilters}
            />
          </Col>
          <Col xs={24}>
            <Space wrap>
              <Button type="primary" onClick={applyFilters} loading={loading}>
                Применить
              </Button>
              <Button onClick={resetFilters}>Сбросить</Button>
              <Button icon={<ReloadOutlined />} onClick={load} loading={loading}>
                Обновить
              </Button>
              <Button icon={<LinkOutlined />} href="/search" target="_blank">
                Открыть поиск на сайте
              </Button>
            </Space>
          </Col>
        </Row>
      </div>

      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Всего обращений" value={summary.total_events} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Найдено" value={summary.found_events} valueStyle={{ color: '#389e0d' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Не найдено" value={summary.not_found_events} valueStyle={{ color: '#cf1322' }} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Уникальных запросов" value={summary.log_unique_queries || summary.unique_queries} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic
              title="За сегодня"
              value={summary.events_today || summary.hits_today}
              suffix={<Typography.Text type="secondary">/ неделя {summary.events_week || summary.hits_week}</Typography.Text>}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Быстрый поиск" value={summary.suggest_events || summary.suggest_hits} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Полный поиск" value={summary.full_events || summary.full_hits} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Доля найденных" value={foundPercent} suffix="%" />
            <Progress percent={foundPercent} showInfo={false} size="small" style={{ marginTop: 8 }} />
          </Card>
        </Col>
      </Row>

      {logsReady ? (
        <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
          <Col xs={24} lg={12}>
            <TopQueriesCard title="Топ запросов за период" items={topQueries} loading={loading} />
          </Col>
          <Col xs={24} lg={12}>
            <Card title="Успешные запросы (сводка)" loading={loading} style={{ height: '100%' }}>
              <Space direction="vertical" size={8}>
                <Typography.Text>
                  Уникальных успешных запросов: <strong>{summary.unique_queries}</strong>
                </Typography.Text>
                <Typography.Text>
                  Всего успешных обращений: <strong>{summary.total_hits}</strong>
                </Typography.Text>
                <Typography.Text type="secondary">
                  Сводка по успешным запросам ведётся отдельно и используется для блока «Популярные поиски» на сайте.
                </Typography.Text>
              </Space>
            </Card>
          </Col>
        </Row>
      ) : null}

      <div className="admin-page-card">
        <div className="admin-toolbar" style={{ marginBottom: 12 }}>
          <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Tabs
              activeKey={view}
              onChange={(key) => setView(key as 'log' | 'aggregated')}
              items={[
                { key: 'log', label: 'Журнал событий', disabled: !logsReady },
                { key: 'aggregated', label: 'Успешные запросы' },
              ]}
            />
            <Space wrap>
              {view === 'log' && logsReady ? (
                <Popconfirm
                  title="Очистить весь журнал поиска?"
                  onConfirm={() => clearStats('logs')}
                >
                  <Button danger>Очистить журнал</Button>
                </Popconfirm>
              ) : null}
              {view === 'aggregated' ? (
                <Popconfirm
                  title="Очистить сводку успешных запросов?"
                  onConfirm={() => clearStats('aggregated')}
                >
                  <Button danger>Очистить сводку</Button>
                </Popconfirm>
              ) : null}
            </Space>
          </Space>
        </div>

        {view === 'log' ? (
          <Table
            rowKey="id"
            loading={loading}
            columns={logColumns}
            dataSource={items}
            pagination={{ pageSize: 50, showSizeChanger: true, pageSizeOptions: ['25', '50', '100'] }}
            scroll={{ x: 980 }}
          />
        ) : (
          <Table
            rowKey="id"
            loading={loading}
            columns={aggregatedColumns}
            dataSource={aggregated}
            pagination={{ pageSize: 50, showSizeChanger: true, pageSizeOptions: ['25', '50', '100'] }}
            scroll={{ x: 960 }}
          />
        )}
      </div>
    </div>
  )
}
