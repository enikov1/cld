import { Alert, Button, Card, Checkbox, Divider, Form, Input, InputNumber, Modal, Select, Space, Switch, Table, Tag, Typography, message } from 'antd'
import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../api/client'

type IntervalOption = { value: number; label: string }

type BackupSettings = {
  enabled: boolean
  interval_minutes: number
  include_database: boolean
  include_files: boolean
  remote_enabled: boolean
  protocol: 'ftp' | 'sftp' | 's3'
  host: string
  port: number
  username: string
  remote_path: string
  s3_key: string
  s3_region: string
  s3_bucket: string
  s3_endpoint: string
  s3_path_style: boolean
  retention_count: number
  passive: boolean
}

type LocalBackup = {
  name: string
  size: number
  created_at: string
  source?: 'local' | 'remote'
}

type RestoreTarget = {
  name: string
  source: 'local' | 'remote'
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

export default function BackupPage() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [running, setRunning] = useState(false)
  const [intervalOptions, setIntervalOptions] = useState<IntervalOption[]>([])
  const [lastRunHuman, setLastRunHuman] = useState<string | null>(null)
  const [passwordSet, setPasswordSet] = useState(false)
  const [s3SecretSet, setS3SecretSet] = useState(false)
  const [remoteConfigured, setRemoteConfigured] = useState(false)
  const [backups, setBackups] = useState<LocalBackup[]>([])
  const [restoreTarget, setRestoreTarget] = useState<RestoreTarget | null>(null)
  const [restoring, setRestoring] = useState(false)
  const [restoreDatabase, setRestoreDatabase] = useState(true)
  const [restoreFiles, setRestoreFiles] = useState(true)
  const [restoreConfirmToken, setRestoreConfirmToken] = useState('')
  const [form] = Form.useForm()

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{
        settings: BackupSettings
        interval_options: IntervalOption[]
        last_run_human?: string | null
        remote_password_set?: boolean
        s3_secret_set?: boolean
        remote_configured?: boolean
        local_backups?: LocalBackup[]
        backups?: LocalBackup[]
      }>('/api/admin/backup/settings')

      setIntervalOptions(data.interval_options ?? [])
      setLastRunHuman(data.last_run_human ?? null)
      setPasswordSet(Boolean(data.remote_password_set))
      setS3SecretSet(Boolean(data.s3_secret_set))
      setRemoteConfigured(Boolean(data.remote_configured))
      setBackups(data.backups ?? data.local_backups ?? [])
      form.setFieldsValue(data.settings)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [form])

  useEffect(() => {
    void load()
  }, [load])

  async function saveSettings(values: BackupSettings & { password?: string; s3_secret?: string }) {
    setSaving(true)
    try {
      const payload = { ...values }
      if (!payload.password) {
        delete payload.password
      }
      if (!payload.s3_secret) {
        delete payload.s3_secret
      }

      await api('/api/admin/backup/settings', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success('Настройки бэкапа сохранены')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  async function testConnection() {
    setTesting(true)
    try {
      const values = form.getFieldsValue() as BackupSettings & { password?: string; s3_secret?: string }
      await api('/api/admin/backup/settings', {
        method: 'POST',
        body: JSON.stringify({
          ...values,
          password: values.password || undefined,
          s3_secret: values.s3_secret || undefined,
        }),
      })
      const res = await api<{ ok: boolean; message?: string; error?: string }>(
        '/api/admin/backup/test-connection',
        { method: 'POST' },
      )
      message.success(res.message || 'Подключение успешно')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setTesting(false)
    }
  }

  async function runBackup() {
    setRunning(true)
    try {
      const res = await api<{ ok: boolean; message?: string }>('/api/admin/backup/run', {
        method: 'POST',
      })
      message.success(res.message || 'Бэкап создан')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setRunning(false)
    }
  }

  function openRestoreModal(target: RestoreTarget) {
    setRestoreTarget(target)
    setRestoreDatabase(true)
    setRestoreFiles(true)
    setRestoreConfirmToken('')
  }

  async function confirmRestore() {
    if (!restoreTarget) return
    if (!restoreDatabase && !restoreFiles) {
      message.warning('Выберите, что восстанавливать')
      return
    }
    if (!restoreConfirmToken.trim()) {
      message.warning('Введите ADMIN_TOKEN для подтверждения')
      return
    }

    setRestoring(true)
    try {
      const res = await api<{ ok: boolean; message?: string }>('/api/admin/backup/restore', {
        method: 'POST',
        body: JSON.stringify({
          name: restoreTarget.name,
          source: restoreTarget.source,
          restore_database: restoreDatabase,
          restore_files: restoreFiles,
          confirm_token: restoreConfirmToken.trim(),
        }),
      })
      message.success(res.message || 'Восстановление завершено')
      setRestoreTarget(null)
      setRestoreConfirmToken('')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setRestoring(false)
    }
  }

  const protocol = Form.useWatch('protocol', form) as BackupSettings['protocol'] | undefined
  const remoteEnabled = Form.useWatch('remote_enabled', form) as boolean | undefined
  const isS3 = protocol === 's3'

  function handleProtocolChange(value: BackupSettings['protocol']) {
    if (value === 's3') {
      const currentPath = String(form.getFieldValue('remote_path') ?? '')
      if (currentPath === '' || currentPath.startsWith('/')) {
        form.setFieldValue('remote_path', 'backups')
      }
      return
    }

    const currentPort = form.getFieldValue('port') as number
    if (value === 'sftp' && (currentPort === 21 || !currentPort)) {
      form.setFieldValue('port', 22)
    }
    if (value === 'ftp' && (currentPort === 22 || !currentPort)) {
      form.setFieldValue('port', 21)
    }

    const currentPath = String(form.getFieldValue('remote_path') ?? '')
    if (currentPath === 'backups' || currentPath === '') {
      form.setFieldValue('remote_path', '/backups')
    }
  }

  return (
    <div>
      <Card title="Резервное копирование" loading={loading}>
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 16 }}
          message="Автоматически создаёт архив базы данных и файлов сайта (загрузки и шаблоны), затем отправляет на удалённый сервер по FTP/SFTP или в S3-хранилище."
        />

        <Form
          form={form}
          layout="vertical"
          onFinish={saveSettings}
          style={{ maxWidth: 720 }}
          initialValues={{
            enabled: false,
            interval_minutes: 360,
            include_database: true,
            include_files: true,
            remote_enabled: false,
            protocol: 'sftp',
            port: 22,
            remote_path: '/backups',
            s3_key: '',
            s3_region: '',
            s3_bucket: '',
            s3_endpoint: '',
            s3_path_style: false,
            retention_count: 10,
            passive: true,
          }}
        >
          <Typography.Paragraph type="secondary">
            По умолчанию — каждые 6 часов.
            {lastRunHuman ? ` Последний запуск: ${lastRunHuman}.` : ' Ещё не запускалось.'}
          </Typography.Paragraph>

          <Form.Item label="Включить автобэкап" name="enabled" valuePropName="checked">
            <Switch />
          </Form.Item>

          <Form.Item label="Интервал" name="interval_minutes">
            <Select options={intervalOptions} />
          </Form.Item>

          <Form.Item label="Включать базу данных" name="include_database" valuePropName="checked">
            <Switch />
          </Form.Item>

          <Form.Item
            label="Включать файлы сайта"
            name="include_files"
            valuePropName="checked"
            extra="storage/app/public (постеры, брендинг) и resources/tpl (шаблоны)"
          >
            <Switch />
          </Form.Item>

          <Form.Item
            label="Хранить копий (локально и на сервере)"
            name="retention_count"
            extra="Старые архивы удаляются автоматически"
          >
            <InputNumber min={1} max={100} style={{ width: '100%' }} />
          </Form.Item>

          <Divider>Удалённое хранилище</Divider>

          <Form.Item label="Отправлять на удалённый сервер" name="remote_enabled" valuePropName="checked">
            <Switch />
          </Form.Item>

          {remoteEnabled ? (
            <>
              <Form.Item label="Тип хранилища" name="protocol">
                <Select
                  options={[
                    { value: 's3', label: 'S3 (AWS, Yandex, MinIO и др.)' },
                    { value: 'sftp', label: 'SFTP (SSH)' },
                    { value: 'ftp', label: 'FTP' },
                  ]}
                  onChange={handleProtocolChange}
                />
              </Form.Item>

              {isS3 ? (
                <>
                  <Form.Item
                    label="Access Key ID"
                    name="s3_key"
                    rules={[{ required: true, message: 'Укажите Access Key' }]}
                  >
                    <Input autoComplete="off" />
                  </Form.Item>

                  <Form.Item
                    label="Secret Access Key"
                    name="s3_secret"
                    extra={s3SecretSet ? 'Ключ сохранён. Оставьте пустым, чтобы не менять.' : 'Обязателен для S3'}
                  >
                    <Input.Password autoComplete="new-password" placeholder={s3SecretSet ? '••••••••' : ''} />
                  </Form.Item>

                  <Form.Item
                    label="Регион"
                    name="s3_region"
                    rules={[{ required: true, message: 'Укажите регион' }]}
                    extra="Например: us-east-1, ru-central1"
                  >
                    <Input placeholder="ru-central1" />
                  </Form.Item>

                  <Form.Item
                    label="Bucket"
                    name="s3_bucket"
                    rules={[{ required: true, message: 'Укажите bucket' }]}
                    extra="Имя контейнера из панели S3 (например Adman). Должен уже существовать."
                  >
                    <Input placeholder="my-backups" />
                  </Form.Item>

                  <Form.Item
                    label="Префикс (папка в bucket)"
                    name="remote_path"
                    extra="Папка внутри бакета, не имя бакета. Например: backups"
                  >
                    <Input placeholder="backups" />
                  </Form.Item>

                  <Form.Item
                    label="Endpoint (опционально)"
                    name="s3_endpoint"
                    extra="Для Adman: https://s3.adman.com. Для Yandex: https://storage.yandexcloud.net"
                  >
                    <Input placeholder="https://s3.adman.com" />
                  </Form.Item>

                  <Form.Item
                    label="Path-style адресация"
                    name="s3_path_style"
                    valuePropName="checked"
                    extra="Для Adman/MinIO обязательна. При указанном Endpoint включается автоматически."
                  >
                    <Switch />
                  </Form.Item>
                </>
              ) : (
                <>
                  <Form.Item label="Хост" name="host" rules={[{ required: true, message: 'Укажите хост' }]}>
                    <Input placeholder="backup.example.com" />
                  </Form.Item>

                  <Form.Item label="Порт" name="port">
                    <InputNumber min={1} max={65535} style={{ width: '100%' }} />
                  </Form.Item>

                  <Form.Item label="Логин" name="username" rules={[{ required: true, message: 'Укажите логин' }]}>
                    <Input autoComplete="off" />
                  </Form.Item>

                  <Form.Item
                    label="Пароль"
                    name="password"
                    extra={passwordSet ? 'Пароль сохранён. Оставьте пустым, чтобы не менять.' : 'Обязателен для отправки на сервер'}
                  >
                    <Input.Password autoComplete="new-password" placeholder={passwordSet ? '••••••••' : ''} />
                  </Form.Item>

                  <Form.Item
                    label="Путь на сервере"
                    name="remote_path"
                    extra="Каталог, куда складывать архивы"
                  >
                    <Input placeholder="/backups" />
                  </Form.Item>

                  {protocol === 'ftp' ? (
                    <Form.Item label="Пассивный режим FTP" name="passive" valuePropName="checked">
                      <Switch />
                    </Form.Item>
                  ) : null}
                </>
              )}

              <Alert
                type={remoteConfigured ? 'success' : 'warning'}
                showIcon
                style={{ marginBottom: 16 }}
                message={
                  remoteConfigured
                    ? 'Удалённое хранилище настроено'
                    : isS3
                      ? 'Заполните Access Key, Secret Key, регион и bucket'
                      : 'Заполните хост, логин и пароль для отправки бэкапов'
                }
              />
            </>
          ) : null}

          <Space wrap>
            <Button type="primary" htmlType="submit" loading={saving}>
              Сохранить настройки
            </Button>
            {remoteEnabled ? (
              <Button onClick={() => void testConnection()} loading={testing}>
                Проверить подключение
              </Button>
            ) : null}
            <Button onClick={() => void runBackup()} loading={running}>
              Создать бэкап сейчас
            </Button>
            <Button type="link" onClick={() => navigate('/cron-runs')}>
              История задач
            </Button>
          </Space>
        </Form>

        {backups.length > 0 ? (
          <>
            <Divider />
            <Typography.Title level={5}>Доступные архивы</Typography.Title>
            <Alert
              type="warning"
              showIcon
              style={{ marginBottom: 12 }}
              message="Восстановление перезапишет текущую базу данных и/или файлы сайта. Рекомендуется сначала создать свежий бэкап."
            />
            <Table
              size="small"
              rowKey={(row) => `${row.source ?? 'local'}:${row.name}`}
              pagination={false}
              dataSource={backups}
              columns={[
                { title: 'Файл', dataIndex: 'name', key: 'name' },
                {
                  title: 'Источник',
                  dataIndex: 'source',
                  key: 'source',
                  render: (source: LocalBackup['source']) =>
                    source === 'remote' ? <Tag color="blue">Удалённый</Tag> : <Tag>Локальный</Tag>,
                },
                {
                  title: 'Размер',
                  dataIndex: 'size',
                  key: 'size',
                  render: (size: number) => (size > 0 ? formatBytes(size) : '—'),
                },
                {
                  title: 'Создан',
                  dataIndex: 'created_at',
                  key: 'created_at',
                  render: (value: string) =>
                    new Date(value).toLocaleString('ru-RU', {
                      day: '2-digit',
                      month: '2-digit',
                      year: 'numeric',
                      hour: '2-digit',
                      minute: '2-digit',
                    }),
                },
                {
                  title: '',
                  key: 'actions',
                  render: (_value, row) => (
                    <Button
                      size="small"
                      danger
                      onClick={() =>
                        openRestoreModal({
                          name: row.name,
                          source: row.source ?? 'local',
                        })
                      }
                    >
                      Восстановить
                    </Button>
                  ),
                },
              ]}
            />
          </>
        ) : null}

        <Modal
          title="Восстановление из бэкапа"
          open={restoreTarget !== null}
          onCancel={() => {
            setRestoreTarget(null)
            setRestoreConfirmToken('')
          }}
          onOk={() => void confirmRestore()}
          okText="Восстановить"
          cancelText="Отмена"
          okButtonProps={{ danger: true, loading: restoring, disabled: !restoreConfirmToken.trim() }}
          confirmLoading={restoring}
        >
          {restoreTarget ? (
            <Space direction="vertical" style={{ width: '100%' }}>
              <Alert
                type="warning"
                showIcon
                message="Опасная операция"
                description="Восстановление перезапишет базу и/или файлы сайта. Потребуется повторный ввод ADMIN_TOKEN."
              />
              <Typography.Text>
                Архив: <Typography.Text code>{restoreTarget.name}</Typography.Text>
              </Typography.Text>
              <Typography.Text type="secondary">
                Источник: {restoreTarget.source === 'remote' ? 'удалённый сервер' : 'локальный диск'}
              </Typography.Text>
              <Checkbox checked={restoreDatabase} onChange={(e) => setRestoreDatabase(e.target.checked)}>
                Восстановить базу данных
              </Checkbox>
              <Checkbox checked={restoreFiles} onChange={(e) => setRestoreFiles(e.target.checked)}>
                Восстановить файлы (загрузки и шаблоны)
              </Checkbox>
              <Input.Password
                value={restoreConfirmToken}
                onChange={(e) => setRestoreConfirmToken(e.target.value)}
                placeholder="ADMIN_TOKEN для подтверждения"
                autoComplete="off"
              />
            </Space>
          ) : null}
        </Modal>
      </Card>
    </div>
  )
}
