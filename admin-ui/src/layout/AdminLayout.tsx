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
  FileSearchOutlined,
  FolderOpenOutlined,
  HistoryOutlined,
  KeyOutlined,
  LayoutOutlined,
  LogoutOutlined,
  MenuOutlined,
  MoonOutlined,
  PictureOutlined,
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
import { Button, Layout, Menu, Space, Tag, Tooltip, Typography } from 'antd'
import type { MenuProps } from 'antd'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
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
  media: MENU_GROUPS.content,
  collections: MENU_GROUPS.content,
  studios: MENU_GROUPS.content,
  taxonomy: MENU_GROUPS.content,
  'nav-menu': MENU_GROUPS.site,
  'home-sections': MENU_GROUPS.site,
  reactions: MENU_GROUPS.site,
  templates: MENU_GROUPS.site,
  'tpl-docs': MENU_GROUPS.site,
  comments: MENU_GROUPS.moderation,
  reviews: MENU_GROUPS.moderation,
  'player-reports': MENU_GROUPS.moderation,
  users: MENU_GROUPS.moderation,
  'search-stats': MENU_GROUPS.moderation,
  'views-stats': MENU_GROUPS.moderation,
  settings: MENU_GROUPS.system,
  redirects: MENU_GROUPS.system,
  'cron-runs': MENU_GROUPS.system,
  backup: MENU_GROUPS.system,
  'admin-access': MENU_GROUPS.system,
  'audit-log': MENU_GROUPS.system,
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

function buildMenuItems(stats: AdminStats | null, allowedPages: Set<string>): MenuProps['items'] {
  const origin = siteOrigin() || '/'
  const allow = (key: string) => allowedPages.has(key)

  const filterChildren = (children: NonNullable<MenuProps['items']>) =>
    children.filter((item) => item && 'key' in item && allow(String(item.key)))

  const contentChildren = filterChildren([
    {
      key: 'series',
      icon: <VideoCameraOutlined />,
      label: <MenuLabel text="Сериалы" count={stats?.series_total ?? null} />,
    },
    { key: 'media', icon: <PictureOutlined />, label: 'Медиатека' },
    { key: 'collections', icon: <FolderOpenOutlined />, label: 'Подборки' },
    { key: 'studios', icon: <BankOutlined />, label: 'Студии' },
    { key: 'taxonomy', icon: <BookOutlined />, label: 'Справочники' },
  ])

  const siteChildren = filterChildren([
    { key: 'nav-menu', icon: <MenuOutlined />, label: 'Меню' },
    { key: 'home-sections', icon: <LayoutOutlined />, label: 'Секции главной' },
    { key: 'reactions', icon: <SmileOutlined />, label: 'Реакции' },
    { key: 'templates', icon: <CodeOutlined />, label: 'Шаблоны' },
    { key: 'tpl-docs', icon: <BookOutlined />, label: 'TPL-DOC' },
  ])

  const moderationChildren = filterChildren([
    {
      key: 'comments',
      icon: <CommentOutlined />,
      label: <MenuLabel text="Комментарии" count={stats?.comments_total ?? null} />,
    },
    {
      key: 'reviews',
      icon: <CommentOutlined />,
      label: <MenuLabel text="Рецензии" count={stats == null ? null : stats.reviews_pending > 0 ? stats.reviews_pending : undefined} />,
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
  ])

  const systemChildren = filterChildren([
    { key: 'settings', icon: <SettingOutlined />, label: 'Настройки' },
    { key: 'redirects', icon: <SwapOutlined />, label: 'Редиректы' },
    { key: 'cron-runs', icon: <HistoryOutlined />, label: 'История задач' },
    { key: 'backup', icon: <DatabaseOutlined />, label: 'Бэкапы' },
    { key: 'admin-access', icon: <KeyOutlined />, label: 'Токены' },
    { key: 'audit-log', icon: <FileSearchOutlined />, label: 'Аудит' },
  ])

  const integrationsChildren = filterChildren([
    { key: 'sync', icon: <CloudSyncOutlined />, label: 'KinoPoisk' },
    { key: 'alloha-sync', icon: <CloudSyncOutlined />, label: 'Alloha' },
    { key: 'rutube-sync', icon: <CloudSyncOutlined />, label: 'Rutube' },
  ])

  const items: NonNullable<MenuProps['items']> = []
  if (allow('dashboard')) {
    items.push({ key: 'dashboard', icon: <DashboardOutlined />, label: 'Обзор' })
  }
  items.push({
    key: SITE_MENU_KEY,
    icon: <ExportOutlined />,
    label: (
      <a href={origin} target="_blank" rel="noopener noreferrer">
        На сайт
      </a>
    ),
  })

  const pushGroup = (key: string, icon: ReactNode, label: string, children: NonNullable<MenuProps['items']>) => {
    if (children.length === 0) return
    items.push({ key, icon, label, children })
  }

  pushGroup(MENU_GROUPS.content, <AppstoreOutlined />, 'Контент', contentChildren)
  pushGroup(MENU_GROUPS.site, <LayoutOutlined />, 'Сайт', siteChildren)
  pushGroup(MENU_GROUPS.moderation, <TeamOutlined />, 'Модерация', moderationChildren)
  pushGroup(MENU_GROUPS.system, <ControlOutlined />, 'Система', systemChildren)
  pushGroup(MENU_GROUPS.integrations, <ToolOutlined />, 'Интеграции', integrationsChildren)

  return items
}

const ROLE_LABELS: Record<string, string> = {
  full: 'Полный',
  content: 'Контент',
  moderation: 'Модерация',
  custom: 'Свой',
}

export default function AdminLayout({ isDark, onToggleTheme }: AdminLayoutProps) {
  const location = useLocation()
  const navigate = useNavigate()
  const { logout, tokenRequired, me } = useAuth()
  const [stats, setStats] = useState<AdminStats | null>(null)

  const page = pageKeyFromPath(location.pathname)
  const meta = pageMeta[page]
  const allowedPages = useMemo(() => new Set(me?.pages ?? []), [me])
  const abilities = useMemo(() => new Set(me?.abilities ?? []), [me])
  const canAbility = (key: string) =>
    me?.actor_type === 'master' || abilities.has('*') || abilities.has(key) || abilities.has(key.split('.')[0] ?? '')
  const menuItems = useMemo(() => buildMenuItems(stats, allowedPages), [stats, allowedPages])
  const activeGroup = PAGE_GROUP[page]
  const [openKeys, setOpenKeys] = useState<string[]>(activeGroup ? [activeGroup] : [])

  useBaseDocumentTitle(meta.title)

  useEffect(() => {
    if (!activeGroup) return
    setOpenKeys((prev) => (prev.includes(activeGroup) ? prev : [...prev, activeGroup]))
  }, [activeGroup])

  useEffect(() => {
    if (allowedPages.size === 0) return
    if (allowedPages.has(page)) return
    const first = me?.pages?.[0]
    navigate(first ? ADMIN_ROUTES[first as AdminPageKey] ?? '/' : '/', { replace: true })
  }, [allowedPages, page, navigate, me])

  useEffect(() => {
    let cancelled = false
    const apply = (partial: Partial<AdminStats>) => {
      if (!cancelled) {
        setStats((prev) => ({ ...(prev ?? ({} as AdminStats)), ...partial }))
      }
    }

    if (canAbility('admin.stats')) {
      api<AdminStats>('/api/admin/stats')
        .then((data) => {
          if (!cancelled) setStats(data)
        })
        .catch(() => {
          /* меню работает и без счётчиков */
        })
    } else if (
      allowedPages.has('reviews') ||
      allowedPages.has('comments') ||
      allowedPages.has('player-reports')
    ) {
      api<{ comments_pending: number; reviews_pending: number; player_reports_total: number }>(
        '/api/admin/moderation-counts',
      )
        .then((data) => {
          apply({
            comments_pending: data.comments_pending,
            reviews_pending: data.reviews_pending,
            player_reports_total: data.player_reports_total,
          })
        })
        .catch(() => {
          /* меню работает и без счётчиков */
        })
    } else {
      setStats(null)
    }

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- canAbility/allowedPages derived from me
  }, [me])

  if ((me?.pages?.length ?? 0) === 0) {
    return (
      <div className="admin-auth-loading">
        <Typography.Paragraph>
          У этого токена нет доступных разделов. Обратитесь к администратору или выйдите и войдите с другим токеном.
        </Typography.Paragraph>
        <Button type="primary" onClick={() => void logout()}>
          Выйти
        </Button>
      </div>
    )
  }

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
            {canAbility('admin.search') ? <AdminGlobalSearch /> : null}
          </div>
          <div className="admin-header__extra">
            <Space wrap>
              {me?.role ? (
                <Tooltip title={me.name || 'Текущий доступ'}>
                  <Tag
                    color={
                      me.role === 'full'
                        ? 'gold'
                        : me.role === 'content'
                          ? 'blue'
                          : me.role === 'moderation'
                            ? 'purple'
                            : 'cyan'
                    }
                  >
                    {ROLE_LABELS[me.role] || me.role}
                  </Tag>
                </Tooltip>
              ) : null}
              {canAbility('admin.cron') ? <AdminCronControl /> : null}
              {canAbility('admin.system') ? <AdminSystemControl /> : null}
              {canAbility('admin.cache') ? <AdminCacheControl /> : null}
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
                  <Button type="text" icon={<LogoutOutlined />} onClick={() => void logout()} aria-label="Выйти" />
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
