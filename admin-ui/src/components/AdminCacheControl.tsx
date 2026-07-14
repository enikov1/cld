import { ClearOutlined, ReloadOutlined } from '@ant-design/icons'
import { Button, Popconfirm, Popover, Space, Spin, Typography, message } from 'antd'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { api } from '../api/client'

type CacheDirInfo = {
  path?: string
  exists?: boolean
  files?: number
  bytes?: number
  bytes_human?: string
}

type CacheStoreInfo = {
  driver?: string
  table?: string
  entries?: number | null
  bytes?: number
  bytes_human?: string
  expired?: number | null
  note?: string | null
}

export type CacheInfo = {
  driver: string
  prefix?: string
  total_bytes: number
  total_human: string
  store: CacheStoreInfo
  views: CacheDirInfo
  file_cache: CacheDirInfo
  config_cache: CacheDirInfo
  routes_cache: CacheDirInfo
  tpl: {
    global_version: number
    home_version: number
  }
  collected_at?: string
}

function StatRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="admin-cache-stat">
      <span className="admin-cache-stat__label">{label}</span>
      <span className="admin-cache-stat__value">{value}</span>
    </div>
  )
}

export default function AdminCacheControl() {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [clearing, setClearing] = useState(false)
  const [info, setInfo] = useState<CacheInfo | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ ok: boolean; cache: CacheInfo }>('/api/admin/cache')
      setInfo(data.cache)
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

  async function clearCache() {
    setClearing(true)
    try {
      const data = await api<{ ok: boolean; message?: string; cache: CacheInfo }>('/api/admin/cache/clear', {
        method: 'POST',
      })
      setInfo(data.cache)
      message.success(data.message || 'Кэш очищен')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setClearing(false)
    }
  }

  const content = (
    <div className="admin-cache-popover">
      <Spin spinning={loading || clearing}>
        {info ? (
          <Space direction="vertical" size={10} style={{ width: '100%' }}>
            <StatRow label="Всего" value={<strong>{info.total_human}</strong>} />
            <StatRow label="Драйвер" value={info.driver} />
            {info.store?.table ? <StatRow label="Таблица" value={info.store.table} /> : null}
            {info.store?.entries != null ? (
              <StatRow
                label="Записей"
                value={`${info.store.entries}${info.store.expired ? ` (истекших: ${info.store.expired})` : ''}`}
              />
            ) : null}
            {info.store?.bytes_human ? <StatRow label="Хранилище" value={info.store.bytes_human} /> : null}
            {info.store?.note ? (
              <Typography.Text type="secondary" style={{ fontSize: 12 }}>{info.store.note}</Typography.Text>
            ) : null}
            <StatRow
              label="Шаблоны (views)"
              value={`${info.views.bytes_human || '0 B'}${info.views.files != null ? ` · ${info.views.files} файлов` : ''}`}
            />
            <StatRow
              label="Версия TPL"
              value={`global ${info.tpl.global_version} · home ${info.tpl.home_version}`}
            />
            <StatRow
              label="config/routes"
              value={`${info.config_cache.exists ? info.config_cache.bytes_human : 'нет'} / ${info.routes_cache.exists ? info.routes_cache.bytes_human : 'нет'}`}
            />
          </Space>
        ) : (
          <Typography.Text type="secondary">Нет данных</Typography.Text>
        )}
      </Spin>
      <div className="admin-cache-popover__actions">
        <Button size="small" icon={<ReloadOutlined />} onClick={() => void load()} disabled={loading || clearing}>
          Обновить
        </Button>
        <Popconfirm
          title="Очистить весь кэш сайта?"
          description="Будет сброшен application cache и скомпилированные views."
          okText="Очистить"
          cancelText="Отмена"
          okButtonProps={{ danger: true }}
          onConfirm={() => void clearCache()}
        >
          <Button size="small" danger type="primary" icon={<ClearOutlined />} loading={clearing}>
            Очистить кэш
          </Button>
        </Popconfirm>
      </div>
    </div>
  )

  return (
    <Popover
      content={content}
      title="Кэш сайта"
      trigger="click"
      open={open}
      onOpenChange={setOpen}
      placement="bottomRight"
    >
      <Button icon={<ClearOutlined />}>
        Кэш{info ? ` · ${info.total_human}` : ''}
      </Button>
    </Popover>
  )
}
