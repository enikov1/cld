import { ConfigProvider, theme } from 'antd'
import ruRU from 'antd/locale/ru_RU'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthContext'
import AdminLayout from './layout/AdminLayout'
import CollectionsPage from './pages/CollectionsPage'
import StudiosPage from './pages/StudiosPage'
import CommentsPage from './pages/CommentsPage'
import PlayerReportsPage from './pages/PlayerReportsPage'
import CronRunsPage from './pages/CronRunsPage'
import DashboardPage from './pages/DashboardPage'
import NavMenuPage from './pages/NavMenuPage'
import KinoPoiskSyncPage from './pages/KinoPoiskSyncPage'
import AllohaSyncPage from './pages/AllohaSyncPage'
import BackupPage from './pages/BackupPage'
import HomeSectionsPage from './pages/HomeSectionsPage'
import LoginPage from './pages/LoginPage'
import TaxonomyPage from './pages/TaxonomyPage'
import ReactionsPage from './pages/ReactionsPage'
import SearchStatsPage from './pages/SearchStatsPage'
import SeriesPage from './pages/SeriesPage'
import SettingsPage from './pages/SettingsPage'
import TemplatesPage from './pages/TemplatesPage'
import UsersPage from './pages/UsersPage'
import { useAdminTheme } from './theme/useAdminTheme'

type AppProps = {
  basename: string
}

type AppRoutesProps = {
  isDark: boolean
  onToggleTheme: () => void
}

function ProtectedLayout({ isDark, onToggleTheme }: AppRoutesProps) {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'login') {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <AdminLayout isDark={isDark} onToggleTheme={onToggleTheme} />
}

function AppRoutes({ isDark, onToggleTheme }: AppRoutesProps) {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<ProtectedLayout isDark={isDark} onToggleTheme={onToggleTheme} />}>
        <Route index element={<DashboardPage />} />
        <Route path="nav-menu" element={<NavMenuPage />} />
        <Route path="categories" element={<Navigate to="/taxonomy" replace />} />
        <Route path="home-sections" element={<HomeSectionsPage />} />
        <Route path="reactions" element={<ReactionsPage />} />
        <Route path="taxonomy" element={<TaxonomyPage />} />
        <Route path="series" element={<SeriesPage />} />
        <Route path="collections" element={<CollectionsPage />} />
        <Route path="studios" element={<StudiosPage />} />
        <Route path="comments" element={<CommentsPage />} />
        <Route path="player-reports" element={<PlayerReportsPage />} />
        <Route path="cron-runs" element={<CronRunsPage />} />
        <Route path="users" element={<UsersPage />} />
        <Route path="search-stats" element={<SearchStatsPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="templates" element={<TemplatesPage />} />
        <Route path="sync" element={<KinoPoiskSyncPage />} />
        <Route path="alloha-sync" element={<AllohaSyncPage />} />
        <Route path="backup" element={<BackupPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  )
}

export default function App({ basename }: AppProps) {
  const { isDark, toggle } = useAdminTheme()

  return (
    <ConfigProvider
      locale={ruRU}
      theme={{
        algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
        token: {
          colorPrimary: '#1677ff',
          borderRadius: 8,
        },
      }}
    >
      <BrowserRouter basename={basename}>
        <AuthProvider>
          <AppRoutes isDark={isDark} onToggleTheme={toggle} />
        </AuthProvider>
      </BrowserRouter>
    </ConfigProvider>
  )
}
