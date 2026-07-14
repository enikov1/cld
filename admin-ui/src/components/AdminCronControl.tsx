import { HistoryOutlined, ReloadOutlined } from '@ant-design/icons'
import { Button, Popover, Space, Spin, Tag, Typography, message } from 'antd'
import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import type { CronRunItem } from '../types'

function statusTag(status: string) {
  const color =
    status === 'success' ? 'green' :
    status === 'failed' ? 'red' :
    status === 'running' ? 'blue' :
    status === 'skipped' ? 'default' : 'default'
  const label =
    status === 'success' ? 'OK' :
    status === 'failed' ? 'Ошибка' :
    status === 'running' ? 'Идёт' :
    status === 'skipped' ? 'Пропуск' : status
  return <Tag color={color} style={{ marginInlineEnd: 0 }}>{label}</Tag>
}

function statusShort(status?: string) {
  if (status === 'success') return 'OK'
  if (status === 'failed') return 'ошибка'
  if (status === 'running') return 'идёт'
  if (status === 'skipped') return 'пропуск'
  return null
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

function formatTime(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export default function AdminCronControl() {
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [items, setItems] = useState<CronRunItem[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [detail, setDetail] = useState<CronRunItem | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ items: CronRunItem[]; total: number }>(
        '/api/admin/cron-runs?per_page=8&page=1',
      )
      setItems(data.items || [])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (open) {
      void load()
    }
  }, [open, load])

  async function openDetail(id: number) {
    if (selectedId === id) {
      setSelectedId(null)
      setDetail(null)
      return
    }

    setSelectedId(id)
    setDetailLoading(true)
    try {
      const data = await api<{ item: CronRunItem }>(`/api/admin/cron-runs/${id}`)
      setDetail(data.item)
    } catch (e) {
      message.error(String((e as Error).message))
      setSelectedId(null)
      setDetail(null)
    } finally {
      setDetailLoading(false)
    }
  }

  const latest = items[0]
  const failedRecent = items.filter((item) => item.status === 'failed').length
  const buttonHint = failedRecent > 0
    ? `ошибки: ${failedRecent}`
    : statusShort(latest?.status)

  const content = (
    <div className="admin-cron-popover">
      <Spin spinning={loading}>
        {items.length ? (
          <div className="admin-cron-list">
            {items.map((item) => {
              const active = selectedId === item.id
              return (
                <button
                  key={item.id}
                  type="button"
                  className={`admin-cron-row${active ? ' is-active' : ''}`}
                  onClick={() => void openDetail(item.id)}
                >
                  <div className="admin-cron-row__top">
                    <span className="admin-cron-row__title">{item.job_label || item.job_key}</span>
                    {statusTag(item.status)}
                  </div>
                  <div className="admin-cron-row__meta">
                    <span>{formatTime(item.started_at)}</span>
                    <span>{formatDuration(item.duration_ms)}</span>
                  </div>
                  {item.message ? (
                    <div className="admin-cron-row__msg">{item.message}</div>
                  ) : null}
                </button>
              )
            })}
          </div>
        ) : (
          <Typography.Text type="secondary">Пока нет запусков</Typography.Text>
        )}
      </Spin>

      {(detailLoading || detail) && selectedId ? (
        <div className="admin-cron-detail">
          <Spin spinning={detailLoading}>
            {detail ? (
              <Space direction="vertical" size={6} style={{ width: '100%' }}>
                <Typography.Text strong>
                  #{detail.id} · {detail.job_label || detail.job_key}
                </Typography.Text>
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                  {detail.command}
                </Typography.Text>
                {detail.message ? <div>{detail.message}</div> : null}
                {detail.error ? (
                  <Typography.Text type="danger" style={{ whiteSpace: 'pre-wrap', fontSize: 12 }}>
                    {detail.error}
                  </Typography.Text>
                ) : null}
                {detail.log ? (
                  <pre className="admin-cron-detail__log">{detail.log.slice(0, 2500)}</pre>
                ) : detail.has_log ? (
                  <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                    Есть полный лог — откройте историю задач
                  </Typography.Text>
                ) : null}
              </Space>
            ) : null}
          </Spin>
        </div>
      ) : null}

      <div className="admin-cache-popover__actions">
        <Button size="small" icon={<ReloadOutlined />} onClick={() => void load()} disabled={loading}>
          Обновить
        </Button>
        <Button
          size="small"
          type="link"
          onClick={() => {
            setOpen(false)
            navigate('/cron-runs')
          }}
        >
          Вся история
        </Button>
      </div>
    </div>
  )

  return (
    <Popover
      content={content}
      title="Последние задачи крона"
      trigger="click"
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (!next) {
          setSelectedId(null)
          setDetail(null)
        }
      }}
      placement="bottomRight"
    >
      <Button icon={<HistoryOutlined />} danger={failedRecent > 0}>
        Крон{buttonHint ? ` · ${buttonHint}` : ''}
      </Button>
    </Popover>
  )
}
