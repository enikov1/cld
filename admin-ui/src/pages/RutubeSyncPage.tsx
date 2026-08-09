import { Alert, Button, Card, Form, Input, InputNumber, Popconfirm, Progress, Radio, Space, Typography, message } from 'antd'
import { PauseCircleOutlined, PlayCircleOutlined, StopOutlined } from '@ant-design/icons'
import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'

type TrailerSyncProgress = {
  status: 'idle' | 'running' | 'paused' | 'stopped' | 'done' | 'failed'
  total: number
  processed: number
  synced: number
  skipped: number
  failed: number
  message: string
  tab_name?: string
  existing_mode?: 'skip' | 'update'
  kp_id?: string | null
  sleep?: number
  batch_size?: number
}

type SyncFormValues = {
  tab_name?: string
  existing_mode?: 'skip' | 'update'
  kp_id?: number
  sleep?: number
  batch_size?: number
}

export default function RutubeSyncPage() {
  const [form] = Form.useForm<SyncFormValues>()
  const [syncing, setSyncing] = useState(false)
  const [progress, setProgress] = useState<TrailerSyncProgress | null>(null)
  const [percent, setPercent] = useState(0)
  const abortRef = useRef(false)
  const resumeCheckedRef = useRef(false)
  const loopActiveRef = useRef(false)

  useBusyFavicon(syncing || progress?.status === 'running')

  const loadProgress = useCallback(async () => {
    const res = await api<{
      progress: TrailerSyncProgress
      percent: number
      done: boolean
      paused?: boolean
      stopped?: boolean
    }>('/api/admin/players/rutube-trailer/sync-progress')
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
          progress?: TrailerSyncProgress
          synced?: number
          skipped?: number
          failed?: number
        }>('/api/admin/players/rutube-trailer/sync-all', {
          method: 'POST',
          body: JSON.stringify({
            tab_name: values.tab_name?.trim() || 'Трейлер',
            existing_mode: values.existing_mode ?? 'skip',
            kp_id: values.kp_id ? String(values.kp_id) : undefined,
            sleep: values.sleep ?? 0.5,
            batch_size: values.batch_size ?? 10,
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
              `Готово: проставлено ${res.synced ?? 0}, пропущено ${res.skipped ?? 0}, ошибок: ${res.failed ?? 0}`,
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
        /* progress panel is optional after sync */
      }
    }
  }, [loadProgress])

  useEffect(() => {
    if (resumeCheckedRef.current) return
    resumeCheckedRef.current = true

    loadProgress()
      .then((res) => {
        if (res.progress.status === 'running') {
          void runLoop(
            {
              tab_name: res.progress.tab_name,
              existing_mode: res.progress.existing_mode,
              kp_id: res.progress.kp_id ? Number(res.progress.kp_id) : undefined,
              sleep: res.progress.sleep,
              batch_size: res.progress.batch_size,
            },
            false,
          )
        }
      })
      .catch(() => {
        /* no running job to resume */
      })
  }, [loadProgress, runLoop])

  async function startSync(values: SyncFormValues) {
    await runLoop(values, true)
  }

  async function pauseSync() {
    abortRef.current = true
    try {
      const res = await api<{ progress: TrailerSyncProgress; percent: number }>(
        '/api/admin/players/rutube-trailer/sync-pause',
        { method: 'POST' },
      )
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      message.info('Пауза')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function resumeSync() {
    try {
      const res = await api<{ progress: TrailerSyncProgress; percent: number }>(
        '/api/admin/players/rutube-trailer/sync-resume',
        { method: 'POST' },
      )
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      await runLoop(
        {
          tab_name: res.progress.tab_name,
          existing_mode: res.progress.existing_mode,
          kp_id: res.progress.kp_id ? Number(res.progress.kp_id) : undefined,
          sleep: res.progress.sleep,
          batch_size: res.progress.batch_size,
        },
        false,
      )
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function stopSync() {
    abortRef.current = true
    try {
      const res = await api<{ progress: TrailerSyncProgress; percent: number }>(
        '/api/admin/players/rutube-trailer/sync-stop',
        { method: 'POST' },
      )
      setProgress(res.progress)
      setPercent(res.percent ?? 0)
      message.warning('Остановлено')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const isRunning = syncing || progress?.status === 'running'
  const isPaused = progress?.status === 'paused'
  const showProgress =
    isRunning || isPaused || progress?.status === 'done' || progress?.status === 'stopped' || progress?.status === 'failed'

  return (
    <div>
      <Card title="Rutube — массовая простановка трейлеров">
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Ищет трейлер на Rutube по названию сериала и добавляет вкладку плеера в конец списка. Обработка идёт пакетами, чтобы не повесить сайт на большом каталоге."
        />

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
                      : progress?.status === 'paused'
                        ? 'normal'
                        : 'active'
              }
            />
            <Typography.Text type="secondary">
              {progress?.message || `Обработано ${progress?.processed ?? 0} из ${progress?.total ?? 0}`}
            </Typography.Text>
            {progress && progress.total > 0 ? (
              <Typography.Text type="secondary" style={{ display: 'block' }}>
                Проставлено: {progress.synced}, пропущено: {progress.skipped}, ошибок: {progress.failed}
              </Typography.Text>
            ) : null}
          </div>
        ) : null}

        <Form
          form={form}
          layout="vertical"
          onFinish={startSync}
          style={{ maxWidth: 560 }}
          initialValues={{
            tab_name: 'Трейлер',
            existing_mode: 'skip',
            sleep: 0.5,
            batch_size: 10,
          }}
        >
          <Form.Item
            label="Название вкладки"
            name="tab_name"
            rules={[{ required: true, message: 'Укажите название вкладки' }]}
          >
            <Input placeholder="Трейлер" maxLength={120} disabled={isRunning} />
          </Form.Item>

          <Form.Item
            label="Если трейлер уже есть"
            name="existing_mode"
            rules={[{ required: true, message: 'Выберите режим' }]}
          >
            <Radio.Group disabled={isRunning}>
              <Radio.Button value="skip">Пропускать</Radio.Button>
              <Radio.Button value="update">Обновлять</Radio.Button>
            </Radio.Group>
          </Form.Item>

          <Form.Item label="KP ID (опционально)" name="kp_id" extra="Если пусто — обработаются все сериалы с названием">
            <InputNumber style={{ width: '100%' }} min={1} placeholder="357" disabled={isRunning} />
          </Form.Item>

          <Form.Item label="Размер пакета" name="batch_size" extra="Сколько сериалов обрабатывать за один запрос">
            <InputNumber min={1} max={50} style={{ width: '100%' }} disabled={isRunning} />
          </Form.Item>

          <Form.Item label="Пауза между запросами к Rutube (сек)" name="sleep">
            <InputNumber min={0} max={30} step={0.5} style={{ width: '100%' }} disabled={isRunning} />
          </Form.Item>

          <Space wrap>
            <Popconfirm
              title="Запустить массовую простановку?"
              description="Трейлеры будут искаться на Rutube и добавляться последней вкладкой плеера."
              okText="Запустить"
              cancelText="Отмена"
              onConfirm={() => form.submit()}
              disabled={isRunning || isPaused}
            >
              <Button type="primary" loading={isRunning} disabled={isRunning || isPaused} icon={<PlayCircleOutlined />}>
                Запустить
              </Button>
            </Popconfirm>

            <Button icon={<PauseCircleOutlined />} onClick={pauseSync} disabled={!isRunning}>
              Пауза
            </Button>

            <Button type="default" icon={<PlayCircleOutlined />} onClick={resumeSync} disabled={!isPaused || syncing}>
              Продолжить
            </Button>

            <Button danger icon={<StopOutlined />} onClick={stopSync} disabled={!isRunning && !isPaused}>
              Стоп
            </Button>
          </Space>
        </Form>
      </Card>
    </div>
  )
}
