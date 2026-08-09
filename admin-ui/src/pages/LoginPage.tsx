import { LockOutlined } from '@ant-design/icons'
import { Alert, Button, Card, Form, Input, Typography } from 'antd'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { useBaseDocumentTitle, useBusyFavicon } from '../documentMeta/AdminDocumentMeta'
import { useState } from 'react'

export default function LoginPage() {
  const { status, login } = useAuth()
  const location = useLocation()
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  useBaseDocumentTitle('Вход')
  useBusyFavicon(loading)

  if (status === 'authenticated') {
    const from = (location.state as { from?: string } | null)?.from ?? '/'
    return <Navigate to={from} replace />
  }

  async function onFinish(values: { token: string }) {
    setLoading(true)
    setError(null)
    try {
      await login(values.token)
    } catch (e) {
      setError(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="admin-login">
      <Card className="admin-login__card">
        <div className="admin-login__brand">
          <div className="admin-login__logo">LS</div>
          <div>
            <Typography.Title level={3} style={{ margin: 0 }}>
              LordSerial Admin
            </Typography.Title>
          </div>
        </div>

        {error ? <Alert type="error" message={error} showIcon style={{ marginBottom: 16 }} /> : null}

        <Form layout="vertical" onFinish={onFinish}>
          <Form.Item
            label="Токен"
            name="token"
            rules={[{ required: true, message: 'Укажите токен из .env (ADMIN_TOKEN)' }]}
          >
            <Input.Password prefix={<LockOutlined />} placeholder="ADMIN_TOKEN" autoComplete="off" />
          </Form.Item>
          <Button type="primary" htmlType="submit" block loading={loading}>
            Войти
          </Button>
        </Form>
      </Card>
    </div>
  )
}
