import { EyeOutlined } from '@ant-design/icons'
import { Card, Col, Row, Statistic, Typography, message } from 'antd'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { ADMIN_ROUTES } from '../routes/adminRoutes'
import type { AdminStats } from '../types'

export default function DashboardPage() {
  const [stats, setStats] = useState<AdminStats | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)

  useEffect(() => {
    api<AdminStats>('/api/admin/stats')
      .then((data) => {
        setStats(data)
        setLoadError(false)
      })
      .catch((e) => {
        setStats(null)
        setLoadError(true)
        message.error(String((e as Error).message || 'Не удалось загрузить статистику'))
      })
      .finally(() => setLoading(false))
  }, [])

  const views = stats?.views

  return (
    <div>
      <Typography.Paragraph type="secondary" style={{ marginBottom: 20 }}>
        Сводка по данным из базы. Контент добавляется через KinoPoisk sync или вручную в разделах ниже.
      </Typography.Paragraph>

      {loadError ? (
        <Typography.Paragraph type="danger">Не удалось загрузить статистику. Показаны нули.</Typography.Paragraph>
      ) : null}

      <Typography.Title level={5} style={{ marginTop: 0 }}>
        Просмотры · <Link to={ADMIN_ROUTES['views-stats']}>подробнее</Link>
      </Typography.Title>
      <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сегодня" value={views?.views_today ?? 0} prefix={<EyeOutlined />} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="7 дней" value={views?.views_7d ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="30 дней" value={views?.views_30d ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сериалов с просмотрами сегодня" value={views?.series_active_today ?? 0} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Просмотров за всё время" value={views?.views_total ?? 0} />
          </Card>
        </Col>
      </Row>

      <Typography.Title level={5}>Контент и модерация</Typography.Title>
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Сериалы" value={stats?.series_total ?? 0} suffix={`/ ${stats?.series_active ?? 0} акт.`} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Подборки" value={stats?.collections ?? 0} suffix={`/ ${stats?.collections_active ?? 0} акт.`} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Студии" value={stats?.studios ?? 0} suffix={`/ ${stats?.studios_active ?? 0} акт.`} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic
              title="Комментарии на модерации"
              value={stats?.comments_pending ?? 0}
              suffix={`из ${stats?.comments_total ?? 0}`}
              valueStyle={stats?.comments_pending ? { color: '#fa8c16' } : undefined}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic
              title="Жалобы на плеер"
              value={stats?.player_reports_today ?? 0}
              suffix={`сегодня / ${stats?.player_reports_total ?? 0} всего`}
              valueStyle={stats?.player_reports_today ? { color: '#cf1322' } : undefined}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic
              title="Пользователи"
              value={stats?.users_total ?? 0}
              suffix={stats?.users_blocked ? `/ ${stats.users_blocked} заблок.` : undefined}
            />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="С плеером" value={stats?.series_with_player ?? 0} suffix={`/ ${stats?.series_total ?? 0}`} />
          </Card>
        </Col>
        <Col xs={24} sm={12} lg={6}>
          <Card loading={loading}>
            <Statistic title="Активный шаблон" value={stats?.active_theme ?? '—'} valueStyle={{ fontSize: 20 }} />
          </Card>
        </Col>
      </Row>

      <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
        <Col xs={24} md={12}>
          <Card title="Быстрые действия">
            <Typography.Paragraph>
              <Link to={ADMIN_ROUTES.series}>Управление сериалами</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.collections}>Подборки</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.studios}>Студии</Link>
            </Typography.Paragraph>
            <Typography.Paragraph>
              <Link to={ADMIN_ROUTES['nav-menu']}>Меню</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.templates}>Шаблоны</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.settings}>Настройки</Link>
            </Typography.Paragraph>
            <Typography.Paragraph>
              <Link to={ADMIN_ROUTES['views-stats']}>Просмотры</Link>
              {' · '}
              <Link to={ADMIN_ROUTES['search-stats']}>Статистика поиска</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.comments}>Модерация комментариев</Link>
              {' · '}
              <Link to={ADMIN_ROUTES['player-reports']}>Жалобы на плеер</Link>
            </Typography.Paragraph>
            <Typography.Paragraph>
              <Link to={ADMIN_ROUTES.users}>Пользователи сайта</Link>
              {' · '}
              <Link to={ADMIN_ROUTES.sync}>KinoPoisk</Link>
              {' · '}
              <Link to={ADMIN_ROUTES['alloha-sync']}>Alloha</Link>
              {' · '}
              <Link to={ADMIN_ROUTES['rutube-sync']}>Rutube</Link>
            </Typography.Paragraph>
            <Typography.Paragraph style={{ marginBottom: 0 }}>
              <Link to={ADMIN_ROUTES.backup}>Бэкапы</Link>
            </Typography.Paragraph>
          </Card>
        </Col>
        <Col xs={24} md={12}>
          <Card title="Порядок наполнения">
            <Typography.Paragraph>1. Создайте категории с URL-slug.</Typography.Paragraph>
            <Typography.Paragraph>2. Импортируйте сериалы через KinoPoisk или добавьте вручную.</Typography.Paragraph>
            <Typography.Paragraph>3. Укажите URL плеера в карточке сериала или фильма.</Typography.Paragraph>
            <Typography.Paragraph style={{ marginBottom: 0 }}>4. Соберите подборки и проверьте настройки шаблона.</Typography.Paragraph>
          </Card>
        </Col>
      </Row>
    </div>
  )
}
