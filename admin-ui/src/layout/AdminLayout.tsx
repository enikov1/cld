import {
  BookOutlined,
  BankOutlined,
  CloudSyncOutlined,
  CodeOutlined,
  CommentOutlined,
  DashboardOutlined,
  ExportOutlined,
  FolderOpenOutlined,
  HistoryOutlined,
  LayoutOutlined,
  LogoutOutlined,
  MenuOutlined,
  MoonOutlined,
  SearchOutlined,
  SettingOutlined,
  SmileOutlined,
  SunOutlined,
  TeamOutlined,
  VideoCameraOutlined,
  WarningOutlined,
} from '@ant-design/icons'
import { Button, Layout, Menu, Space, Tooltip, Typography } from 'antd'
import type { MenuProps } from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { api } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import AdminCacheControl from '../components/AdminCacheControl'
import AdminCronControl from '../components/AdminCronControl'
import AdminSystemControl from '../components/AdminSystemControl'
import { ADMIN_ROUTES, pageKeyFromPath, pageMeta } from '../routes/adminRoutes'
import type { AdminPageKey, AdminStats } from '../types'
import { siteOrigin } from '../utils/mediaUrl'

const { Sider, Header, Content } = Layout

type AdminLayoutProps = {
  isDark: boolean
  onToggleTheme: () => void
}

const SITE_MENU_KEY = 'open-site'

function formatCount(value: number | null | undefined): string {
  if (value == null) return '…'
  return new Intl.NumberFormat('ru-RU').format(value)
}

function MenuLabel({ text, count }: { text: string; count?: number | null }) {
  return (
    <span className="admin-menu-label">
      <span className="admin-menu-label__text">{text}</span>
      {count !== undefined ? (
        <span className="admin-menu-label__count">{formatCount(count)}</span>
      ) : null}
    </span>
  )
}

function buildMenuItems(stats: AdminStats | null): MenuProps['items'] {
  const origin = siteOrigin() || '/'

  return [
    { key: 'dashboard', icon: <DashboardOutlined />, label: 'Обзор' },
    {
      key: SITE_MENU_KEY,
      icon: <ExportOutlined />,
      label: (
        <a href={origin} target="_blank" rel="noopener noreferrer">
          На сайт
        </a>
      ),
    },
    { type: 'divider' },
    { key: 'nav-menu', icon: <MenuOutlined />, label: 'Меню' },
    { key: 'home-sections', icon: <LayoutOutlined />, label: 'Секции главной' },
    { key: 'reactions', icon: <SmileOutlined />, label: 'Реакции' },
    { key: 'taxonomy', icon: <BookOutlined />, label: 'Справочники' },
    {
      key: 'series',
      icon: <VideoCameraOutlined />,
      label: <MenuLabel text="Сериалы" count={stats?.series_total ?? null} />,
    },
    { key: 'collections', icon: <FolderOpenOutlined />, label: 'Подборки' },
    { key: 'studios', icon: <BankOutlined />, label: 'Студии' },
    { type: 'divider' },
    {
      key: 'comments',
      icon: <CommentOutlined />,
      label: <MenuLabel text="Комментарии" count={stats?.comments_total ?? null} />,
    },
    {
      key: 'player-reports',
      icon: <WarningOutlined />,
      label: <MenuLabel text="Жалобы" count={stats?.player_reports_total ?? null} />,
    },
    { key: 'cron-runs', icon: <HistoryOutlined />, label: 'История задач' },
    {
      key: 'users',
      icon: <TeamOutlined />,
      label: <MenuLabel text="Пользователи" count={stats?.users_total ?? null} />,
    },
    { key: 'search-stats', icon: <SearchOutlined />, label: 'Поиск' },
    { key: 'settings', icon: <SettingOutlined />, label: 'Настройки' },
    { key: 'templates', icon: <CodeOutlined />, label: 'Шаблоны' },
    { key: 'sync', icon: <CloudSyncOutlined />, label: 'KinoPoisk' },
    { key: 'alloha-sync', icon: <CloudSyncOutlined />, label: 'Alloha' },
  ]
}

export default function AdminLayout({ isDark, onToggleTheme }: AdminLayoutProps) {
  const location = useLocation()
  const navigate = useNavigate()
  const { logout, tokenRequired } = useAuth()
  const [stats, setStats] = useState<AdminStats | null>(null)

  const page = pageKeyFromPath(location.pathname)
  const meta = pageMeta[page]
  const menuItems = useMemo(() => buildMenuItems(stats), [stats])

  useEffect(() => {
    let cancelled = false
    api<AdminStats>('/api/admin/stats')
      .then((data) => {
        if (!cancelled) setStats(data)
      })
      .catch(() => {
        /* меню работает и без счётчиков */
      })
    return () => {
      cancelled = true
    }
  }, [location.pathname])

  return (
    <Layout className="admin-shell">
      <Sider
        className="admin-sider"
        width={240}
        breakpoint="lg"
        collapsedWidth={72}
        theme="dark"
      >
        <div className="admin-brand">
          <div className="admin-brand__logo">LS</div>
          <div className="admin-brand__text">
            <strong>LordSerial</strong>
            <span>Панель управления</span>
          </div>
        </div>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[page]}
          items={menuItems}
          onClick={({ key }) => {
            if (key === SITE_MENU_KEY) {
              return
            }
            navigate(ADMIN_ROUTES[key as AdminPageKey])
          }}
        />
      </Sider>

      <Layout>
        <Header className="admin-header">
          <div className="admin-header__titles">
            <Typography.Title level={3} className="admin-header__title">
              {meta.title}
            </Typography.Title>
            {meta.subtitle ? (
              <Typography.Text type="secondary">{meta.subtitle}</Typography.Text>
            ) : null}
          </div>
          <div className="admin-header__extra">
            <Space wrap>
              <AdminCronControl />
              <AdminSystemControl />
              <AdminCacheControl />
              <Tooltip title={isDark ? 'Светлая тема' : 'Тёмная тема'}>
                <Button
                  type="text"
                  icon={isDark ? <SunOutlined /> : <MoonOutlined />}
                  onClick={onToggleTheme}
                  aria-label="Переключить тему"
                />
              </Tooltip>
              {tokenRequired ? (
                <Tooltip title="Выйти">
                  <Button type="text" icon={<LogoutOutlined />} onClick={logout} aria-label="Выйти" />
                </Tooltip>
              ) : null}
            </Space>
          </div>
        </Header>

        <Content className="admin-content">
          <Outlet />
        </Content>
      </Layout>
    </Layout>
  )
}
