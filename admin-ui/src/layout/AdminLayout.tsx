import {
  BookOutlined,
  BankOutlined,
  CloudSyncOutlined,
  CodeOutlined,
  CommentOutlined,
  DashboardOutlined,
  FolderOpenOutlined,
  LogoutOutlined,
  MenuOutlined,
  MoonOutlined,
  SearchOutlined,
  SettingOutlined,
  SmileOutlined,
  SunOutlined,
  TeamOutlined,
  VideoCameraOutlined,
} from '@ant-design/icons'
import { Button, Layout, Menu, Space, Tooltip, Typography } from 'antd'
import type { MenuProps } from 'antd'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { ADMIN_ROUTES, pageKeyFromPath, pageMeta } from '../routes/adminRoutes'
import type { AdminPageKey } from '../types'

const { Sider, Header, Content } = Layout

type AdminLayoutProps = {
  isDark: boolean
  onToggleTheme: () => void
}

const menuItems: MenuProps['items'] = [
  { key: 'dashboard', icon: <DashboardOutlined />, label: 'Обзор' },
  { type: 'divider' },
  { key: 'nav-menu', icon: <MenuOutlined />, label: 'Меню' },
  { key: 'reactions', icon: <SmileOutlined />, label: 'Реакции' },
  { key: 'taxonomy', icon: <BookOutlined />, label: 'Справочники' },
  { key: 'series', icon: <VideoCameraOutlined />, label: 'Сериалы' },
  { key: 'collections', icon: <FolderOpenOutlined />, label: 'Подборки' },
  { key: 'studios', icon: <BankOutlined />, label: 'Студии' },
  { type: 'divider' },
  { key: 'comments', icon: <CommentOutlined />, label: 'Комментарии' },
  { key: 'users', icon: <TeamOutlined />, label: 'Пользователи' },
  { key: 'search-stats', icon: <SearchOutlined />, label: 'Поиск' },
  { key: 'settings', icon: <SettingOutlined />, label: 'Настройки' },
  { key: 'templates', icon: <CodeOutlined />, label: 'Шаблоны' },
  { key: 'sync', icon: <CloudSyncOutlined />, label: 'KinoPoisk' },
  { key: 'alloha-sync', icon: <CloudSyncOutlined />, label: 'Alloha' },
]

export default function AdminLayout({ isDark, onToggleTheme }: AdminLayoutProps) {
  const location = useLocation()
  const navigate = useNavigate()
  const { logout, tokenRequired } = useAuth()

  const page = pageKeyFromPath(location.pathname)
  const meta = pageMeta[page]

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
          onClick={({ key }) => navigate(ADMIN_ROUTES[key as AdminPageKey])}
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
            <Space>
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
