import { EyeOutlined, LinkOutlined, ReloadOutlined } from '@ant-design/icons'
import {
  Alert,
  Button,
  Card,
  Col,
  DatePicker,
  Progress,
  Row,
  Segmented,
  Select,
  Space,
  Statistic,
  Table,
  Tag,
  Typography,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import dayjs, { type Dayjs } from 'dayjs'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'
import { ADMIN_ROUTES } from '../routes/adminRoutes'
import type { ViewsStatsResponse, ViewsStatsTopSeries } from '../types'
import { resolveMediaUrl, siteOrigin } from '../utils/mediaUrl'
import { seriesPublicPath } from '../utils/seriesPublicPath'

const { RangePicker } = DatePicker

type PeriodKey = 'today' | 'yesterday' | '7d' | '30d' | '90d' | '365d' | 'all' | 'custom'
type GroupKey = 'day' | 'week' | 'month'

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

function ViewsChart({
  points,
  loading,
}: {
  points: ViewsStatsResponse['timeseries']
  loading: boolean
}) {
  const max = useMemo(() => Math.max(1, ...points.map((p) => p.views)), [points])

  if (loading) {
    return <Card loading style={{ minHeight: 220 }} />
  }

  if (points.length === 0) {
    return (
      <Card title="Динамика просмотров">
        <Typography.Text type="secondary">Нет данных за выбранный период</Typography.Text>
      </Card>
    )
  }

  const dense = points.length > 45

  return (
    <Card title="Динамика просмотров">
      <div className={`views-chart${dense ? ' views-chart--dense' : ''}`}>
        {points.map((point) => {
          const height = Math.max(2, Math.round((point.views / max) * 100))
          return (
            <div key={point.bucket} className="views-chart__col" title={`${point.label}: ${formatInt(point.views)}`}>
              <div className="views-chart__value">{dense ? '' : formatInt(point.views)}</div>
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

export default function ViewsStatsPage() {
  const [period, setPeriod] = useState<PeriodKey>('30d')
  const [group, setGroup] = useState<GroupKey>('day')
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>(null)
  const [data, setData] = useState<ViewsStatsResponse | null>(null)
  const [loading, setLoading] = useState(true)

  useBusyFavicon(loading)

  const load = useCallback(async () => {
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
      const res = await api<ViewsStatsResponse>(`/api/admin/views-stats?${params}`)
      setData(res)
    } catch (err) {
      message.error(String((err as Error).message || 'Не удалось загрузить статистику'))
    } finally {
      setLoading(false)
    }
  }, [period, group, range])

  useEffect(() => {
    if (period === 'custom' && !range) {
      setLoading(false)
      return
    }
    void load()
  }, [load, period, range])

  const summary = data?.summary
  const origin = siteOrigin() || ''

  const columns: ColumnsType<ViewsStatsTopSeries> = [
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
      title: 'За период',
      dataIndex: 'views',
      width: 110,
      align: 'right',
      sorter: (a, b) => a.views - b.views,
      defaultSortOrder: 'descend',
      render: (v: number) => formatInt(v),
    },
    {
      title: 'Доля',
      dataIndex: 'share',
      width: 160,
      render: (share: number) => <Progress percent={share} size="small" format={(p) => `${p}%`} />,
    },
    {
      title: 'Всего',
      dataIndex: 'views_total',
      width: 110,
      align: 'right',
      render: (v: number) => formatInt(v),
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
    <div className="views-stats">
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
        <Button icon={<ReloadOutlined />} onClick={() => void load()} loading={loading}>
          Обновить
        </Button>
        {data?.cache_ttl ? (
          <Typography.Text type="secondary">кэш ~{data.cache_ttl} с</Typography.Text>
        ) : null}
      </Space>

      {data && !data.ready ? (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message="Таблица дневных просмотров ещё не создана"
          description="Просмотры начнут копиться после миграций и открытия страниц сериалов."
        />
      ) : null}

      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сегодня" value={summary?.views_today ?? 0} prefix={<EyeOutlined />} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Вчера" value={summary?.views_yesterday ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="За период" value={summary?.views_period ?? 0} />
            {summary?.views_change_pct != null ? (
              <Tag color={summary.views_change_pct >= 0 ? 'success' : 'error'} style={{ marginTop: 8 }}>
                {summary.views_change_pct > 0 ? '+' : ''}
                {summary.views_change_pct}% к пред. периоду
              </Tag>
            ) : null}
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Всего за всё время" value={summary?.views_total ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сериалов с просмотрами сегодня" value={summary?.series_active_today ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сериалов с просмотрами за период" value={summary?.series_active_period ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Среднее в день" value={summary?.avg_per_day ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic
              title="Сравнение"
              value={summary?.views_prev_period ?? 0}
              suffix="пред. период"
              valueStyle={{ fontSize: 22 }}
            />
          </Card>
        </Col>
      </Row>

      <div style={{ marginBottom: 16 }}>
        <ViewsChart points={data?.timeseries ?? []} loading={loading} />
      </div>

      <Card title="Топ сериалов за период">
        <Table
          rowKey="id"
          loading={loading}
          columns={columns}
          dataSource={data?.top_series ?? []}
          pagination={false}
          size="middle"
          locale={{ emptyText: 'Нет просмотров за выбранный период' }}
        />
      </Card>
    </div>
  )
}
