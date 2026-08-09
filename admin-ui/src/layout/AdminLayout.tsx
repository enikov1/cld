import {
  AppstoreOutlined,
  BookOutlined,
  BankOutlined,
  CloudSyncOutlined,
  DatabaseOutlined,
  CodeOutlined,
  CommentOutlined,
  ControlOutlined,
  DashboardOutlined,
  ExportOutlined,
  FolderOpenOutlined,
  HistoryOutlined,
  LayoutOutlined,
  LogoutOutlined,
  MenuOutlined,
  MoonOutlined,
  SwapOutlined,
  SearchOutlined,
  SettingOutlined,
  SmileOutlined,
  SunOutlined,
  TeamOutlined,
  ToolOutlined,
  EyeOutlined,
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
import AdminGlobalSearch from '../components/AdminGlobalSearch'
import AdminSystemControl from '../components/AdminSystemControl'
import { useBaseDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { ADMIN_ROUTES, pageKeyFromPath, pageMeta } from '../routes/adminRoutes'
import type { AdminPageKey, AdminStats } from '../types'
import { siteOrigin } from '../utils/mediaUrl'

const { Sider, Header, Content } = Layout

type AdminLayoutProps = {
  isDark: boolean
  onToggleTheme: () => void
}

const SITE_MENU_KEY = 'open-site'

const MENU_GROUPS = {
  content: 'grp-content',
  site: 'grp-site',
  moderation: 'grp-moderation',
  system: 'grp-system',
  integrations: 'grp-integrations',
} as const

const PAGE_GROUP: Partial<Record<AdminPageKey, string>> = {
  series: MENU_GROUPS.content,
  collections: MENU_GROUPS.content,
  studios: MENU_GROUPS.content,
  taxonomy: MENU_GROUPS.content,
  'nav-menu': MENU_GROUPS.site,
  'home-sections': MENU_GROUPS.site,
  reactions: MENU_GROUPS.site,
  templates: MENU_GROUPS.site,
  comments: MENU_GROUPS.moderation,
  'player-reports': MENU_GROUPS.moderation,
  users: MENU_GROUPS.moderation,
  'search-stats': MENU_GROUPS.moderation,
  'views-stats': MENU_GROUPS.moderation,
  settings: MENU_GROUPS.system,
  redirects: MENU_GROUPS.system,
  'cron-runs': MENU_GROUPS.system,
  backup: MENU_GROUPS.system,
  sync: MENU_GROUPS.integrations,
  'alloha-sync': MENU_GROUPS.integrations,
  'rutube-sync': MENU_GROUPS.integrations,
}

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
    {
      key: MENU_GROUPS.content,
      icon: <AppstoreOutlined />,
      label: 'Контент',
      children: [
        {
          key: 'series',
          icon: <VideoCameraOutlined />,
          label: <MenuLabel text="Сериалы" count={stats?.series_total ?? null} />,
        },
        { key: 'collections', icon: <FolderOpenOutlined />, label: 'Подборки' },
        { key: 'studios', icon: <BankOutlined />, label: 'Студии' },
        { key: 'taxonomy', icon: <BookOutlined />, label: 'Справочники' },
      ],
    },
    {
      key: MENU_GROUPS.site,
      icon: <LayoutOutlined />,
      label: 'Сайт',
      children: [
        { key: 'nav-menu', icon: <MenuOutlined />, label: 'Меню' },
        { key: 'home-sections', icon: <LayoutOutlined />, label: 'Секции главной' },
        { key: 'reactions', icon: <SmileOutlined />, label: 'Реакции' },
        { key: 'templates', icon: <CodeOutlined />, label: 'Шаблоны' },
      ],
    },
    {
      key: MENU_GROUPS.moderation,
      icon: <TeamOutlined />,
      label: 'Модерация',
      children: [
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
        {
          key: 'users',
          icon: <TeamOutlined />,
          label: <MenuLabel text="Пользователи" count={stats?.users_total ?? null} />,
        },
        { key: 'search-stats', icon: <SearchOutlined />, label: 'Поиск' },
        { key: 'views-stats', icon: <EyeOutlined />, label: 'Просмотры' },
      ],
    },
    {
      key: MENU_GROUPS.system,
      icon: <ControlOutlined />,
      label: 'Система',
      children: [
        { key: 'settings', icon: <SettingOutlined />, label: 'Настройки' },
        { key: 'redirects', icon: <SwapOutlined />, label: 'Редиректы' },
        { key: 'cron-runs', icon: <HistoryOutlined />, label: 'История задач' },
        { key: 'backup', icon: <DatabaseOutlined />, label: 'Бэкапы' },
      ],
    },
    {
      key: MENU_GROUPS.integrations,
      icon: <ToolOutlined />,
      label: 'Интеграции',
      children: [
        { key: 'sync', icon: <CloudSyncOutlined />, label: 'KinoPoisk' },
        { key: 'alloha-sync', icon: <CloudSyncOutlined />, label: 'Alloha' },
        { key: 'rutube-sync', icon: <CloudSyncOutlined />, label: 'Rutube' },
      ],
    },
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
  const activeGroup = PAGE_GROUP[page]
  const [openKeys, setOpenKeys] = useState<string[]>(activeGroup ? [activeGroup] : [])

  useBaseDocumentTitle(meta.title)

  useEffect(() => {
    if (!activeGroup) return
    setOpenKeys((prev) => (prev.includes(activeGroup) ? prev : [...prev, activeGroup]))
  }, [activeGroup])

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
  }, [])

  return (
    <Layout className="admin-shell">
      <Sider
        className="admin-sider"
        width={240}
        breakpoint="lg"
        collapsedWidth={72}
        theme={isDark ? 'dark' : 'light'}
      >
        <div className="admin-brand">
          <div className="admin-brand__logo">LS</div>
          <div className="admin-brand__text">
            <strong>LordSerial</strong>
            <span>Панель управления</span>
          </div>
        </div>
        <Menu
          theme={isDark ? 'dark' : 'light'}
          mode="inline"
          selectedKeys={[page]}
          openKeys={openKeys}
          onOpenChange={setOpenKeys}
          items={menuItems}
          onClick={({ key }) => {
            if (key === SITE_MENU_KEY || key.startsWith('grp-')) {
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
          <div className="admin-header__search">
            <AdminGlobalSearch />
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
