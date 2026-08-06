import { Button, Drawer, Popconfirm, Select, Space, Table, Tag, Typography, message } from 'antd'
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { CronRunItem, CronRunJobOption } from '../types'

const STATUS_OPTIONS = [
  { value: 'all', label: 'Все статусы' },
  { value: 'running', label: 'Выполняется' },
  { value: 'success', label: 'Успешно' },
  { value: 'failed', label: 'Ошибка' },
  { value: 'skipped', label: 'Пропуск' },
]

const TRIGGER_OPTIONS = [
  { value: 'all', label: 'Все источники' },
  { value: 'schedule', label: 'Крон' },
  { value: 'admin', label: 'Админка' },
  { value: 'cli', label: 'CLI' },
]

function statusTag(status: string) {
  const color =
    status === 'success' ? 'green' :
    status === 'failed' ? 'red' :
    status === 'running' ? 'blue' :
    status === 'skipped' ? 'default' : 'default'
  const label =
    status === 'success' ? 'Успешно' :
    status === 'failed' ? 'Ошибка' :
    status === 'running' ? 'Выполняется' :
    status === 'skipped' ? 'Пропуск' : status
  return <Tag color={color}>{label}</Tag>
}

function triggerLabel(trigger: string) {
  if (trigger === 'schedule') return 'Крон'
  if (trigger === 'admin') return 'Админка'
  if (trigger === 'cli') return 'CLI'
  return trigger
}

function formatDuration(ms?: number | null) {
  if (ms == null) return '—'
  if (ms < 1000) return `${ms} мс`
  const sec = ms / 1000
  if (sec < 60) return `${sec.toFixed(sec >= 10 ? 0 : 1)} с`
  const min = Math.floor(sec / 60)
  const rem = Math.round(sec % 60)
  return `${min} м ${rem} с`
}

function formatCounts(counts?: Record<string, number | string> | null) {
  if (!counts || Object.keys(counts).length === 0) return '—'
  const labels: Record<string, string> = {
    added: 'добавлено',
    updated: 'обновлено',
    skipped: 'пропуск',
    failed: 'ошибок',
    changed: 'изменено',
    urls: 'URL',
    processed: 'обработано',
    total: 'всего',
    status_changed: 'статусы',
    schedule_synced: 'расписание',
    studios_linked: 'студии',
    studio_logos: 'лого',
    kp_ids: 'KP ID',
  }
  return Object.entries(counts)
    .filter(([, v]) => v !== null && v !== undefined && v !== '')
    .map(([k, v]) => `${labels[k] || k}: ${v}`)
    .join(' · ')
}

export default function CronRunsPage() {
  const [items, setItems] = useState<CronRunItem[]>([])
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(50)
  const [total, setTotal] = useState(0)
  const [jobKey, setJobKey] = useState('all')
  const [status, setStatus] = useState('all')
  const [trigger, setTrigger] = useState('all')
  const [jobOptions, setJobOptions] = useState<CronRunJobOption[]>([])
  const [detail, setDetail] = useState<CronRunItem | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async (
    nextPage = page,
    nextPerPage = perPage,
    nextJob = jobKey,
    nextStatus = status,
    nextTrigger = trigger,
  ) => {
    setLoading(true)
    try {
      const params = new URLSearchParams({
        page: String(nextPage),
        per_page: String(nextPerPage),
      })
      if (nextJob && nextJob !== 'all') params.set('job_key', nextJob)
      if (nextStatus && nextStatus !== 'all') params.set('status', nextStatus)
      if (nextTrigger && nextTrigger !== 'all') params.set('trigger', nextTrigger)

      const data = await api<{
        items: CronRunItem[]
        total: number
        page: number
        per_page: number
        job_options: CronRunJobOption[]
      }>(`/api/admin/cron-runs?${params.toString()}`)
      setItems(data.items)
      setTotal(data.total)
      setPage(data.page)
      setPerPage(data.per_page)
      setJobOptions(data.job_options || [])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [page, perPage, jobKey, status, trigger])

  useEffect(() => {
    void load(1, perPage, jobKey, status, trigger)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function openDetail(id: number) {
    setDetailLoading(true)
    try {
      const data = await api<{ item: CronRunItem }>(`/api/admin/cron-runs/${id}`)
      setDetail(data.item)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setDetailLoading(false)
    }
  }

  async function removeRun(id: number) {
    try {
      await api(`/api/admin/cron-runs/${id}`, { method: 'DELETE' })
      message.success('Запись удалена')
      if (detail?.id === id) setDetail(null)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<CronRunItem> = [
    { title: 'ID', dataIndex: 'id', width: 70 },
    {
      title: 'Задача',
      key: 'job',
      width: 220,
      render: (_, r) => (
        <Space direction="vertical" size={0}>
          <span>{r.job_label || r.job_key}</span>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>{r.command}</Typography.Text>
        </Space>
      ),
    },
    {
      title: 'Источник',
      dataIndex: 'trigger',
      width: 100,
      render: (v) => triggerLabel(String(v)),
    },
    {
      title: 'Статус',
      dataIndex: 'status',
      width: 120,
      render: (v) => statusTag(String(v)),
    },
    {
      title: 'Итог',
      key: 'counts',
      ellipsis: true,
      render: (_, r) => r.message || formatCounts(r.counts),
    },
    {
      title: 'Длительность',
      dataIndex: 'duration_ms',
      width: 110,
      render: (v) => formatDuration(v),
    },
    {
      title: 'Старт',
      dataIndex: 'started_at',
      width: 170,
      render: (v) => (v ? new Date(v).toLocaleString('ru-RU') : '—'),
    },
    {
      title: '',
      key: 'actions',
      width: 200,
      render: (_, r) => (
        <Space>
          <Button size="small" onClick={() => void openDetail(r.id)}>
            Подробнее
          </Button>
          <Popconfirm
            title="Удалить запись из истории?"
            onConfirm={() => void removeRun(r.id)}
            okText="Удалить"
            cancelText="Отмена"
            okButtonProps={{ danger: true }}
          >
            <Button size="small" danger>
              Удалить
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  function onTableChange(pagination: TablePaginationConfig) {
    const nextPage = pagination.current || 1
    const nextPerPage = pagination.pageSize || perPage
    setPage(nextPage)
    setPerPage(nextPerPage)
    void load(nextPage, nextPerPage)
  }

  function applyFilters(next: { job?: string; status?: string; trigger?: string }) {
    const nextJob = next.job ?? jobKey
    const nextStatus = next.status ?? status
    const nextTrigger = next.trigger ?? trigger
    setJobKey(nextJob)
    setStatus(nextStatus)
    setTrigger(nextTrigger)
    setPage(1)
    void load(1, perPage, nextJob, nextStatus, nextTrigger)
  }

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Space wrap>
          <Select
            style={{ minWidth: 220 }}
            value={jobKey}
            onChange={(v) => applyFilters({ job: v })}
            options={[
              { value: 'all', label: 'Все задачи' },
              ...jobOptions.map((o) => ({ value: o.value, label: o.label })),
            ]}
          />
          <Select
            style={{ minWidth: 150 }}
            value={status}
            onChange={(v) => applyFilters({ status: v })}
            options={STATUS_OPTIONS}
          />
          <Select
            style={{ minWidth: 150 }}
            value={trigger}
            onChange={(v) => applyFilters({ trigger: v })}
            options={TRIGGER_OPTIONS}
          />
          <Typography.Text type="secondary">Всего: {total}</Typography.Text>
        </Space>
        <Button onClick={() => void load()}>Обновить</Button>
      </div>

      <Table
        rowKey="id"
        loading={loading}
        columns={columns}
        dataSource={items}
        onChange={onTableChange}
        pagination={{
          current: page,
          pageSize: perPage,
          total,
          showSizeChanger: true,
        }}
      />

      <Drawer
        title={detail ? `${detail.job_label || detail.job_key} #${detail.id}` : 'Запуск'}
        open={!!detail || detailLoading}
        onClose={() => setDetail(null)}
        width={560}
        loading={detailLoading}
      >
        {detail ? (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            <div>{statusTag(detail.status)} · {triggerLabel(detail.trigger)}</div>
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              <Typography.Text type="secondary">Команда: </Typography.Text>
              <code>{detail.command}</code>
            </Typography.Paragraph>
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              <Typography.Text type="secondary">Время: </Typography.Text>
              {detail.started_at ? new Date(detail.started_at).toLocaleString('ru-RU') : '—'}
              {detail.finished_at ? ` → ${new Date(detail.finished_at).toLocaleString('ru-RU')}` : ''}
              {` (${formatDuration(detail.duration_ms)})`}
            </Typography.Paragraph>
            {detail.message ? (
              <Typography.Paragraph style={{ marginBottom: 0 }}>{detail.message}</Typography.Paragraph>
            ) : null}
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              <Typography.Text type="secondary">Счётчики: </Typography.Text>
              {formatCounts(detail.counts)}
            </Typography.Paragraph>
            {detail.error ? (
              <Typography.Paragraph type="danger" style={{ marginBottom: 0 }}>
                {detail.error}
              </Typography.Paragraph>
            ) : null}
            {detail.meta && Object.keys(detail.meta).length > 0 ? (
              <pre className="admin-cron-log">{JSON.stringify(detail.meta, null, 2)}</pre>
            ) : null}
            {detail.log ? (
              <>
                <Typography.Text type="secondary">Лог</Typography.Text>
                <pre className="admin-cron-log">{detail.log}</pre>
              </>
            ) : (
              <Typography.Text type="secondary">Подробный лог отсутствует</Typography.Text>
            )}
          </Space>
        ) : null}
      </Drawer>
    </div>
  )
}
