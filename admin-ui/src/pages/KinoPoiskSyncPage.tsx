import { Alert, Button, Card, Form, Input, InputNumber, Progress, Space, Switch, Typography, message } from 'antd'
import { PauseCircleOutlined, PlayCircleOutlined, StopOutlined } from '@ant-design/icons'
import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'

type KpSyncProgress = {
  status: 'idle' | 'running' | 'paused' | 'stopped' | 'done' | 'failed'
  total: number
  processed: number
  synced: number
  skipped: number
  failed: number
  message: string
  keyword?: string
  limit?: number
  sleep?: number
  download_poster?: boolean
  batch_size?: number
}

type SyncFormValues = {
  keyword?: string
  limit?: number
  sleep?: number
  download_poster?: boolean
  batch_size?: number
}

export default function KinoPoiskSyncPage() {
  const [kinopoiskConfigured, setKinopoiskConfigured] = useState<boolean | null>(null)
  const [syncing, setSyncing] = useState(false)
  const [progress, setProgress] = useState<KpSyncProgress | null>(null)
  const [percent, setPercent] = useState(0)
  const [form] = Form.useForm<SyncFormValues>()
  const abortRef = useRef(false)
  const loopActiveRef = useRef(false)

  useBusyFavicon(syncing || progress?.status === 'running')

  const loadSettings = useCallback(async () => {
    const settingsData = await api<{ kinopoisk_api_key_set?: boolean }>('/api/admin/settings')
    setKinopoiskConfigured(Boolean(settingsData.kinopoisk_api_key_set))
  }, [])

  const loadProgress = useCallback(async () => {
    const res = await api<{
      progress: KpSyncProgress
      percent: number
      done: boolean
      paused?: boolean
      stopped?: boolean
    }>('/api/admin/sync/kp/progress')
    setProgress(res.progress)
    setPercent(res.percent ?? 0)
    return res
  }, [])

  const runLoop = useCallback(async (values: SyncFormValues, restart: boolean) => {
    if (loopActiveRef.current) return

    loopActiveRef.current = true
    setSyncing(true)
    abortRef.current = false
    let nextRestart = restart

    try {
      while (!abortRef.current) {
        const res = await api<{
          ok: boolean
          done?: boolean
          paused?: boolean
          stopped?: boolean
          percent?: number
          message?: string
          progress?: KpSyncProgress
          synced?: number
          skipped?: number
          failed?: number
        }>('/api/admin/sync/kp', {
          method: 'POST',
          body: JSON.stringify({
            keyword: values.keyword?.trim() || '',
            limit: values.limit ?? 20,
            sleep: values.sleep ?? 0,
            download_poster: values.download_poster ?? false,
            batch_size: values.batch_size ?? 5,
            restart: nextRestart,
            continue: !nextRestart,
          }),
        })

        nextRestart = false
        if (res.progress) setProgress(res.progress)
        setPercent(res.percent ?? 0)

        if (res.paused || res.progress?.status === 'paused') {
          message.info(res.message || 'Задача на паузе')
          break
        }

        if (res.stopped || res.progress?.status === 'stopped') {
          message.warning(res.message || 'Задача остановлена')
          break
        }

        if (res.done) {
          message.success(
            res.message ||
              `Готово: синхронизировано ${res.synced ?? 0}, пропущено ${res.skipped ?? 0}, ошибок: ${res.failed ?? 0}`,
          )
          break
        }
      }
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      loopActiveRef.current = false
      setSyncing(false)
      try {
        await loadProgress()
      } catch {
        /* optional */
      }
    }
  }, [loadProgress])

  useEffect(() => {
    loadSettings().catch((e) => message.error(String((e as Error).message)))
  }, [loadSettings])

  useEffect(() => {
    loadProgress().catch(() => {
      /* no running job */
    })
    return () => {
      abortRef.current = true
    }
  }, [loadProgress])

  async function startSync(values: SyncFormValues) {
    await runLoop(values, true)
  }

  async function pauseSync() {
    abortRef.current = true
    try {
      const res = await api<{ progress: KpSyncProgress; percent: number }>('/api/admin/sync/kp/pause', {
        method: 'POST',
      })
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      message.info('Пауза')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function resumeSync() {
    try {
      const res = await api<{ progress: KpSyncProgress; percent: number }>('/api/admin/sync/kp/resume', {
        method: 'POST',
      })
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      await runLoop(
        {
          keyword: res.progress.keyword,
          limit: res.progress.limit,
          sleep: res.progress.sleep,
          download_poster: res.progress.download_poster,
          batch_size: res.progress.batch_size,
        },
        false,
      )
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function continueRunningSync() {
    if (!progress || progress.status !== 'running') return
    await runLoop(
      {
        keyword: progress.keyword,
        limit: progress.limit,
        sleep: progress.sleep,
        download_poster: progress.download_poster,
        batch_size: progress.batch_size,
      },
      false,
    )
  }

  async function stopSync() {
    abortRef.current = true
    try {
      const res = await api<{ progress: KpSyncProgress; percent: number }>('/api/admin/sync/kp/stop', {
        method: 'POST',
      })
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      message.warning('Остановлено')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const serverRunning = progress?.status === 'running'
  const isPaused = progress?.status === 'paused'
  const needsClientContinue = serverRunning && !syncing
  const isRunning = syncing
  const formLocked = syncing || serverRunning || isPaused
  const showProgress =
    syncing ||
    serverRunning ||
    isPaused ||
    progress?.status === 'done' ||
    progress?.status === 'stopped' ||
    progress?.status === 'failed'

  return (
    <div>
      <Card title="Импорт из KinoPoisk API">
        <Alert
          type={kinopoiskConfigured ? 'info' : 'warning'}
          showIcon
          style={{ marginBottom: 16 }}
          message={
            kinopoiskConfigured
              ? 'API-ключ KinoPoisk настроен. Импорт идёт пакетами с прогрессом и паузой.'
              : 'Укажите API-ключ KinoPoisk в разделе Настройки → Интеграции.'
          }
        />

        {needsClientContinue ? (
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 16 }}
            message="На сервере есть незавершённая задача. Нажмите «Продолжить», чтобы возобновить пакеты в этой вкладке."
          />
        ) : null}

        {showProgress ? (
          <div style={{ marginBottom: 16 }}>
            <Progress
              percent={percent}
              status={
                progress?.status === 'failed'
                  ? 'exception'
                  : progress?.status === 'done'
                    ? 'success'
                    : progress?.status === 'stopped'
                      ? 'exception'
                      : progress?.status === 'paused' || needsClientContinue
                        ? 'normal'
                        : 'active'
              }
            />
            <Typography.Text type="secondary">
              {progress?.message || `Обработано ${progress?.processed ?? 0} из ${progress?.total ?? 0}`}
            </Typography.Text>
            {progress && progress.total > 0 ? (
              <Typography.Text type="secondary" style={{ display: 'block' }}>
                Синхронизировано: {progress.synced}, пропущено: {progress.skipped}, ошибок: {progress.failed}
              </Typography.Text>
            ) : null}
          </div>
        ) : null}

        <Form
          form={form}
          layout="vertical"
          onFinish={startSync}
          initialValues={{ limit: 20, download_poster: true, sleep: 0, batch_size: 5 }}
          style={{ maxWidth: 520 }}
        >
          <Form.Item label="Поисковый запрос" name="keyword" rules={[{ required: true, message: 'Введите название сериала' }]}>
            <Input placeholder="Например: извне, даттон" disabled={formLocked} />
          </Form.Item>
          <Form.Item label="Лимит результатов" name="limit">
            <InputNumber min={1} max={250} style={{ width: '100%' }} disabled={formLocked} />
          </Form.Item>
          <Form.Item label="Размер пакета" name="batch_size" extra="Сколько фильмов обрабатывать за один HTTP-запрос">
            <InputNumber min={1} max={50} style={{ width: '100%' }} disabled={formLocked} />
          </Form.Item>
          <Form.Item label="Пауза между запросами (сек)" name="sleep">
            <InputNumber min={0} max={30} step={0.5} style={{ width: '100%' }} disabled={formLocked} />
          </Form.Item>
          <Form.Item label="Скачать постеры на сервер" name="download_poster" valuePropName="checked">
            <Switch disabled={formLocked} />
          </Form.Item>

          <Space wrap>
            {!formLocked ? (
              <Button type="primary" htmlType="submit" icon={<PlayCircleOutlined />} disabled={!kinopoiskConfigured}>
                Запустить импорт
              </Button>
            ) : null}
            {isRunning ? (
              <Button icon={<PauseCircleOutlined />} onClick={() => void pauseSync()}>
                Пауза
              </Button>
            ) : null}
            {isPaused || needsClientContinue ? (
              <Button
                type="primary"
                icon={<PlayCircleOutlined />}
                onClick={() => void (isPaused ? resumeSync() : continueRunningSync())}
              >
                Продолжить
              </Button>
            ) : null}
            {isRunning || isPaused || needsClientContinue ? (
              <Button danger icon={<StopOutlined />} onClick={() => void stopSync()}>
                Стоп
              </Button>
            ) : null}
          </Space>
        </Form>
      </Card>
    </div>
  )
}
