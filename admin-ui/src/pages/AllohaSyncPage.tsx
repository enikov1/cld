import { Alert, Button, Card, Checkbox, Divider, Form, Input, InputNumber, Popconfirm, Progress, Select, Space, Switch, Tabs, Typography, message } from 'antd'
import { PauseCircleOutlined, PlayCircleOutlined, StopOutlined } from '@ant-design/icons'
import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'

type IntervalOption = { value: number; label: string }

type AutoSyncSettings = {
  enabled: boolean
  interval_minutes: number
  latest_days: number
  auto_add_new: boolean
  new_is_hidden: boolean
  new_is_active: boolean
  download_poster_new: boolean
  update_existing: boolean
  update_ratings: boolean
  update_players: boolean
  update_metadata: boolean
  update_poster: boolean
  update_genres_countries: boolean
  fill_empty_only: boolean
}

type PlayerSyncProgress = {
  status: 'idle' | 'running' | 'paused' | 'stopped' | 'done' | 'failed'
  total: number
  processed: number
  synced: number
  skipped: number
  failed: number
  message: string
  tab_name?: string
  position?: number
  kp_id?: string | null
  sleep?: number
}

type PlayerSyncFormValues = {
  tab_name?: string
  position?: number
  kp_id?: number
  sleep?: number
}

export default function AllohaSyncPage() {
  const [allohaConfigured, setAllohaConfigured] = useState<boolean | null>(null)
  const [loading, setLoading] = useState(false)
  const [autoLoading, setAutoLoading] = useState(false)
  const [output, setOutput] = useState('')
  const [lastRunHuman, setLastRunHuman] = useState<string | null>(null)
  const [intervalOptions, setIntervalOptions] = useState<IntervalOption[]>([])
  const [form] = Form.useForm()
  const [bulkForm] = Form.useForm()
  const [autoForm] = Form.useForm()
  const [playerForm] = Form.useForm()
  const [playerSyncing, setPlayerSyncing] = useState(false)
  const [playerProgress, setPlayerProgress] = useState<PlayerSyncProgress | null>(null)
  const [playerPercent, setPlayerPercent] = useState(0)
  const playerSyncAbortRef = useRef(false)
  const playerLoopActiveRef = useRef(false)

  useBusyFavicon(
    loading ||
      autoLoading ||
      playerSyncing ||
      playerProgress?.status === 'running',
  )

  const loadSettings = useCallback(async () => {
    const [settingsData, autoData] = await Promise.all([
      api<{ alloha_api_token_set?: boolean }>('/api/admin/settings'),
      api<{
        settings: AutoSyncSettings
        interval_options: IntervalOption[]
        last_run_human?: string | null
        alloha_api_token_set?: boolean
      }>('/api/admin/alloha/auto-sync'),
    ])
    setAllohaConfigured(Boolean(settingsData.alloha_api_token_set ?? autoData.alloha_api_token_set))
    setIntervalOptions(autoData.interval_options ?? [])
    setLastRunHuman(autoData.last_run_human ?? null)
    autoForm.setFieldsValue(autoData.settings)
  }, [autoForm])

  useEffect(() => {
    loadSettings().catch((e) => message.error(String((e as Error).message)))
  }, [loadSettings])

  const loadPlayerProgress = useCallback(async () => {
    const res = await api<{
      progress: PlayerSyncProgress
      percent: number
      done: boolean
      paused?: boolean
      stopped?: boolean
    }>('/api/admin/players/alloha/sync-progress')
    setPlayerProgress(res.progress)
    setPlayerPercent(res.percent ?? 0)
    return res
  }, [])

  const runPlayerLoop = useCallback(async (values: PlayerSyncFormValues, restart: boolean) => {
    if (playerLoopActiveRef.current) return

    playerLoopActiveRef.current = true
    setPlayerSyncing(true)
    setOutput('')
    playerSyncAbortRef.current = false
    let nextRestart = restart
    let lastMessage = ''

    try {
      while (!playerSyncAbortRef.current) {
        const res = await api<{
          ok: boolean
          done?: boolean
          paused?: boolean
          stopped?: boolean
          percent?: number
          message?: string
          progress?: PlayerSyncProgress
          synced?: number
          skipped?: number
          failed?: number
        }>('/api/admin/players/alloha/sync-all', {
          method: 'POST',
          body: JSON.stringify({
            tab_name: values.tab_name?.trim() || 'Смотреть онлайн',
            position: values.position ?? 1,
            kp_id: values.kp_id ? String(values.kp_id) : undefined,
            sleep: values.sleep ?? 0,
            restart: nextRestart,
            continue: !nextRestart,
          }),
        })

        nextRestart = false
        lastMessage = res.message || res.progress?.message || lastMessage
        if (res.progress) setPlayerProgress(res.progress)
        setPlayerPercent(res.percent ?? 0)
        setOutput(lastMessage)

        if (res.paused || res.progress?.status === 'paused') {
          message.info(lastMessage || 'Задача на паузе')
          break
        }

        if (res.stopped || res.progress?.status === 'stopped') {
          message.warning(lastMessage || 'Задача остановлена')
          break
        }

        if (res.done) {
          message.success(
            lastMessage ||
              `Готово: проставлено ${res.synced ?? 0}, пропущено ${res.skipped ?? 0}, ошибок: ${res.failed ?? 0}`,
          )
          break
        }
      }
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      playerLoopActiveRef.current = false
      setPlayerSyncing(false)
      try {
        await loadPlayerProgress()
      } catch {
        /* progress panel is optional after sync */
      }
    }
  }, [loadPlayerProgress])

  async function runSync(values: Record<string, unknown>) {
    setLoading(true)
    setOutput('')
    try {
      const res = await api<{ ok: boolean; output: string }>('/api/admin/sync/alloha', {
        method: 'POST',
        body: JSON.stringify(values),
      })
      setOutput(res.output || 'Синхронизация завершена.')
      message.success('Синхронизация завершена')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  async function runBulkImport(values: Record<string, unknown>) {
    setLoading(true)
    setOutput('')
    try {
      const res = await api<{ ok: boolean; output: string }>('/api/admin/sync/alloha-import', {
        method: 'POST',
        body: JSON.stringify(values),
      })
      setOutput(res.output || 'Импорт завершён.')
      message.success('Импорт завершён')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  async function saveAutoSettings(values: AutoSyncSettings) {
    setAutoLoading(true)
    try {
      await api('/api/admin/alloha/auto-sync', {
        method: 'POST',
        body: JSON.stringify(values),
      })
      message.success('Настройки автосинхронизации сохранены')
      await loadSettings()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setAutoLoading(false)
    }
  }

  async function runLatestCheck() {
    setLoading(true)
    setOutput('')
    try {
      const values = autoForm.getFieldsValue() as AutoSyncSettings
      const res = await api<{ ok: boolean; output: string; result?: { added: number; updated: number } }>(
        '/api/admin/sync/alloha-latest',
        {
          method: 'POST',
          body: JSON.stringify({
            use_saved_settings: false,
            settings: values,
            days: values.latest_days,
          }),
        },
      )
      setOutput(res.output || 'Проверка завершена.')
      const added = res.result?.added ?? 0
      const updated = res.result?.updated ?? 0
      message.success(`Готово: добавлено ${added}, обновлено ${updated}`)
      setLastRunHuman(new Date().toLocaleString('ru-RU'))
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  async function startPlayerSync(values: PlayerSyncFormValues) {
    await runPlayerLoop(values, true)
  }

  async function pausePlayerSync() {
    playerSyncAbortRef.current = true
    try {
      const res = await api<{ progress: PlayerSyncProgress; percent: number }>(
        '/api/admin/players/alloha/sync-pause',
        { method: 'POST' },
      )
      setPlayerProgress(res.progress)
      setPlayerPercent(res.percent ?? 0)
      message.info('Пауза')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function resumePlayerSync() {
    try {
      const res = await api<{ progress: PlayerSyncProgress; percent: number }>(
        '/api/admin/players/alloha/sync-resume',
        { method: 'POST' },
      )
      setPlayerProgress(res.progress)
      setPlayerPercent(res.percent ?? 0)
      await runPlayerLoop(
        {
          tab_name: res.progress.tab_name,
          position: res.progress.position,
          kp_id: res.progress.kp_id ? Number(res.progress.kp_id) : undefined,
          sleep: res.progress.sleep,
        },
        false,
      )
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function stopPlayerSync() {
    playerSyncAbortRef.current = true
    try {
      const res = await api<{ progress: PlayerSyncProgress; percent: number }>(
        '/api/admin/players/alloha/sync-stop',
        { method: 'POST' },
      )
      setPlayerProgress(res.progress)
      setPlayerPercent(res.percent ?? 0)
      message.warning('Остановлено')
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function continuePlayerSync() {
    if (!playerProgress || playerProgress.status !== 'running') return
    await runPlayerLoop(
      {
        tab_name: playerProgress.tab_name,
        position: playerProgress.position,
        kp_id: playerProgress.kp_id ? Number(playerProgress.kp_id) : undefined,
        sleep: playerProgress.sleep,
      },
      false,
    )
  }

  useEffect(() => {
    loadPlayerProgress().catch(() => {
      /* no running job to resume */
    })
    return () => {
      playerSyncAbortRef.current = true
    }
  }, [loadPlayerProgress])

  const playerServerRunning = playerProgress?.status === 'running'
  const playerIsPaused = playerProgress?.status === 'paused'
  const playerNeedsContinue = playerServerRunning && !playerSyncing
  const playerIsRunning = playerSyncing
  const playerFormLocked = playerSyncing || playerServerRunning || playerIsPaused
  const showPlayerProgress =
    playerSyncing ||
    playerServerRunning ||
    playerIsPaused ||
    playerProgress?.status === 'done' ||
    playerProgress?.status === 'stopped' ||
    playerProgress?.status === 'failed'

  return (
    <div>
      <Card title="Alloha TV — синхронизация">
        <Alert
          type={allohaConfigured ? 'info' : 'warning'}
          showIcon
          style={{ marginBottom: 16 }}
          message={
            allohaConfigured
              ? 'API-токен Alloha настроен. Используется эндпоинт /v2/movies/latest для проверки новинок.'
              : 'Укажите API-токен Alloha в разделе Настройки → Интеграции.'
          }
        />

        <Tabs
          items={[
            {
              key: 'auto',
              label: 'Автообновление',
              children: (
                <Form form={autoForm} layout="vertical" onFinish={saveAutoSettings} style={{ maxWidth: 640 }}>
                  <Typography.Paragraph type="secondary">
                    Проверяет <code>/v2/movies/latest</code> и добавляет новый контент или обновляет существующий по расписанию.
                    {lastRunHuman ? ` Последний запуск: ${lastRunHuman}.` : ' Ещё не запускалось.'}
                  </Typography.Paragraph>

                  <Form.Item label="Включить автосинхронизацию" name="enabled" valuePropName="checked">
                    <Switch />
                  </Form.Item>

                  <Form.Item label="Интервал проверки" name="interval_minutes">
                    <Select options={intervalOptions} />
                  </Form.Item>

                  <Form.Item
                    label="Период проверки (дней)"
                    name="latest_days"
                    extra="Сколько дней назад смотреть в /v2/movies/latest (1–30)"
                  >
                    <InputNumber min={1} max={30} style={{ width: '100%' }} />
                  </Form.Item>

                  <Divider>Новый контент</Divider>

                  <Form.Item label="Автоматически добавлять на сайт" name="auto_add_new" valuePropName="checked">
                    <Switch />
                  </Form.Item>

                  <Form.Item
                    label="Скрыть новые по умолчанию"
                    name="new_is_hidden"
                    valuePropName="checked"
                    extra="Страница будет недоступна посетителям (404), пока не снимете скрытие вручную"
                  >
                    <Switch />
                  </Form.Item>

                  <Form.Item label="Показывать на сайте (is_active)" name="new_is_active" valuePropName="checked">
                    <Switch />
                  </Form.Item>

                  <Form.Item label="Скачивать постеры для новых" name="download_poster_new" valuePropName="checked">
                    <Switch />
                  </Form.Item>

                  <Divider>Обновление существующих</Divider>

                  <Form.Item label="Обновлять существующие сериалы" name="update_existing" valuePropName="checked">
                    <Switch />
                  </Form.Item>

                  <Form.Item label="Какие поля обновлять">
                    <Space direction="vertical">
                      <Form.Item name="update_ratings" valuePropName="checked" noStyle>
                        <Checkbox>Рейтинги (KP, IMDb)</Checkbox>
                      </Form.Item>
                      <Form.Item name="update_players" valuePropName="checked" noStyle>
                        <Checkbox>Плееры / озвучки</Checkbox>
                      </Form.Item>
                      <Form.Item name="update_metadata" valuePropName="checked" noStyle>
                        <Checkbox>Метаданные (название, описание, год…)</Checkbox>
                      </Form.Item>
                      <Form.Item name="update_poster" valuePropName="checked" noStyle>
                        <Checkbox>Постер</Checkbox>
                      </Form.Item>
                      <Form.Item name="update_genres_countries" valuePropName="checked" noStyle>
                        <Checkbox>Жанры и страны</Checkbox>
                      </Form.Item>
                      <Form.Item name="fill_empty_only" valuePropName="checked" noStyle>
                        <Checkbox>Только пустые поля (не затирать ручные правки)</Checkbox>
                      </Form.Item>
                    </Space>
                  </Form.Item>

                  <Space wrap>
                    <Button type="primary" htmlType="submit" loading={autoLoading}>
                      Сохранить настройки
                    </Button>
                    <Button onClick={runLatestCheck} loading={loading}>
                      Проверить сейчас
                    </Button>
                  </Space>
                </Form>
              ),
            },
            {
              key: 'update',
              label: 'Обновить существующие',
              children: (
                <Form form={form} layout="vertical" onFinish={runSync} style={{ maxWidth: 520 }}>
                  <Form.Item label="KP ID (опционально)" name="kp_id" extra="Если пусто — обновятся все сериалы с KP ID">
                    <InputNumber style={{ width: '100%' }} min={1} placeholder="357" />
                  </Form.Item>
                  <Form.Item label="Режим" name="mode" initialValue="all">
                    <Select
                      options={[
                        { value: 'all', label: 'Метаданные + плееры' },
                        { value: 'ratings', label: 'Только рейтинги' },
                        { value: 'players', label: 'Только плееры' },
                      ]}
                    />
                  </Form.Item>
                  <Form.Item label="Пауза между запросами (сек)" name="sleep" initialValue={0}>
                    <InputNumber min={0} max={30} step={0.5} style={{ width: '100%' }} />
                  </Form.Item>
                  <Button type="primary" htmlType="submit" loading={loading}>
                    Запустить синхронизацию
                  </Button>
                </Form>
              ),
            },
            {
              key: 'players',
              label: 'Проставить плеер',
              children: (
                <Form
                  form={playerForm}
                  layout="vertical"
                  onFinish={startPlayerSync}
                  style={{ maxWidth: 520 }}
                  initialValues={{ tab_name: 'Смотреть онлайн', position: 1, sleep: 0.5 }}
                >
                  <Typography.Paragraph type="secondary">
                    Добавляет или обновляет вкладку плеера Alloha у сериалов с KP ID. Iframe запрашивается через{' '}
                    <code>/v2/movies/exists</code> (KP → IMDb → TMDB) и детальные эндпоинты Alloha API. Позиция считается среди всех
                    вкладок сериала. Пропущенные — нет в каталоге Alloha или нет активных файлов.
                  </Typography.Paragraph>

                  {showPlayerProgress ? (
                    <div style={{ marginBottom: 16 }}>
                      <Progress
                        percent={playerPercent}
                        status={
                          playerProgress?.status === 'failed'
                            ? 'exception'
                            : playerProgress?.status === 'done'
                              ? 'success'
                              : playerProgress?.status === 'stopped'
                                ? 'exception'
                                : playerProgress?.status === 'paused'
                                  ? 'normal'
                                  : 'active'
                        }
                      />
                      <Typography.Text type="secondary">
                        {playerProgress?.message ||
                          `Обработано ${playerProgress?.processed ?? 0} из ${playerProgress?.total ?? 0}`}
                      </Typography.Text>
                      {playerProgress && playerProgress.total > 0 ? (
                        <Typography.Text type="secondary" style={{ display: 'block' }}>
                          Проставлено: {playerProgress.synced}, пропущено: {playerProgress.skipped}, ошибок:{' '}
                          {playerProgress.failed}
                        </Typography.Text>
                      ) : null}
                    </div>
                  ) : null}

                  <Form.Item
                    label="Название вкладки"
                    name="tab_name"
                    rules={[{ required: true, message: 'Укажите название вкладки' }]}
                  >
                    <Input placeholder="Смотреть онлайн" maxLength={120} disabled={playerFormLocked} />
                  </Form.Item>

                  <Form.Item
                    label="Позиция вкладки"
                    name="position"
                    extra="1 — первая (слева), 2 — вторая, 3 — третья и т.д."
                    rules={[{ required: true, message: 'Укажите позицию' }]}
                  >
                    <InputNumber min={1} max={20} style={{ width: '100%' }} disabled={playerFormLocked} />
                  </Form.Item>

                  <Form.Item label="KP ID (опционально)" name="kp_id" extra="Если пусто — обработаются все сериалы с KP ID">
                    <InputNumber style={{ width: '100%' }} min={1} placeholder="357" disabled={playerFormLocked} />
                  </Form.Item>

                  <Form.Item label="Пауза между запросами (сек)" name="sleep" extra="Только при обращении к API Alloha">
                    <InputNumber min={0} max={30} step={0.5} style={{ width: '100%' }} disabled={playerFormLocked} />
                  </Form.Item>

                  <Space wrap>
                    {!playerFormLocked ? (
                      <Popconfirm
                        title="Проставить плеер Alloha?"
                        description="Будет создана или обновлена вкладка плеера. Существующие вкладки Alloha у сериала будут перезаписаны."
                        okText="Проставить"
                        cancelText="Отмена"
                        onConfirm={() => playerForm.submit()}
                        disabled={!allohaConfigured}
                      >
                        <Button type="primary" icon={<PlayCircleOutlined />} disabled={!allohaConfigured}>
                          Проставить плеер
                        </Button>
                      </Popconfirm>
                    ) : null}
                    {playerIsRunning ? (
                      <Button icon={<PauseCircleOutlined />} onClick={() => void pausePlayerSync()}>
                        Пауза
                      </Button>
                    ) : null}
                    {playerIsPaused || playerNeedsContinue ? (
                      <Button
                        type="primary"
                        icon={<PlayCircleOutlined />}
                        onClick={() => void (playerIsPaused ? resumePlayerSync() : continuePlayerSync())}
                      >
                        Продолжить
                      </Button>
                    ) : null}
                    {playerIsRunning || playerIsPaused || playerNeedsContinue ? (
                      <Button danger icon={<StopOutlined />} onClick={() => void stopPlayerSync()}>
                        Стоп
                      </Button>
                    ) : null}
                  </Space>
                </Form>
              ),
            },
            {
              key: 'import',
              label: 'Импорт из каталога',
              children: (
                <Form form={bulkForm} layout="vertical" onFinish={runBulkImport} style={{ maxWidth: 520 }}>
                  <Typography.Paragraph type="secondary">
                    Сравнивает полный каталог Alloha с базой и импортирует отсутствующие KP ID.
                  </Typography.Paragraph>
                  <Form.Item label="Лимит" name="limit" initialValue={50} extra="Максимум новых записей за один запуск">
                    <InputNumber min={1} max={500} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item label="Скачать постеры" name="download_poster" valuePropName="checked" initialValue={true}>
                    <Switch />
                  </Form.Item>
                  <Form.Item label="Пауза между запросами (сек)" name="sleep" initialValue={0.5}>
                    <InputNumber min={0} max={30} step={0.5} style={{ width: '100%' }} />
                  </Form.Item>
                  <Button type="primary" htmlType="submit" loading={loading}>
                    Импортировать новые
                  </Button>
                </Form>
              ),
            },
          ]}
        />

        {output ? (
          <Card size="small" title="Вывод" style={{ marginTop: 16 }}>
            <pre style={{ whiteSpace: 'pre-wrap', margin: 0 }}>{output}</pre>
          </Card>
        ) : null}
      </Card>
    </div>
  )
}
