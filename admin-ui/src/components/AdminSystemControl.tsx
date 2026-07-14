import { CloudServerOutlined, ReloadOutlined } from '@ant-design/icons'
import { Button, Popover, Progress, Space, Spin, Typography, message } from 'antd'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { api } from '../api/client'

type LargestTable = {
  name: string
  rows: number
  size_bytes: number
  size_human: string
}

type SystemInfo = {
  database: {
    connection: string
    driver: string
    name: string
    version?: string | null
    size_bytes?: number | null
    size_human: string
    tables?: number | null
    largest_tables?: LargestTable[]
    note?: string | null
  }
  disk: {
    path: string
    mount?: string
    total_human: string
    free_human: string
    used_human: string
    used_percent?: number | null
    note?: string | null
  }
  memory: {
    total_human: string
    available_human: string
    used_human: string
    used_percent?: number | null
    note?: string | null
  }
  cpu: {
    cores?: number | null
    load_1?: number | null
    load_5?: number | null
    load_15?: number | null
    load_human: string
    note?: string | null
  }
  php: {
    version: string
    sapi: string
    memory_limit: string
    memory_usage_human: string
    memory_peak_human: string
    laravel: string
    timezone: string
  }
  os: {
    family: string
    name: string
    hostname?: string | null
    uname: string
    uptime_human?: string | null
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

function SectionTitle({ children }: { children: ReactNode }) {
  return <Typography.Text strong className="admin-system-section">{children}</Typography.Text>
}

function usageStatus(percent?: number | null): 'normal' | 'active' | 'success' | 'exception' {
  if (percent == null) return 'normal'
  if (percent >= 90) return 'exception'
  if (percent >= 75) return 'active'
  return 'success'
}

function buttonLabel(info: SystemInfo | null): string {
  if (!info) return 'Система'
  const diskFree = info.disk.free_human
  const ram = info.memory.used_percent != null ? `${info.memory.used_percent}% RAM` : null
  const parts = [diskFree !== '—' ? `Диск ${diskFree}` : null, ram].filter(Boolean)
  return parts.length ? `Система · ${parts.join(' · ')}` : 'Система'
}

export default function AdminSystemControl() {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [info, setInfo] = useState<SystemInfo | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ ok: boolean; system: SystemInfo }>('/api/admin/system')
      setInfo(data.system)
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

  const content = (
    <div className="admin-system-popover">
      <Spin spinning={loading}>
        {info ? (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            <div>
              <SectionTitle>Диск</SectionTitle>
              <StatRow label="Том" value={info.disk.mount || info.disk.path} />
              <StatRow label="Всего" value={info.disk.total_human} />
              <StatRow label="Занято" value={`${info.disk.used_human}${info.disk.used_percent != null ? ` (${info.disk.used_percent}%)` : ''}`} />
              <StatRow label="Свободно" value={<strong>{info.disk.free_human}</strong>} />
              {info.disk.used_percent != null ? (
                <Progress
                  percent={Math.min(100, info.disk.used_percent)}
                  size="small"
                  status={usageStatus(info.disk.used_percent)}
                  style={{ marginTop: 6, marginBottom: 0 }}
                />
              ) : null}
              {info.disk.note ? (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>{info.disk.note}</Typography.Text>
              ) : null}
            </div>

            <div>
              <SectionTitle>База данных</SectionTitle>
              <StatRow label="БД" value={info.database.name || '—'} />
              <StatRow label="Драйвер" value={`${info.database.driver}${info.database.version ? ` ${info.database.version}` : ''}`} />
              <StatRow label="Размер" value={<strong>{info.database.size_human}</strong>} />
              {info.database.tables != null ? <StatRow label="Таблиц" value={info.database.tables} /> : null}
              {info.database.largest_tables && info.database.largest_tables.length > 0 ? (
                <div className="admin-system-tables">
                  {info.database.largest_tables.slice(0, 5).map((table) => (
                    <div key={table.name} className="admin-cache-stat">
                      <span className="admin-cache-stat__label">{table.name}</span>
                      <span className="admin-cache-stat__value">
                        {table.size_human}
                        {table.rows ? ` · ~${table.rows.toLocaleString('ru-RU')} строк` : ''}
                      </span>
                    </div>
                  ))}
                </div>
              ) : null}
              {info.database.note ? (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>{info.database.note}</Typography.Text>
              ) : null}
            </div>

            <div>
              <SectionTitle>Память (RAM)</SectionTitle>
              <StatRow label="Всего" value={info.memory.total_human} />
              <StatRow label="Занято" value={`${info.memory.used_human}${info.memory.used_percent != null ? ` (${info.memory.used_percent}%)` : ''}`} />
              <StatRow label="Свободно" value={info.memory.available_human} />
              {info.memory.used_percent != null ? (
                <Progress
                  percent={Math.min(100, info.memory.used_percent)}
                  size="small"
                  status={usageStatus(info.memory.used_percent)}
                  style={{ marginTop: 6, marginBottom: 0 }}
                />
              ) : null}
              {info.memory.note ? (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>{info.memory.note}</Typography.Text>
              ) : null}
            </div>

            <div>
              <SectionTitle>CPU / система</SectionTitle>
              <StatRow label="Ядер" value={info.cpu.cores ?? '—'} />
              <StatRow label="Load 1/5/15" value={info.cpu.load_human} />
              {info.os.uptime_human ? <StatRow label="Аптайм" value={info.os.uptime_human} /> : null}
              <StatRow label="Хост" value={info.os.hostname || '—'} />
              <StatRow label="ОС" value={`${info.os.family} (${info.os.name})`} />
              {info.cpu.note ? (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>{info.cpu.note}</Typography.Text>
              ) : null}
            </div>

            <div>
              <SectionTitle>PHP</SectionTitle>
              <StatRow label="PHP" value={`${info.php.version} · ${info.php.sapi}`} />
              <StatRow label="Laravel" value={info.php.laravel} />
              <StatRow label="Лимит памяти" value={info.php.memory_limit} />
              <StatRow label="Использовано PHP" value={`${info.php.memory_usage_human} (peak ${info.php.memory_peak_human})`} />
              <StatRow label="Timezone" value={info.php.timezone} />
            </div>
          </Space>
        ) : (
          <Typography.Text type="secondary">Нет данных</Typography.Text>
        )}
      </Spin>
      <div className="admin-cache-popover__actions">
        <Button size="small" icon={<ReloadOutlined />} onClick={() => void load()} disabled={loading}>
          Обновить
        </Button>
        {info?.collected_at ? (
          <Typography.Text type="secondary" style={{ fontSize: 11 }}>
            {new Date(info.collected_at).toLocaleTimeString('ru-RU')}
          </Typography.Text>
        ) : null}
      </div>
    </div>
  )

  return (
    <Popover
      content={content}
      title="Сервер и база данных"
      trigger="click"
      open={open}
      onOpenChange={setOpen}
      placement="bottomRight"
    >
      <Button icon={<CloudServerOutlined />}>
        {buttonLabel(info)}
      </Button>
    </Popover>
  )
}
