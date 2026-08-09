import { Alert, Button, Card, Form, Input, InputNumber, Switch, Typography, message } from 'antd'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon } from '../documentMeta/AdminDocumentMeta'

export default function KinoPoiskSyncPage() {
  const [kinopoiskConfigured, setKinopoiskConfigured] = useState<boolean | null>(null)
  const [loading, setLoading] = useState(false)
  const [output, setOutput] = useState('')
  const [form] = Form.useForm()

  useBusyFavicon(loading)
  const loadSettings = useCallback(async () => {
    const settingsData = await api<{ kinopoisk_api_key_set?: boolean }>('/api/admin/settings')
    setKinopoiskConfigured(Boolean(settingsData.kinopoisk_api_key_set))
  }, [])

  useEffect(() => {
    loadSettings().catch((e) => message.error(String((e as Error).message)))
  }, [loadSettings])

  async function runSync(values: Record<string, unknown>) {
    setLoading(true)
    setOutput('')
    try {
      const res = await api<{ ok: boolean; output: string }>('/api/admin/sync/kp', {
        method: 'POST',
        body: JSON.stringify(values),
      })
      setOutput(res.output || 'Готово')
      message.success('Синхронизация завершена')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Card title="Импорт из KinoPoisk API">
        <Alert
          type={kinopoiskConfigured ? 'info' : 'warning'}
          showIcon
          style={{ marginBottom: 16 }}
          message={
            kinopoiskConfigured
              ? 'API-ключ KinoPoisk настроен. Данные сохраняются в базу — mock-контент не используется.'
              : 'Укажите API-ключ KinoPoisk в разделе Настройки. Данные сохраняются в базу — mock-контент не используется.'
          }
        />

        <Form form={form} layout="vertical" onFinish={runSync} initialValues={{ limit: 20, download_poster: true }}>
          <Form.Item label="Поисковый запрос" name="keyword" rules={[{ required: true, message: 'Введите название сериала' }]}>
            <Input placeholder="Например: извне, даттон" />
          </Form.Item>
          <Form.Item label="Лимит результатов" name="limit">
            <InputNumber min={1} max={50} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Скачать постеры на сервер" name="download_poster" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Button type="primary" htmlType="submit" loading={loading}>
            Запустить kp:sync
          </Button>
        </Form>
      </Card>

      {output ? (
        <Card title="Вывод команды" style={{ marginTop: 16 }}>
          <Typography.Paragraph>
            <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: 13 }}>{output}</pre>
          </Typography.Paragraph>
        </Card>
      ) : null}
    </div>
  )
}
