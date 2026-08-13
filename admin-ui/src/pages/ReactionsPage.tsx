import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  LinkOutlined,
  PlusOutlined,
  ReloadOutlined,
} from '@ant-design/icons'
import {
  Button,
  Card,
  Col,
  DatePicker,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Progress,
  Row,
  Segmented,
  Select,
  Space,
  Statistic,
  Switch,
  Table,
  Tabs,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import dayjs, { type Dayjs } from 'dayjs'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { ADMIN_ROUTES } from '../routes/adminRoutes'
import type { ReactionStatsResponse, ReactionStatsTopSeries, ReactionStatsType } from '../types'
import { resolveMediaUrl, siteOrigin } from '../utils/mediaUrl'
import { seriesPublicPath } from '../utils/seriesPublicPath'

type ReactionItem = {
  id: number
  emoji: string
  label: string
  sort_order: number
  is_active: boolean
}

type PeriodKey = 'today' | 'yesterday' | '7d' | '30d' | '90d' | '365d' | 'all' | 'custom'
type GroupKey = 'day' | 'week' | 'month'

const { RangePicker } = DatePicker

const PERIOD_OPTIONS: { value: PeriodKey; label: string }[] = [
  { value: 'today', label: 'Сегодня' },
  { value: 'yesterday', label: 'Вчера' },
  { value: '7d', label: '7 дней' },
  { value: '30d', label: '30 дней' },
  { value: '90d', label: '90 дней' },
  { value: '365d', label: 'Год' },
  { value: 'all', label: 'Всё время' },
  { value: 'custom', label: 'Период…' },
]

function formatInt(value: number | null | undefined): string {
  return new Intl.NumberFormat('ru-RU').format(value ?? 0)
}

export default function ReactionsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const tab = searchParams.get('tab') === 'stats' ? 'stats' : 'settings'

  function setTab(next: string) {
    const params = new URLSearchParams(searchParams)
    if (next === 'stats') params.set('tab', 'stats')
    else params.delete('tab')
    setSearchParams(params, { replace: true })
  }

  return (
    <div className="admin-page-card">
      <Tabs
        activeKey={tab}
        onChange={setTab}
        destroyOnHidden
        items={[
          { key: 'settings', label: 'Настройки', children: <ReactionSettingsPanel /> },
          { key: 'stats', label: 'Статистика', children: <ReactionStatsPanel /> },
        ]}
      />
    </div>
  )
}

function ReactionSettingsPanel() {
  const [items, setItems] = useState<ReactionItem[]>([])
  const [enabled, setEnabled] = useState(true)
  const [badge, setBadge] = useState('ОЦЕНИТЕ')
  const [title, setTitle] = useState('Как вам этот сериал?')
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<ReactionItem | null>(null)
  const [form] = Form.useForm()

  useDocumentTitle(
    modalOpen
      ? editing
        ? `Редактируем реакцию — ${editing.label || editing.emoji}`
        : 'Новая реакция'
      : null,
  )

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
    try {
      await api(`/api/admin/reactions/${id}`, { method: 'DELETE' })
      message.success('Реакция удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function move(id: number, direction: -1 | 1) {
    const index = items.findIndex((i) => i.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= items.length) return

    const next = [...items]
    const tmp = next[index]
    next[index] = next[target]
    next[target] = tmp

    try {
      await api('/api/admin/reactions/reorder', {
        method: 'POST',
        body: JSON.stringify({ ids: next.map((i) => i.id) }),
      })
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
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
    <>
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
    </>
  )
}

function ReactionVotesChart({
  points,
  loading,
}: {
  points: ReactionStatsResponse['timeseries']
  loading: boolean
}) {
  const max = useMemo(() => Math.max(1, ...points.map((p) => p.votes)), [points])

  if (loading) {
    return <Card loading style={{ minHeight: 220 }} />
  }

  if (points.length === 0) {
    return (
      <Card title="Динамика голосов">
        <Typography.Text type="secondary">Нет данных за выбранный период</Typography.Text>
      </Card>
    )
  }

  const dense = points.length > 45

  return (
    <Card title="Динамика голосов">
      <div className={`views-chart${dense ? ' views-chart--dense' : ''}`}>
        {points.map((point) => {
          const height = Math.max(2, Math.round((point.votes / max) * 100))
          return (
            <div key={point.bucket} className="views-chart__col" title={`${point.label}: ${formatInt(point.votes)}`}>
              <div className="views-chart__value">{dense ? '' : formatInt(point.votes)}</div>
              <div className="views-chart__bar-wrap">
                <div className="views-chart__bar" style={{ height: `${height}%` }} />
              </div>
              <div className="views-chart__label">{point.label}</div>
            </div>
          )
        })}
      </div>
    </Card>
  )
}

function ReactionStatsPanel() {
  const [period, setPeriod] = useState<PeriodKey>('30d')
  const [group, setGroup] = useState<GroupKey>('day')
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>(null)
  const [typeId, setTypeId] = useState<number | 'all'>('all')
  const [data, setData] = useState<ReactionStatsResponse | null>(null)
  const [loading, setLoading] = useState(true)

  useBusyFavicon(loading)
  useDocumentTitle('Статистика реакций')

  const load = useCallback(async (fresh = false) => {
    if (period === 'custom' && !range) {
      setLoading(false)
      return
    }
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('period', period)
      params.set('group', group)
      params.set('top', '25')
      if (period === 'custom' && range) {
        params.set('date_from', range[0].format('YYYY-MM-DD'))
        params.set('date_to', range[1].format('YYYY-MM-DD'))
      }
      if (typeId !== 'all') {
        params.set('reaction_type_id', String(typeId))
      }
      if (fresh) params.set('fresh', '1')
      const res = await api<ReactionStatsResponse>(`/api/admin/reactions/stats?${params}`)
      setData(res)
    } catch (err) {
      message.error(String((err as Error).message || 'Не удалось загрузить статистику'))
    } finally {
      setLoading(false)
    }
  }, [period, group, range, typeId])

  useEffect(() => {
    void load(false)
  }, [load])

  const summary = data?.summary
  const origin = siteOrigin() || ''

  const typeColumns: ColumnsType<ReactionStatsType> = [
    {
      title: '',
      dataIndex: 'emoji',
      width: 56,
      render: (emoji: string) => <span className="reaction-stats__emoji">{emoji}</span>,
    },
    {
      title: 'Реакция',
      dataIndex: 'label',
      render: (label: string, row) => (
        <Space size={8}>
          <span>{label}</span>
          {!row.is_active ? <Tag>Выкл</Tag> : null}
          {row.highlighted ? <Tag color="blue">фильтр</Tag> : null}
        </Space>
      ),
    },
    {
      title: 'Голосов',
      dataIndex: 'votes',
      width: 120,
      align: 'right',
      sorter: (a, b) => a.votes - b.votes,
      defaultSortOrder: 'descend',
      render: (v: number) => formatInt(v),
    },
    {
      title: 'Сериалов',
      dataIndex: 'series_count',
      width: 110,
      align: 'right',
      render: (v: number) => formatInt(v),
    },
    {
      title: 'Доля',
      dataIndex: 'share',
      width: 220,
      render: (share: number) => <Progress percent={share} size="small" format={(p) => `${p}%`} />,
    },
  ]

  const seriesColumns: ColumnsType<ReactionStatsTopSeries> = [
    {
      title: '',
      dataIndex: 'poster_url',
      width: 56,
      render: (url: string | null) =>
        url ? (
          <img src={resolveMediaUrl(url)} alt="" className="views-stats__poster" />
        ) : (
          <div className="views-stats__poster views-stats__poster--empty" />
        ),
    },
    {
      title: 'Сериал',
      dataIndex: 'title',
      render: (_, row) => (
        <Space direction="vertical" size={0}>
          <Link to={`${ADMIN_ROUTES.series}?id=${row.id}`}>{row.title}</Link>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            #{row.id}
            {row.year ? ` · ${row.year}` : ''}
            {!row.is_active ? ' · выкл.' : ''}
          </Typography.Text>
        </Space>
      ),
    },
    {
      title: 'Реакции',
      dataIndex: 'reactions',
      render: (mix: ReactionStatsTopSeries['reactions']) => (
        <div className="reaction-stats__mix">
          {mix.length === 0 ? (
            <Typography.Text type="secondary">—</Typography.Text>
          ) : (
            mix.map((item) => (
              <Tag key={item.id} className="reaction-stats__mix-tag">
                {item.emoji} {formatInt(item.votes)}
              </Tag>
            ))
          )}
        </div>
      ),
    },
    {
      title: 'Голосов',
      dataIndex: 'votes',
      width: 100,
      align: 'right',
      sorter: (a, b) => a.votes - b.votes,
      defaultSortOrder: 'descend',
      render: (v: number) => formatInt(v),
    },
    {
      title: 'Доля',
      dataIndex: 'share',
      width: 140,
      render: (share: number) => <Progress percent={share} size="small" format={(p) => `${p}%`} />,
    },
    {
      title: '',
      key: 'open',
      width: 48,
      render: (_, row) => (
        <a href={`${origin}${seriesPublicPath(row)}`} target="_blank" rel="noreferrer" aria-label="Открыть на сайте">
          <LinkOutlined />
        </a>
      ),
    },
  ]

  return (
    <div className="reaction-stats">
      <Space wrap style={{ marginBottom: 16 }} size="middle">
        <Select
          value={period}
          options={PERIOD_OPTIONS}
          onChange={(value: PeriodKey) => {
            setPeriod(value)
            if (value !== 'custom') setRange(null)
          }}
          style={{ width: 160 }}
        />
        {period === 'custom' ? (
          <RangePicker
            value={range}
            onChange={(values) => {
              if (values?.[0] && values[1]) {
                setRange([values[0], values[1]])
              } else {
                setRange(null)
              }
            }}
            allowClear={false}
            disabledDate={(current) => !!current && current.isAfter(dayjs(), 'day')}
          />
        ) : null}
        <Segmented
          value={group}
          onChange={(value) => setGroup(value as GroupKey)}
          options={[
            { label: 'Дни', value: 'day' },
            { label: 'Недели', value: 'week' },
            { label: 'Месяцы', value: 'month' },
          ]}
        />
        <Select
          value={typeId}
          onChange={setTypeId}
          style={{ minWidth: 220 }}
          options={[
            { value: 'all', label: 'Все реакции' },
            ...(data?.types ?? []).map((type) => ({
              value: type.id,
              label: `${type.emoji} ${type.label}`,
            })),
          ]}
        />
        <Button icon={<ReloadOutlined />} onClick={() => void load(true)} loading={loading}>
          Обновить
        </Button>
        {data?.cache_ttl ? (
          <Typography.Text type="secondary">кэш ~{data.cache_ttl} с</Typography.Text>
        ) : null}
      </Space>

      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сегодня" value={summary?.votes_today ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Вчера" value={summary?.votes_yesterday ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="За период" value={summary?.votes_period ?? 0} />
            {summary?.votes_change_pct != null ? (
              <Tag color={summary.votes_change_pct >= 0 ? 'success' : 'error'} style={{ marginTop: 8 }}>
                {summary.votes_change_pct > 0 ? '+' : ''}
                {summary.votes_change_pct}% к пред. периоду
              </Tag>
            ) : null}
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Всего за всё время" value={summary?.votes_total ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сериалов с реакциями" value={summary?.series_period ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Уникальных голосующих" value={summary?.voters_period ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Пользователи / гости" value={`${formatInt(summary?.users_period)} / ${formatInt(summary?.guests_period)}`} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Среднее в день" value={summary?.avg_per_day ?? 0} />
          </Card>
        </Col>
      </Row>

      <div style={{ marginBottom: 16 }}>
        <ReactionVotesChart points={data?.timeseries ?? []} loading={loading} />
      </div>

      <Card title="Какие реакции ставят" style={{ marginBottom: 16 }}>
        <Table
          rowKey="id"
          loading={loading}
          columns={typeColumns}
          dataSource={data?.by_type ?? []}
          pagination={false}
          size="middle"
          locale={{ emptyText: 'Пока нет голосов' }}
        />
      </Card>

      <Card title="Где поставили — топ сериалов">
        <Table
          rowKey="id"
          loading={loading}
          columns={seriesColumns}
          dataSource={data?.top_series ?? []}
          pagination={false}
          size="middle"
          locale={{ emptyText: 'Нет реакций за выбранный период' }}
        />
      </Card>
    </div>
  )
}
