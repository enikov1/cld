import { ConfigProvider, Spin, theme } from 'antd'
import ruRU from 'antd/locale/ru_RU'
import { Suspense, lazy } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth/AuthContext'
import AdminErrorBoundary from './components/AdminErrorBoundary'
import AdminLayout from './layout/AdminLayout'
import LoginPage from './pages/LoginPage'
import { useAdminTheme } from './theme/useAdminTheme'

const DashboardPage = lazy(() => import('./pages/DashboardPage'))
const NavMenuPage = lazy(() => import('./pages/NavMenuPage'))
const HomeSectionsPage = lazy(() => import('./pages/HomeSectionsPage'))
const ReactionsPage = lazy(() => import('./pages/ReactionsPage'))
const TaxonomyPage = lazy(() => import('./pages/TaxonomyPage'))
const SeriesPage = lazy(() => import('./pages/SeriesPage'))
const CollectionsPage = lazy(() => import('./pages/CollectionsPage'))
const StudiosPage = lazy(() => import('./pages/StudiosPage'))
const CommentsPage = lazy(() => import('./pages/CommentsPage'))
const PlayerReportsPage = lazy(() => import('./pages/PlayerReportsPage'))
const CronRunsPage = lazy(() => import('./pages/CronRunsPage'))
const UsersPage = lazy(() => import('./pages/UsersPage'))
const SearchStatsPage = lazy(() => import('./pages/SearchStatsPage'))
const RedirectsPage = lazy(() => import('./pages/RedirectsPage'))
const SettingsPage = lazy(() => import('./pages/SettingsPage'))
const TemplatesPage = lazy(() => import('./pages/TemplatesPage'))
const KinoPoiskSyncPage = lazy(() => import('./pages/KinoPoiskSyncPage'))
const AllohaSyncPage = lazy(() => import('./pages/AllohaSyncPage'))
const BackupPage = lazy(() => import('./pages/BackupPage'))

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

function PageFallback() {
  return (
    <div style={{ display: 'flex', justifyContent: 'center', padding: 48 }}>
      <Spin size="large" />
    </div>
  )
}

function AppRoutes({ isDark, onToggleTheme }: AppRoutesProps) {
  return (
    <Suspense fallback={<PageFallback />}>
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
          <Route path="redirects" element={<RedirectsPage />} />
          <Route path="settings" element={<SettingsPage />} />
          <Route path="templates" element={<TemplatesPage />} />
          <Route path="sync" element={<KinoPoiskSyncPage />} />
          <Route path="alloha-sync" element={<AllohaSyncPage />} />
          <Route path="backup" element={<BackupPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </Suspense>
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
          <AdminErrorBoundary>
            <AppRoutes isDark={isDark} onToggleTheme={toggle} />
          </AdminErrorBoundary>
        </AuthProvider>
      </BrowserRouter>
    </ConfigProvider>
  )
}
