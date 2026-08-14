import {
  Alert,
  Button,
  Card,
  Col,
  ColorPicker,
  Form,
  Input,
  InputNumber,
  Row,
  Select,
  Space,
  Switch,
  Tabs,
  Typography,
  Upload,
  message,
  Popconfirm,
  Progress,
} from 'antd'
import {
  ApiOutlined,
  AppstoreOutlined,
  CommentOutlined,
  DollarOutlined,
  GlobalOutlined,
  HomeOutlined,
  LikeOutlined,
  LockOutlined,
  MessageOutlined,
  PauseCircleOutlined,
  PlayCircleOutlined,
  SkinOutlined,
  StopOutlined,
  TagOutlined,
  ThunderboltOutlined,
  ToolOutlined,
  UserOutlined,
} from '@ant-design/icons'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, apiUpload } from '../api/client'
import BrandImageEditorModal from '../components/BrandImageEditorModal'
import SiteConfigFields from '../components/SiteConfigFields'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import type { SettingItem, ThemeItem } from '../types'
import type { SiteConfigField, SiteConfigSchema } from '../types/siteConfig'
import { resolveCropperImageUrl } from '../utils/mediaUrl'

type SettingsSection =
  | 'branding'
  | 'theme'
  | 'home'
  | 'seo'
  | 'auth'
  | 'comments'
  | 'reviews'
  | 'ratings'
  | 'engagement'
  | 'catalog'
  | 'optimization'
  | 'advertising'
  | 'maintenance'
  | 'general'
  | 'admin'
  | 'moderation'
  | 'integrations'

const SECTION_LABELS: Record<SettingsSection, string> = {
  branding: 'Брендинг',
  theme: 'Шаблон сайта',
  home: 'Главная страница',
  seo: 'SEO',
  auth: 'Авторизация',
  comments: 'Комментарии',
  reviews: 'Рецензии',
  ratings: 'Рейтинги',
  engagement: 'Списки и уведомления',
  catalog: 'Каталог',
  optimization: 'Оптимизация',
  advertising: 'Реклама',
  maintenance: 'Обслуживание',
  general: 'Общие',
  admin: 'Админ-панель',
  moderation: 'Модерация',
  integrations: 'Интеграции',
}

const CATALOG_TABS = [
  { key: 'catalog', label: 'Каталог' },
  { key: 'calendar', label: 'Календарь' },
  { key: 'coming_soon', label: 'Скоро' },
  { key: 'search', label: 'Поиск' },
  { key: 'collections', label: 'Подборки' },
  { key: 'studios', label: 'Студии' },
  { key: 'home', label: 'Главная' },
  { key: 'nav', label: 'Меню' },
] as const

type CatalogTabKey = (typeof CATALOG_TABS)[number]['key']

function catalogTabForField(key: string): CatalogTabKey {
  if (key.startsWith('calendar_')) return 'calendar'
  if (key.startsWith('coming_soon_')) return 'coming_soon'
  if (key.startsWith('search_')) return 'search'
  if (key.startsWith('collections_')) return 'collections'
  if (key.startsWith('studios_') || key.startsWith('home_studios_')) return 'studios'
  if (key.startsWith('home_')) return 'home'
  if (key.startsWith('nav_')) return 'nav'
  return 'catalog'
}

function groupCatalogFields(fields: SiteConfigField[]) {
  const groups: Partial<Record<CatalogTabKey, SiteConfigField[]>> = {}
  for (const field of fields) {
    const tab = catalogTabForField(field.key)
    groups[tab] ??= []
    groups[tab].push(field)
  }

  return CATALOG_TABS
    .filter((tab) => (groups[tab.key] ?? []).length > 0)
    .map((tab) => ({ ...tab, fields: groups[tab.key] ?? [] }))
}

function sectionFromHash(): SettingsSection {
  const hash = window.location.hash.replace(/^#/, '')
  const section = hash.split('/')[0]
  if (section in SECTION_LABELS) {
    return section as SettingsSection
  }
  return 'branding'
}

function catalogTabFromHash(): CatalogTabKey {
  const hash = window.location.hash.replace(/^#/, '')
  const tab = hash.split('/')[1]
  return CATALOG_TABS.some((item) => item.key === tab) ? (tab as CatalogTabKey) : 'catalog'
}

const SEO_TABS = [
  { key: 'robots', label: 'robots.txt' },
  { key: 'verification', label: 'Верификация' },
  { key: 'counters', label: 'Счётчики' },
  { key: 'pages', label: 'Страницы' },
  { key: 'series', label: 'Сериал' },
  { key: 'ai', label: 'Промпты ИИ' },
  { key: 'sitemap', label: 'sitemap.xml' },
] as const

type SeoTabKey = (typeof SEO_TABS)[number]['key']

function seoTabForField(key: string): SeoTabKey {
  if (key === 'robots_txt') return 'robots'
  if (key === 'sitemap') return 'sitemap'
  if (key.includes('verification')) return 'verification'
  if (key.includes('counters')) return 'counters'
  if (key.includes('ai_prompt')) return 'ai'
  if (key.includes('share_widget') || key.startsWith('series_ui_')) return 'series'
  return 'pages'
}

function seoTabFromHash(): SeoTabKey {
  const hash = window.location.hash.replace(/^#/, '')
  const tab = hash.split('/')[1]
  return SEO_TABS.some((item) => item.key === tab) ? (tab as SeoTabKey) : 'robots'
}

const COMMENTS_TABS = [
  { key: 'general', label: 'Основные' },
  { key: 'limits', label: 'Лимиты' },
  { key: 'messages', label: 'Сообщения' },
  { key: 'ui', label: 'Интерфейс' },
] as const

type CommentsTabKey = (typeof COMMENTS_TABS)[number]['key']

function sectionHash(
  section: SettingsSection,
  catalogTab: CatalogTabKey,
  seoTab: SeoTabKey,
  commentsTab: CommentsTabKey,
  reviewsTab: ReviewsTabKey,
): string {
  if (section === 'catalog') return `catalog/${catalogTab}`
  if (section === 'seo') return `seo/${seoTab}`
  if (section === 'comments') return `comments/${commentsTab}`
  if (section === 'reviews') return `reviews/${reviewsTab}`
  return section
}

function commentsTabForField(key: string): CommentsTabKey {
  if (key.startsWith('comments_msg_')) return 'messages'
  if (key.startsWith('comments_ui_') || key.startsWith('comments_label_')) return 'ui'
  if (key.includes('_length') || key.includes('_depth') || key === 'profile_comments_limit') return 'limits'
  return 'general'
}

function groupCommentsFields(fields: SiteConfigField[]) {
  const groups: Partial<Record<CommentsTabKey, SiteConfigField[]>> = {}
  for (const field of fields) {
    const tab = commentsTabForField(field.key)
    groups[tab] ??= []
    groups[tab].push(field)
  }

  return COMMENTS_TABS
    .filter((tab) => (groups[tab.key] ?? []).length > 0)
    .map((tab) => ({ ...tab, fields: groups[tab.key] ?? [] }))
}

const REVIEWS_TABS = [
  { key: 'general', label: 'Основные' },
  { key: 'limits', label: 'Лимиты' },
  { key: 'messages', label: 'Сообщения' },
  { key: 'ui', label: 'Интерфейс' },
] as const

type ReviewsTabKey = (typeof REVIEWS_TABS)[number]['key']

function reviewsTabForField(key: string): ReviewsTabKey {
  if (key.startsWith('reviews_msg_')) return 'messages'
  if (key.startsWith('reviews_ui_') || key.startsWith('reviews_label_')) return 'ui'
  if (key.includes('_length') || key === 'profile_reviews_limit' || key === 'reviews_list_limit') return 'limits'
  return 'general'
}

function groupReviewsFields(fields: SiteConfigField[]) {
  const groups: Partial<Record<ReviewsTabKey, SiteConfigField[]>> = {}
  for (const field of fields) {
    const tab = reviewsTabForField(field.key)
    groups[tab] ??= []
    groups[tab].push(field)
  }

  return REVIEWS_TABS
    .filter((tab) => (groups[tab.key] ?? []).length > 0)
    .map((tab) => ({ ...tab, fields: groups[tab.key] ?? [] }))
}

function reviewsTabFromHash(): ReviewsTabKey {
  const hash = window.location.hash.replace(/^#/, '')
  const tab = hash.split('/')[1]
  return REVIEWS_TABS.some((item) => item.key === tab) ? (tab as ReviewsTabKey) : 'general'
}

function commentsTabFromHash(): CommentsTabKey {
  const hash = window.location.hash.replace(/^#/, '')
  const tab = hash.split('/')[1]
  return COMMENTS_TABS.some((item) => item.key === tab) ? (tab as CommentsTabKey) : 'general'
}

export default function SettingsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [settings, setSettings] = useState<SettingItem[]>([])
  const [themes, setThemes] = useState<ThemeItem[]>([])
  const [activeTheme, setActiveTheme] = useState('')
  const [kinopoiskApiKeySet, setKinopoiskApiKeySet] = useState(false)
  const [allohaApiTokenSet, setAllohaApiTokenSet] = useState(false)
  const [tmdbApiKeySet, setTmdbApiKeySet] = useState(false)
  const [tmdbAutoSyncEnabled, setTmdbAutoSyncEnabled] = useState(false)
  const [tmdbLastRunAt, setTmdbLastRunAt] = useState<string | null>(null)
  const [tmdbSyncing, setTmdbSyncing] = useState(false)
  const [cdnSyncing, setCdnSyncing] = useState(false)
  const [cdnProgress, setCdnProgress] = useState<{
    status: string
    total: number
    processed: number
    synced: number
    skipped: number
    failed: number
    message: string
  } | null>(null)
  const [cdnPercent, setCdnPercent] = useState(0)
  const cdnAbortRef = useRef(false)
  const cdnLoopActiveRef = useRef(false)
  const [robotsDefault, setRobotsDefault] = useState('')
  const [robotsEffective, setRobotsEffective] = useState('')
  const [robotsUrl, setRobotsUrl] = useState('/robots.txt')
  const [sitemapUrl, setSitemapUrl] = useState('/sitemap.xml')
  const [sitemapUrlCount, setSitemapUrlCount] = useState<number | null>(null)
  const [sitemapLastModifiedAt, setSitemapLastModifiedAt] = useState<string | null>(null)
  const [sitemapDirty, setSitemapDirty] = useState(false)
  const [sitemapGenerating, setSitemapGenerating] = useState(false)
  const [configSchema, setConfigSchema] = useState<SiteConfigSchema>({})
  const [configSeoFields, setConfigSeoFields] = useState<SiteConfigSchema>({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [bgEditor, setBgEditor] = useState<{
    src: string
    fileName: string
    revokeUrl?: string
  } | null>(null)
  const savingRef = useRef(false)
  const [section, setSection] = useState<SettingsSection>(sectionFromHash)
  const [catalogTab, setCatalogTab] = useState<CatalogTabKey>(catalogTabFromHash)
  const [seoTab, setSeoTab] = useState<SeoTabKey>(seoTabFromHash)
  const [commentsTab, setCommentsTab] = useState<CommentsTabKey>(commentsTabFromHash)
  const [reviewsTab, setReviewsTab] = useState<ReviewsTabKey>(reviewsTabFromHash)
  const [tabPosition, setTabPosition] = useState<'left' | 'top'>('left')
  const [form] = Form.useForm()
  const highlightField = searchParams.get('highlight')?.trim() || ''

  useDocumentTitle(`Настройки — ${SECTION_LABELS[section]}`)
  useBusyFavicon(loading || tmdbSyncing || cdnSyncing || cdnProgress?.status === 'running' || sitemapGenerating)

  const settingsMap = useMemo(() => {
    const m: Record<string, string> = {}
    settings.forEach((s) => { m[s.key] = s.value ?? '' })
    return m
  }, [settings])

  const themeOptions = useMemo(
    () => themes.map((t) => ({ value: t.name, label: t.label })),
    [themes],
  )

  const adminPath = (settingsMap.admin_path || 'admin').replace(/^\/+|\/+$/g, '')

  const boolKeys = useMemo(() => {
    const keys = new Set<string>(['comments_auto_approve', 'reviews_auto_approve', 'site_background_hide_mobile'])
    Object.values(configSchema).forEach((group) => {
      group.fields.forEach((field) => {
        if (field.type === 'bool') keys.add(field.key)
      })
    })
    Object.values(configSeoFields).forEach((group) => {
      group.fields.forEach((field) => {
        if (field.type === 'bool') keys.add(field.key)
      })
    })
    return keys
  }, [configSchema, configSeoFields])

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [settingsData, themesData, tmdbAutoData, sitemapData] = await Promise.all([
        api<{
          items: SettingItem[]
          kinopoisk_api_key_set?: boolean
          alloha_api_token_set?: boolean
          tmdb_api_key_set?: boolean
          robots_txt_default?: string
          robots_txt_effective?: string
          robots_url?: string
          config_schema?: SiteConfigSchema
          config_seo_fields?: SiteConfigSchema
        }>('/api/admin/settings'),
        api<{ items: ThemeItem[]; active: string }>('/api/admin/themes'),
        api<{ settings?: { enabled?: boolean }; last_run_at?: string | null }>('/api/admin/tmdb/auto-sync'),
        api<{
          url?: string
          url_count?: number
          last_modified_at?: string | null
          is_dirty?: boolean
        }>('/api/admin/sitemap'),
      ])
      setSettings(settingsData.items)
      setKinopoiskApiKeySet(Boolean(settingsData.kinopoisk_api_key_set))
      setAllohaApiTokenSet(Boolean(settingsData.alloha_api_token_set))
      setTmdbApiKeySet(Boolean(settingsData.tmdb_api_key_set))
      setTmdbAutoSyncEnabled(Boolean(tmdbAutoData.settings?.enabled))
      setTmdbLastRunAt(tmdbAutoData.last_run_at ?? null)
      setRobotsDefault(settingsData.robots_txt_default ?? '')
      setRobotsEffective(settingsData.robots_txt_effective ?? '')
      setRobotsUrl(settingsData.robots_url ?? '/robots.txt')
      setSitemapUrl(sitemapData.url ?? '/sitemap.xml')
      setSitemapUrlCount(sitemapData.url_count ?? null)
      setSitemapLastModifiedAt(sitemapData.last_modified_at ?? null)
      setSitemapDirty(Boolean(sitemapData.is_dirty))
      setConfigSchema(settingsData.config_schema ?? {})
      setConfigSeoFields(settingsData.config_seo_fields ?? {})
      setThemes(themesData.items)
      setActiveTheme(themesData.active)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const loadCdnProgress = useCallback(async () => {
    const res = await api<{
      progress: {
        status: string
        total: number
        processed: number
        synced: number
        skipped: number
        failed: number
        message: string
      }
      percent: number
    }>('/api/admin/players/cdnvideohub/sync-progress')
    setCdnProgress(res.progress)
    setCdnPercent(res.percent ?? 0)
    return res
  }, [])

  const runCdnLoop = useCallback(async (restart: boolean) => {
    if (cdnLoopActiveRef.current) return
    cdnLoopActiveRef.current = true
    setCdnSyncing(true)
    cdnAbortRef.current = false
    let nextRestart = restart

    try {
      while (!cdnAbortRef.current) {
        const res = await api<{
          ok: boolean
          done?: boolean
          paused?: boolean
          stopped?: boolean
          percent?: number
          message?: string
          progress?: {
            status: string
            total: number
            processed: number
            synced: number
            skipped: number
            failed: number
            message: string
          }
          synced?: number
          skipped?: number
        }>('/api/admin/players/cdnvideohub/sync-all', {
          method: 'POST',
          body: JSON.stringify({
            restart: nextRestart,
            continue: !nextRestart,
            batch_size: 100,
          }),
        })

        nextRestart = false
        if (res.progress) setCdnProgress(res.progress)
        setCdnPercent(res.percent ?? 0)

        if (res.paused || res.progress?.status === 'paused') {
          message.info(res.message || 'Задача на паузе')
          break
        }
        if (res.stopped || res.progress?.status === 'stopped') {
          message.warning(res.message || 'Задача остановлена')
          break
        }
        if (res.done) {
          message.success(res.message || `Готово: ${res.synced ?? 0}, пропущено: ${res.skipped ?? 0}`)
          break
        }
      }
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      cdnLoopActiveRef.current = false
      setCdnSyncing(false)
      try {
        await loadCdnProgress()
      } catch {
        /* optional */
      }
    }
  }, [loadCdnProgress])

  useEffect(() => {
    loadCdnProgress().catch(() => {
      /* no running job */
    })
    return () => {
      cdnAbortRef.current = true
    }
  }, [loadCdnProgress])

  useEffect(() => {
    const values: Record<string, unknown> = {
      site_name: settingsMap.site_name,
      site_tagline: settingsMap.site_tagline,
      footer_text: settingsMap.footer_text,
      site_background_header_offset: Number(settingsMap.site_background_header_offset || 200),
      site_background_color: settingsMap.site_background_color || '#111',
      site_background_hide_mobile: settingsMap.site_background_hide_mobile === '1',
      home_heading: settingsMap.home_heading,
      home_lead: settingsMap.home_lead,
      home_seo_html: settingsMap.home_seo_html,
      robots_txt: settingsMap.robots_txt,
      active_theme: settingsMap.active_theme || activeTheme || themes[0]?.name,
      kinopoisk_api_key: '',
      alloha_api_token: '',
      tmdb_api_key: '',
      admin_path: settingsMap.admin_path || 'admin',
      comments_auto_approve: settingsMap.comments_auto_approve === '1',
      reviews_auto_approve: settingsMap.reviews_auto_approve === '1',
    }

    const applyFields = (schema: SiteConfigSchema) => {
      Object.values(schema).forEach((group) => {
        group.fields.forEach((field) => {
          const stored = settingsMap[field.key]
          if (field.type === 'bool') {
            values[field.key] = stored !== undefined && stored !== ''
              ? stored === '1'
              : field.default === '1'
          } else if (field.type === 'int') {
            values[field.key] = stored !== undefined && stored !== ''
              ? Number(stored)
              : Number(field.default ?? field.min ?? 0)
          } else {
            values[field.key] = stored !== undefined && stored !== ''
              ? stored
              : (field.default ?? '')
          }
        })
      })
    }

    applyFields(configSchema)
    applyFields(configSeoFields)
    form.setFieldsValue(values)
  }, [settingsMap, activeTheme, themes, form, configSchema, configSeoFields])

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 768px)')
    const update = () => setTabPosition(mq.matches ? 'top' : 'left')
    update()
    mq.addEventListener('change', update)
    return () => mq.removeEventListener('change', update)
  }, [])

  useEffect(() => {
    const onHashChange = () => {
      setSection(sectionFromHash())
      setCatalogTab(catalogTabFromHash())
      setSeoTab(seoTabFromHash())
      setCommentsTab(commentsTabFromHash())
      setReviewsTab(reviewsTabFromHash())
    }
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [])

  useEffect(() => {
    if (!highlightField) {
      return
    }

    const catalogKeys = new Set((configSchema.catalog?.fields ?? []).map((field) => field.key))
    if (catalogKeys.has(highlightField)) {
      const nextTab = catalogTabForField(highlightField)
      if (nextTab !== catalogTab) {
        setCatalogTab(nextTab)
      }
    }

    const seoKeys = new Set(
      Object.values(configSeoFields).flatMap((group) => group.fields.map((field) => field.key)),
    )
    seoKeys.add('robots_txt')
    seoKeys.add('sitemap')
    if (seoKeys.has(highlightField)) {
      const nextTab = seoTabForField(highlightField)
      if (nextTab !== seoTab) {
        setSeoTab(nextTab)
      }
    }

    const commentsKeys = new Set((configSchema.comments?.fields ?? []).map((field) => field.key))
    if (commentsKeys.has(highlightField)) {
      const nextTab = commentsTabForField(highlightField)
      if (nextTab !== commentsTab) {
        setCommentsTab(nextTab)
      }
    }

    const reviewsKeys = new Set((configSchema.reviews?.fields ?? []).map((field) => field.key))
    if (reviewsKeys.has(highlightField)) {
      const nextTab = reviewsTabForField(highlightField)
      if (nextTab !== reviewsTab) {
        setReviewsTab(nextTab)
      }
    }
  }, [highlightField, catalogTab, seoTab, commentsTab, reviewsTab, configSchema.catalog?.fields, configSchema.comments?.fields, configSchema.reviews?.fields, configSeoFields])

  useEffect(() => {
    if (!highlightField || loading) {
      return
    }

    let clearHighlight: number | undefined
    const timer = window.setTimeout(() => {
      const target =
        document.querySelector<HTMLElement>(`[data-settings-field="${CSS.escape(highlightField)}"]`)
        ?? document.getElementById(`settings-field-${highlightField}`)
        ?? document.getElementById(highlightField)

      if (!target) {
        return
      }

      const focusNode = (target.closest('.ant-form-item') as HTMLElement | null) ?? target
      focusNode.classList.add('settings-field--highlight')
      focusNode.scrollIntoView({ behavior: 'smooth', block: 'center' })

      clearHighlight = window.setTimeout(() => {
        focusNode.classList.remove('settings-field--highlight')
      }, 3200)

      const next = new URLSearchParams(searchParams)
      next.delete('highlight')
      setSearchParams(next, { replace: true })
    }, 120)

    return () => {
      window.clearTimeout(timer)
      if (clearHighlight) {
        window.clearTimeout(clearHighlight)
      }
    }
  }, [highlightField, loading, section, catalogTab, seoTab, commentsTab, reviewsTab, searchParams, setSearchParams])

  function changeSection(key: string) {
    const next = key as SettingsSection
    setSection(next)
    window.location.hash = sectionHash(next, catalogTab, seoTab, commentsTab, reviewsTab)
  }

  function changeCatalogTab(key: string) {
    const next = key as CatalogTabKey
    setCatalogTab(next)
    window.location.hash = `catalog/${next}`
  }

  function changeSeoTab(key: string) {
    const next = key as SeoTabKey
    setSeoTab(next)
    window.location.hash = `seo/${next}`
  }

  function changeCommentsTab(key: string) {
    const next = key as CommentsTabKey
    setCommentsTab(next)
    window.location.hash = `comments/${next}`
  }

  function changeReviewsTab(key: string) {
    const next = key as ReviewsTabKey
    setReviewsTab(next)
    window.location.hash = `reviews/${next}`
  }

  async function save(values: Record<string, unknown>) {
    if (savingRef.current) return
    savingRef.current = true
    setSaving(true)
    const prevPath = settingsMap.admin_path || 'admin'
    const allValues = form.getFieldsValue(true) as Record<string, unknown>
    const merged = { ...allValues, ...values }
    try {
      await api('/api/admin/settings', {
        method: 'POST',
        body: JSON.stringify({
          settings: Object.entries(merged).map(([key, value]) => {
            if (boolKeys.has(key)) {
              return { key, value: value ? '1' : '0' }
            }
            return { key, value: String(value ?? '') }
          }),
        }),
      })
      const nextPath = String(merged.admin_path || 'admin').replace(/^\/+|\/+$/g, '')
      if (nextPath !== prevPath.replace(/^\/+|\/+$/g, '')) {
        message.success(`Настройки сохранены. Откройте админку: /${nextPath}/`)
      } else {
        message.success('Настройки сохранены')
      }
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      savingRef.current = false
      setSaving(false)
    }
  }

  const tabItems = [
    {
      key: 'branding',
      label: (
        <span className="settings-tab-label">
          <TagOutlined />
          Брендинг
        </span>
      ),
      children: (
        <Card title="Брендинг сайта" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Название, слоган, логотип, favicon, фон и текст в подвале — отображаются на всех страницах.
          </Typography.Paragraph>
          <Form.Item label="Название сайта" name="site_name">
            <Input placeholder="LordSerial" />
          </Form.Item>
          <Form.Item label="Слоган" name="site_tagline">
            <Input placeholder="Смотреть бесплатно в хорошем качестве" />
          </Form.Item>
          <Form.Item label="Текст в футере" name="footer_text">
            <Input.TextArea rows={3} placeholder="Сериалы онлайн в HD качестве" />
          </Form.Item>

          <Typography.Title level={5} style={{ marginTop: 8 }}>Логотип</Typography.Title>
          <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
            PNG, JPG или SVG, до 2 МБ. Если не загружен — используется логотип из шаблона.
          </Typography.Paragraph>
          {settingsMap.site_logo_url ? (
            <div style={{ marginBottom: 12 }}>
              <img
                src={settingsMap.site_logo_url}
                alt="Логотип"
                style={{ maxHeight: 56, maxWidth: 220, objectFit: 'contain', display: 'block', marginBottom: 8 }}
              />
              <Button
                danger
                size="small"
                onClick={async () => {
                  try {
                    await api('/api/admin/branding/logo', { method: 'DELETE' })
                    message.success('Логотип удалён')
                    await load()
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Удалить логотип
              </Button>
            </div>
          ) : null}
          <Upload
            beforeUpload={async (file) => {
              const fd = new FormData()
              fd.append('logo', file)
              try {
                await apiUpload<{ logo_url: string }>('/api/admin/branding/logo', fd)
                message.success('Логотип загружен')
                await load()
              } catch (e) {
                message.error(String((e as Error).message))
              }
              return false
            }}
            showUploadList={false}
            accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml"
          >
            <Button style={{ marginBottom: 24 }}>Загрузить логотип</Button>
          </Upload>

          <Typography.Title level={5}>Favicon</Typography.Title>
          <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
            PNG, JPG, ICO, SVG или WebP, до 2 МБ. Отображается во вкладке браузера на всех страницах сайта.
          </Typography.Paragraph>
          {settingsMap.site_favicon_url ? (
            <div style={{ marginBottom: 12 }}>
              <img
                src={settingsMap.site_favicon_url}
                alt="Favicon"
                style={{ width: 32, height: 32, objectFit: 'contain', display: 'block', marginBottom: 8 }}
              />
              <Typography.Paragraph type="secondary" style={{ marginBottom: 8, fontSize: 12 }}>
                <code>{settingsMap.site_favicon_url}</code>
              </Typography.Paragraph>
              <Button
                danger
                size="small"
                onClick={async () => {
                  try {
                    await api('/api/admin/branding/favicon', { method: 'DELETE' })
                    message.success('Favicon удалён')
                    await load()
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Удалить favicon
              </Button>
            </div>
          ) : null}
          <Upload
            beforeUpload={async (file) => {
              const fd = new FormData()
              fd.append('favicon', file)
              try {
                await apiUpload<{ favicon_url: string }>('/api/admin/branding/favicon', fd)
                message.success('Favicon загружен')
                await load()
              } catch (e) {
                message.error(String((e as Error).message))
              }
              return false
            }}
            showUploadList={false}
            accept=".png,.jpg,.jpeg,.ico,.svg,.webp,image/png,image/jpeg,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,image/webp"
          >
            <Button style={{ marginBottom: 24 }}>Загрузить favicon</Button>
          </Upload>

          <Typography.Title level={5}>Фон сайта</Typography.Title>
          <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
            PNG или JPG. При загрузке картинка сильно сжимается по размеру (как в онлайн-компрессорах, цель ≈
            90% меньше, максимум 2 МБ). При загруженном фоне шапка сдвигается вниз на заданный отступ.
          </Typography.Paragraph>
          {settingsMap.site_background_url ? (
            <div style={{ marginBottom: 12 }}>
              <img
                src={settingsMap.site_background_url}
                alt="Фон"
                style={{ maxHeight: 120, maxWidth: '100%', objectFit: 'cover', display: 'block', marginBottom: 8, borderRadius: 8 }}
              />
              <Space size={8} wrap>
                <Button
                  size="small"
                  onClick={() => {
                    const src = resolveCropperImageUrl(settingsMap.site_background_url)
                    if (!src) return
                    setBgEditor((prev) => {
                      if (prev?.revokeUrl) URL.revokeObjectURL(prev.revokeUrl)
                      return { src, fileName: 'site-background.jpg' }
                    })
                  }}
                >
                  Подогнать кадр
                </Button>
                <Button
                  danger
                  size="small"
                  onClick={async () => {
                    try {
                      await api('/api/admin/branding/background', { method: 'DELETE' })
                      message.success('Фон удалён')
                      await load()
                    } catch (e) {
                      message.error(String((e as Error).message))
                    }
                  }}
                >
                  Удалить фон
                </Button>
              </Space>
            </div>
          ) : null}
          <Upload
            beforeUpload={(file) => {
              const src = URL.createObjectURL(file)
              setBgEditor((prev) => {
                if (prev?.revokeUrl) URL.revokeObjectURL(prev.revokeUrl)
                return { src, fileName: file.name || 'site-background.jpg', revokeUrl: src }
              })
              return false
            }}
            showUploadList={false}
            accept=".png,.jpg,.jpeg,image/png,image/jpeg"
          >
            <Button style={{ marginBottom: 16 }}>Загрузить фон</Button>
          </Upload>
          <Form.Item
            label="Отступ шапки от верха (px)"
            name="site_background_header_offset"
            extra="Применяется, когда загружен фон. По умолчанию 200 px."
          >
            <InputNumber min={0} max={600} style={{ width: 160 }} />
          </Form.Item>
          <Form.Item
            label="Цвет фона"
            name="site_background_color"
            getValueFromEvent={(color) => {
              if (typeof color === 'string') return color
              return color?.toHexString?.() ?? '#111'
            }}
            extra="Подложка под картинкой фона. По умолчанию #111."
          >
            <ColorPicker showText disabledAlpha format="hex" />
          </Form.Item>
          <Form.Item
            label="Отключить фон на мобильных"
            name="site_background_hide_mobile"
            valuePropName="checked"
            extra="На экранах до 768 px картинка фона и отступ шапки не применяются."
          >
            <Switch checkedChildren="Да" unCheckedChildren="Нет" />
          </Form.Item>
        </Card>
      ),
    },
    {
      key: 'theme',
      label: (
        <span className="settings-tab-label">
          <SkinOutlined />
          Шаблон
        </span>
      ),
      children: (
        <Card title="Шаблон сайта (.tpl)" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Папка с шаблонами в <code>site/resources/tpl/</code>. Внутри должен быть <code>layout.tpl</code>.
          </Typography.Paragraph>
          <Form.Item label="Активная тема" name="active_theme" rules={[{ required: true }]}>
            <Select options={themeOptions} placeholder="Выберите тему" style={{ maxWidth: 360 }} />
          </Form.Item>
        </Card>
      ),
    },
    {
      key: 'home',
      label: (
        <span className="settings-tab-label">
          <HomeOutlined />
          Главная
        </span>
      ),
      children: (
        <Card title="Главная страница" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Заголовки каталога и SEO-блок внизу первой страницы.
          </Typography.Paragraph>
          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item label="Заголовок каталога" name="home_heading">
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item label="Подзаголовок" name="home_lead">
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item
                label="SEO-блок (HTML)"
                name="home_seo_html"
                extra="Текст под списком сериалов на главной. Допускается HTML."
              >
                <Input.TextArea rows={6} placeholder="<h1>...</h1><p>...</p>" />
              </Form.Item>
            </Col>
          </Row>
        </Card>
      ),
    },
    {
      key: 'seo',
      label: (
        <span className="settings-tab-label">
          <GlobalOutlined />
          SEO
        </span>
      ),
      children: (
        <Card title="SEO" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Настройки разделены по задачам. Сохранение применяется ко всем вкладкам сразу.
          </Typography.Paragraph>
          <Tabs
            activeKey={seoTab}
            onChange={changeSeoTab}
            size="small"
            className="settings-inner-tabs"
            items={[
              {
                key: 'robots',
                label: 'robots.txt',
                children: (
                  <div data-settings-field="robots_txt" id="settings-field-robots_txt">
                    <Typography.Paragraph type="secondary">
                      Содержимое файла <Typography.Text code>/robots.txt</Typography.Text>. Оставьте поле
                      пустым, чтобы использовать шаблон по умолчанию.
                    </Typography.Paragraph>
                    <Space wrap style={{ marginBottom: 12 }}>
                      <Button
                        onClick={() => {
                          form.setFieldValue('robots_txt', robotsDefault)
                          message.info('Подставлен шаблон по умолчанию')
                        }}
                      >
                        Шаблон по умолчанию
                      </Button>
                      <Button
                        onClick={() => {
                          form.setFieldValue('robots_txt', '')
                          message.info('Будет использован автоматический шаблон')
                        }}
                      >
                        Очистить
                      </Button>
                      <Button type="link" href={robotsUrl} target="_blank" rel="noreferrer">
                        Открыть /robots.txt
                      </Button>
                    </Space>
                    <Form.Item
                      label="Содержимое robots.txt"
                      name="robots_txt"
                      extra="Подстановки: {admin_path}, {sitemap_url}, {site_url}"
                    >
                      <Input.TextArea
                        rows={12}
                        placeholder={robotsDefault || 'User-agent: *\nAllow: /'}
                        className="settings-robots-editor"
                        spellCheck={false}
                      />
                    </Form.Item>
                    {!settingsMap.robots_txt?.trim() ? (
                      <Alert
                        type="info"
                        showIcon
                        message="Сейчас используется шаблон по умолчанию"
                        description={<pre className="settings-robots-preview">{robotsEffective}</pre>}
                      />
                    ) : (
                      <Alert
                        type="success"
                        showIcon
                        message="Используется сохранённый robots.txt"
                        description={<pre className="settings-robots-preview">{robotsEffective}</pre>}
                      />
                    )}
                  </div>
                ),
              },
              ...(configSeoFields.seo_verification
                ? [{
                    key: 'verification',
                    label: 'Верификация',
                    children: (
                      <>
                        <Typography.Paragraph type="secondary">
                          Укажите только значение атрибута <Typography.Text code>content</Typography.Text> из
                          meta-тега верификации — теги добавятся на сайт автоматически.
                        </Typography.Paragraph>
                        <SiteConfigFields fields={configSeoFields.seo_verification.fields} />
                      </>
                    ),
                  }]
                : []),
              ...(configSeoFields.seo_counters
                ? [{
                    key: 'counters',
                    label: 'Счётчики',
                    children: (
                      <>
                        <Typography.Paragraph type="secondary">
                          Вставьте полный код счётчиков (script, noscript, img). Код выводится перед
                          закрывающим тегом <Typography.Text code>{'</body>'}</Typography.Text> на всех
                          страницах.
                        </Typography.Paragraph>
                        <SiteConfigFields fields={configSeoFields.seo_counters.fields} />
                      </>
                    ),
                  }]
                : []),
              {
                key: 'pages',
                label: 'Страницы',
                children: (
                  <>
                    <Typography.Paragraph type="secondary">
                      Meta title и description главной и суффиксы для страницы сериала.
                    </Typography.Paragraph>
                    <SiteConfigFields
                      fields={(configSeoFields.seo_content?.fields ?? []).filter((field) =>
                        field.key.startsWith('home_meta_') || field.key.startsWith('series_meta_'),
                      )}
                    />
                  </>
                ),
              },
              {
                key: 'series',
                label: 'Сериал',
                children: (
                  <>
                    <Typography.Paragraph type="secondary">
                      Тексты вокруг плеера и виджет «Поделиться» на странице сериала.
                    </Typography.Paragraph>
                    <SiteConfigFields
                      fields={(configSeoFields.seo_content?.fields ?? []).filter((field) =>
                        field.key.startsWith('series_ui_'),
                      )}
                    />
                    {configSeoFields.seo_widgets ? (
                      <SiteConfigFields fields={configSeoFields.seo_widgets.fields} />
                    ) : null}
                  </>
                ),
              },
              ...(configSeoFields.seo_ai
                ? [{
                    key: 'ai',
                    label: 'Промпты ИИ',
                    children: (
                      <>
                        <Typography.Paragraph type="secondary">
                          Шаблоны промптов для копирования в ChatGPT/Claude из админки. Плейсхолдеры
                          подставляются автоматически при генерации промпта на странице справочника.
                        </Typography.Paragraph>
                        <SiteConfigFields fields={configSeoFields.seo_ai.fields} />
                      </>
                    ),
                  }]
                : []),
              {
                key: 'sitemap',
                label: 'sitemap.xml',
                children: (
                  <div data-settings-field="sitemap" id="settings-field-sitemap">
                    <Typography.Paragraph type="secondary">
                      Карта сайта для поисковых систем. Автоматически обновляется по расписанию и при
                      изменении контента; здесь можно принудительно пересобрать файл.
                    </Typography.Paragraph>
                    <Space wrap style={{ marginBottom: 12 }}>
                      <Button
                        type="primary"
                        loading={sitemapGenerating}
                        disabled={sitemapGenerating}
                        onClick={async () => {
                          setSitemapGenerating(true)
                          try {
                            const res = await api<{
                              ok: boolean
                              url_count?: number
                              last_modified_at?: string | null
                              message?: string
                            }>('/api/admin/sitemap/generate', {
                              method: 'POST',
                              body: JSON.stringify({}),
                            })
                            setSitemapUrlCount(res.url_count ?? null)
                            setSitemapLastModifiedAt(res.last_modified_at ?? null)
                            setSitemapDirty(false)
                            message.success(res.message ?? 'Sitemap обновлён')
                          } catch (e) {
                            message.error(String((e as Error).message))
                          } finally {
                            setSitemapGenerating(false)
                          }
                        }}
                      >
                        Обновить sitemap.xml
                      </Button>
                      <Button type="link" href={sitemapUrl} target="_blank" rel="noreferrer">
                        Открыть /sitemap.xml
                      </Button>
                    </Space>
                    {sitemapUrlCount !== null ? (
                      <Typography.Paragraph type="secondary" style={{ marginBottom: sitemapDirty ? 8 : 0 }}>
                        URL в файле: {sitemapUrlCount}
                        {sitemapLastModifiedAt ? (
                          <> · Обновлён: {new Date(sitemapLastModifiedAt).toLocaleString('ru-RU')}</>
                        ) : null}
                      </Typography.Paragraph>
                    ) : (
                      <Typography.Paragraph type="secondary" style={{ marginBottom: sitemapDirty ? 8 : 0 }}>
                        Файл sitemap.xml ещё не создан.
                      </Typography.Paragraph>
                    )}
                    {sitemapDirty ? (
                      <Alert
                        type="warning"
                        showIcon
                        message="Sitemap помечен как устаревший"
                        description="После изменений контента файл будет пересобран автоматически, либо нажмите «Обновить sitemap.xml»."
                      />
                    ) : null}
                  </div>
                ),
              },
            ]}
          />
        </Card>
      ),
    },
    {
      key: 'auth',
      label: (
        <span className="settings-tab-label">
          <UserOutlined />
          Авторизация
        </span>
      ),
      children: (
        <Card title={configSchema.auth?.title ?? 'Авторизация'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Включение входа, регистрации, восстановления пароля, лимиты и тексты сообщений.
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.auth?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'comments',
      label: (
        <span className="settings-tab-label">
          <MessageOutlined />
          Комментарии
        </span>
      ),
      children: (
        <Card title={configSchema.comments?.title ?? 'Комментарии'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Настройки разделены по группам. Сохранение применяется ко всем вкладкам сразу.
          </Typography.Paragraph>
          <Tabs
            activeKey={commentsTab}
            onChange={changeCommentsTab}
            size="small"
            className="settings-inner-tabs"
            items={groupCommentsFields(configSchema.comments?.fields ?? []).map((tab) => ({
              key: tab.key,
              label: tab.label,
              children: <SiteConfigFields fields={tab.fields} />,
            }))}
          />
        </Card>
      ),
    },
    {
      key: 'reviews',
      label: (
        <span className="settings-tab-label">
          <MessageOutlined />
          Рецензии
        </span>
      ),
      children: (
        <Card title={configSchema.reviews?.title ?? 'Рецензии'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Рецензии с оценкой 1–10, лимиты и тексты интерфейса. Сохранение применяется ко всем вкладкам сразу.
          </Typography.Paragraph>
          <Tabs
            activeKey={reviewsTab}
            onChange={changeReviewsTab}
            size="small"
            className="settings-inner-tabs"
            items={groupReviewsFields(configSchema.reviews?.fields ?? []).map((tab) => ({
              key: tab.key,
              label: tab.label,
              children: <SiteConfigFields fields={tab.fields} />,
            }))}
          />
        </Card>
      ),
    },
    {
      key: 'ratings',
      label: (
        <span className="settings-tab-label">
          <LikeOutlined />
          Рейтинги
        </span>
      ),
      children: (
        <Card title={configSchema.ratings?.title ?? 'Рейтинги и реакции'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Лайк/дизлайк сериала, реакции и голоса. Типы эмодзи — в разделе «Реакции» админки.
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.ratings?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'engagement',
      label: (
        <span className="settings-tab-label">
          <CommentOutlined />
          Списки
        </span>
      ),
      children: (
        <Card title={configSchema.engagement?.title ?? 'Списки и уведомления'} loading={loading} bordered={false}>
          <SiteConfigFields fields={configSchema.engagement?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'catalog',
      label: (
        <span className="settings-tab-label">
          <AppstoreOutlined />
          Каталог
        </span>
      ),
      children: (
        <Card title={configSchema.catalog?.title ?? 'Каталог и навигация'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Настройки разделены по страницам сайта. Сохранение применяется ко всем вкладкам сразу.
          </Typography.Paragraph>
          <Tabs
            activeKey={catalogTab}
            onChange={changeCatalogTab}
            size="small"
            className="settings-inner-tabs"
            items={groupCatalogFields(configSchema.catalog?.fields ?? []).map((tab) => ({
              key: tab.key,
              label: tab.label,
              children: <SiteConfigFields fields={tab.fields} />,
            }))}
          />
        </Card>
      ),
    },
    {
      key: 'optimization',
      label: (
        <span className="settings-tab-label">
          <ThunderboltOutlined />
          Оптимизация
        </span>
      ),
      children: (
        <Card title={configSchema.optimization?.title ?? 'Оптимизация изображений'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Настройки загрузки постеров сериалов и обложек подборок: размер, сжатие, формат и шаблон имени файла.
            Сейчас по умолчанию: <Typography.Text code>/storage/posters/kp-357.jpg</Typography.Text>
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.optimization?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'advertising',
      label: (
        <span className="settings-tab-label">
          <DollarOutlined />
          Реклама
        </span>
      ),
      children: (
        <Card title={configSchema.advertising?.title ?? 'Реклама'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Коды рекламных блоков для вывода в шаблонах сайта. Вставляйте полный HTML/JavaScript — div, script и др.
          </Typography.Paragraph>
          <Typography.Paragraph type="secondary">
            В шаблоне: <Typography.Text code>{'{ad_vpaid_code|raw}'}</Typography.Text> или блок{' '}
            <Typography.Text code>[ad_vpaid_code]...[/ad_vpaid_code]</Typography.Text> (показывается только если код задан).
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.advertising?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'maintenance',
      label: (
        <span className="settings-tab-label">
          <ToolOutlined />
          Обслуживание
        </span>
      ),
      children: (
        <Card title={configSchema.maintenance?.title ?? 'Техническое обслуживание'} loading={loading} bordered={false}>
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 16 }}
            message="При включении сайт полностью закрыт для посетителей и поисковиков"
            description="robots.txt запрещает индексацию, sitemap.xml недоступен. Администраторы с действующим токеном продолжают видеть сайт после входа в админку."
          />
          <SiteConfigFields fields={configSchema.maintenance?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'general',
      label: (
        <span className="settings-tab-label">
          <TagOutlined />
          Общие
        </span>
      ),
      children: (
        <Card title={configSchema.general?.title ?? 'Общие сообщения'} loading={loading} bordered={false}>
          <SiteConfigFields fields={configSchema.general?.fields ?? []} />
        </Card>
      ),
    },
    {
      key: 'admin',
      label: (
        <span className="settings-tab-label">
          <LockOutlined />
          Админка
        </span>
      ),
      children: (
        <Card title="Админ-панель" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Секретный URL-префикс. Без него страницы админки недоступны.
          </Typography.Paragraph>
          <Form.Item
            label="URL-префикс"
            name="admin_path"
            rules={[
              { required: true, message: 'Укажите префикс' },
              { pattern: /^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/, message: 'Только a-z, 0-9 и дефис' },
            ]}
            extra="Пример: admin54123 → /admin54123/comments"
          >
            <Input addonBefore="/" placeholder="admin54123" style={{ maxWidth: 360 }} />
          </Form.Item>
          <Alert
            type="info"
            showIcon
            message={`Текущий адрес: ${window.location.origin}/${adminPath}/`}
          />
        </Card>
      ),
    },
    {
      key: 'moderation',
      label: (
        <span className="settings-tab-label">
          <CommentOutlined />
          Модерация
        </span>
      ),
      children: (
        <>
        <Card title="Комментарии" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            По умолчанию новые комментарии попадают на модерацию и не видны на сайте до одобрения.
          </Typography.Paragraph>
          <Form.Item
            label="Автоодобрение комментариев"
            name="comments_auto_approve"
            valuePropName="checked"
            extra="Если включено — комментарии сразу публикуются без проверки."
          >
            <Switch checkedChildren="Вкл" unCheckedChildren="Выкл" />
          </Form.Item>
        </Card>
        <Card title="Рецензии" loading={loading} bordered={false} style={{ marginTop: 16 }}>
          <Typography.Paragraph type="secondary">
            Пользовательские рецензии по умолчанию требуют модерации. Редакционные из админки публикуются сразу.
          </Typography.Paragraph>
          <Form.Item
            label="Автоодобрение рецензий"
            name="reviews_auto_approve"
            valuePropName="checked"
            extra="Если включено — рецензии пользователей сразу публикуются без проверки."
          >
            <Switch checkedChildren="Вкл" unCheckedChildren="Выкл" />
          </Form.Item>
        </Card>
        </>
      ),
    },
    {
      key: 'integrations',
      label: (
        <span className="settings-tab-label">
          <ApiOutlined />
          Интеграции
        </span>
      ),
      children: (
        <>
        <Card title={configSchema.integrations?.title ?? 'Импорт метаданных'} loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Ограничение числа актёров и режиссёров при импорте через кнопки «Импорт КР», «Импорт Alloha» и «Импорт TMDB».
            Большие списки часто зависают из‑за скачивания фотографий.
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.integrations?.fields ?? []} />
        </Card>
        <Card title="KinoPoisk API" loading={loading} bordered={false} style={{ marginTop: 16 }}>
          <Typography.Paragraph type="secondary">
            Ключ для импорта сериалов и фильмов. Запасной вариант — переменная <code>KINOPOISK_API_KEY</code> в .env.
          </Typography.Paragraph>
          <Form.Item
            label="API-ключ"
            name="kinopoisk_api_key"
            extra={
              kinopoiskApiKeySet
                ? 'Ключ уже задан. Введите новый, чтобы заменить, или оставьте поле пустым.'
                : 'Получите ключ на kinopoiskapiunofficial.tech'
            }
          >
            <Input.Password
              placeholder={kinopoiskApiKeySet ? '••••••••••••••••' : 'Ваш API-ключ'}
              autoComplete="new-password"
              style={{ maxWidth: 480 }}
            />
          </Form.Item>
          {kinopoiskApiKeySet ? (
            <Alert type="success" showIcon message="API-ключ KinoPoisk настроен" />
          ) : (
            <Alert type="warning" showIcon message="API-ключ KinoPoisk не задан — импорт недоступен" />
          )}
        </Card>
        <Card title="Alloha API" loading={loading} bordered={false} style={{ marginTop: 16 }}>
          <Typography.Paragraph type="secondary">
            Bearer-токен для импорта плееров и метаданных. Запасной вариант — переменная <code>ALLOHA_API_TOKEN</code> в .env.
          </Typography.Paragraph>
          <Form.Item
            label="API-токен"
            name="alloha_api_token"
            extra={
              allohaApiTokenSet
                ? 'Токен уже задан. Введите новый, чтобы заменить, или оставьте поле пустым.'
                : 'Получите токен у провайдера Alloha TV'
            }
          >
            <Input.Password
              placeholder={allohaApiTokenSet ? '••••••••••••••••' : 'Ваш Bearer-токен'}
              autoComplete="new-password"
              style={{ maxWidth: 480 }}
            />
          </Form.Item>
          {allohaApiTokenSet ? (
            <Alert type="success" showIcon message="API-токен Alloha настроен" />
          ) : (
            <Alert type="warning" showIcon message="API-токен Alloha не задан — импорт плееров недоступен" />
          )}
        </Card>
        <Card title="TMDB API" loading={loading} bordered={false} style={{ marginTop: 16 }}>
          <Typography.Paragraph type="secondary">
            Ключ для синхронизации популярности, статуса эфира и студий из The Movie Database. Запасной вариант — переменная{' '}
            <code>TMDB_API_KEY</code> в .env.
            Документация:{' '}
            <a href="https://developer.themoviedb.org/reference/tv-series-details" target="_blank" rel="noreferrer">
              сериалы
            </a>
            {' '}(networks),{' '}
            <a href="https://developer.themoviedb.org/reference/movie-details" target="_blank" rel="noreferrer">
              фильмы
            </a>
            {' '}(production_companies).
          </Typography.Paragraph>
          <Form.Item
            label="API-ключ"
            name="tmdb_api_key"
            extra={
              tmdbApiKeySet
                ? 'Ключ уже задан. Введите новый, чтобы заменить, или оставьте поле пустым.'
                : 'Получите ключ на themoviedb.org/settings/api'
            }
          >
            <Input.Password
              placeholder={tmdbApiKeySet ? '••••••••••••••••' : 'Ваш API-ключ TMDB'}
              autoComplete="new-password"
              style={{ maxWidth: 480 }}
            />
          </Form.Item>
          {tmdbApiKeySet ? (
            <Alert type="success" showIcon message="API-ключ TMDB настроен" style={{ marginBottom: 16 }} />
          ) : (
            <Alert type="warning" showIcon message="API-ключ TMDB не задан — синхронизация популярности и статуса недоступна" style={{ marginBottom: 16 }} />
          )}
          <Form.Item
            label="Автообновление TMDB"
            extra="Синхронизация идёт пакетами (по 25 сериалов), чтобы сайт не зависал на больших каталогах. Раз в сутки обновляет популярность TMDB, статус, расписание, студии и логотипы. Ручной статус «На паузе» не перезаписывается, пока TMDB считает шоу продолжающимся."
          >
            <Space wrap>
              <Switch
                checked={tmdbAutoSyncEnabled}
                checkedChildren="Вкл"
                unCheckedChildren="Выкл"
                disabled={!tmdbApiKeySet}
                onChange={async (checked) => {
                  try {
                    await api('/api/admin/tmdb/auto-sync', {
                      method: 'POST',
                      body: JSON.stringify({ enabled: checked }),
                    })
                    setTmdbAutoSyncEnabled(checked)
                    message.success(checked ? 'Автообновление включено' : 'Автообновление выключено')
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              />
              <Button
                disabled={!tmdbApiKeySet || tmdbSyncing}
                loading={tmdbSyncing}
                onClick={async () => {
                  setTmdbSyncing(true)
                  try {
                    let done = false
                    let restart = true
                    let lastResult: {
                      updated?: number
                      status_changed?: number
                      schedule_synced?: number
                      studio_logos?: number
                      failed?: number
                      processed?: number
                      total?: number
                    } = {}

                    while (!done) {
                      const res = await api<{
                        ok: boolean
                        done?: boolean
                        output?: string
                        result?: typeof lastResult
                        error?: string
                      }>('/api/admin/sync/tmdb-popularity', {
                        method: 'POST',
                        body: JSON.stringify({
                          restart,
                          continue: !restart,
                        }),
                      })
                      restart = false
                      lastResult = res.result ?? lastResult
                      done = Boolean(res.done)
                      if (!done && res.output) {
                        message.loading({ content: res.output, key: 'tmdb-sync', duration: 0 })
                      }
                    }

                    message.destroy('tmdb-sync')
                    message.success(
                      `TMDB: обновлено ${lastResult.updated ?? 0}, статус ${lastResult.status_changed ?? 0}, расписание ${lastResult.schedule_synced ?? 0}, логотипов ${lastResult.studio_logos ?? 0}`,
                    )
                    const autoData = await api<{ last_run_at?: string | null }>('/api/admin/tmdb/auto-sync')
                    setTmdbLastRunAt(autoData.last_run_at ?? null)
                  } catch (e) {
                    message.destroy('tmdb-sync')
                    message.error(String((e as Error).message))
                  } finally {
                    setTmdbSyncing(false)
                  }
                }}
              >
                Обновить сейчас
              </Button>
              <Button
                disabled={!tmdbApiKeySet || tmdbSyncing}
                onClick={async () => {
                  try {
                    const res = await api<{ output?: string }>('/api/admin/sync/tmdb-studio-logos', {
                      method: 'POST',
                      body: JSON.stringify({}),
                    })
                    message.success(res.output || 'Логотипы студий обновлены')
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Дозагрузить лого студий
              </Button>
            </Space>
          </Form.Item>
          {tmdbLastRunAt ? (
            <Typography.Paragraph type="secondary" style={{ marginBottom: 0 }}>
              Последний запуск: {new Date(tmdbLastRunAt).toLocaleString('ru-RU')}
            </Typography.Paragraph>
          ) : null}
        </Card>
        <Card title="CDN VideoHub — автоплеер" loading={loading} bordered={false} style={{ marginTop: 16 }}>
          <Typography.Paragraph type="secondary">
            При импорте сериала автоматически добавляется вкладка с плеером <code>&lt;video-player&gt;</code>.
            В атрибут <code>data-title-id</code> подставляется KP ID сериала.
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.players?.fields ?? []} />

          {(cdnSyncing ||
            cdnProgress?.status === 'running' ||
            cdnProgress?.status === 'paused' ||
            cdnProgress?.status === 'done' ||
            cdnProgress?.status === 'stopped' ||
            cdnProgress?.status === 'failed') && (
            <div style={{ marginBottom: 16, marginTop: 8 }}>
              <Progress
                percent={cdnPercent}
                status={
                  cdnProgress?.status === 'failed' || cdnProgress?.status === 'stopped'
                    ? 'exception'
                    : cdnProgress?.status === 'done'
                      ? 'success'
                      : cdnProgress?.status === 'paused' || (cdnProgress?.status === 'running' && !cdnSyncing)
                        ? 'normal'
                        : 'active'
                }
              />
              <Typography.Text type="secondary">
                {cdnProgress?.message || `Обработано ${cdnProgress?.processed ?? 0} из ${cdnProgress?.total ?? 0}`}
              </Typography.Text>
            </div>
          )}

          <Space style={{ marginTop: 8 }} wrap>
            {!cdnSyncing && cdnProgress?.status !== 'running' && cdnProgress?.status !== 'paused' ? (
              <Popconfirm
                title="Проставить CDN VideoHub всем сериалам?"
                description="Будет создана или обновлена вкладка плеера у всех сериалов с KP ID. Сохраните настройки перед запуском."
                okText="Проставить всем"
                cancelText="Отмена"
                onConfirm={() => void runCdnLoop(true)}
              >
                <Button type="primary" icon={<PlayCircleOutlined />}>
                  Проставить всем сериалам
                </Button>
              </Popconfirm>
            ) : null}
            {cdnSyncing ? (
              <Button
                icon={<PauseCircleOutlined />}
                onClick={async () => {
                  cdnAbortRef.current = true
                  try {
                    const res = await api<{ progress: typeof cdnProgress; percent: number }>(
                      '/api/admin/players/cdnvideohub/sync-pause',
                      { method: 'POST' },
                    )
                    if (res.progress) setCdnProgress(res.progress)
                    setCdnPercent(res.percent ?? 0)
                    message.info('Пауза')
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Пауза
              </Button>
            ) : null}
            {cdnProgress?.status === 'paused' || (cdnProgress?.status === 'running' && !cdnSyncing) ? (
              <Button
                type="primary"
                icon={<PlayCircleOutlined />}
                onClick={async () => {
                  try {
                    if (cdnProgress?.status === 'paused') {
                      await api('/api/admin/players/cdnvideohub/sync-resume', { method: 'POST' })
                    }
                    await runCdnLoop(false)
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Продолжить
              </Button>
            ) : null}
            {cdnSyncing || cdnProgress?.status === 'running' || cdnProgress?.status === 'paused' ? (
              <Button
                danger
                icon={<StopOutlined />}
                onClick={async () => {
                  cdnAbortRef.current = true
                  try {
                    const res = await api<{ progress: typeof cdnProgress; percent: number }>(
                      '/api/admin/players/cdnvideohub/sync-stop',
                      { method: 'POST' },
                    )
                    if (res.progress) setCdnProgress(res.progress)
                    setCdnPercent(res.percent ?? 0)
                    message.warning('Остановлено')
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                }}
              >
                Стоп
              </Button>
            ) : null}
            <Typography.Text type="secondary">
              Только сериалы с числовым KP ID. Работает, если автодобавление включено.
            </Typography.Text>
          </Space>
        </Card>
        </>
      ),
    },
  ]

  return (
    <>
    <Form form={form} layout="vertical" onFinish={save} className="settings-page">
      <div className="settings-page__toolbar">
        <Typography.Title level={5} style={{ margin: 0 }}>
          {SECTION_LABELS[section]}
        </Typography.Title>
        <Button type="primary" htmlType="submit" loading={saving}>
          Сохранить
        </Button>
      </div>

      <Tabs
        activeKey={section}
        onChange={changeSection}
        tabPosition={tabPosition}
        className="settings-tabs"
        items={tabItems}
      />

      <div className="settings-page__footer">
        <Space>
          <Button type="primary" htmlType="submit" size="large" loading={saving}>
            Сохранить настройки
          </Button>
          <Typography.Text type="secondary">
            Изменения применяются ко всем разделам сразу
          </Typography.Text>
        </Space>
      </div>
    </Form>
    <BrandImageEditorModal
      open={Boolean(bgEditor)}
      imageSrc={bgEditor?.src ?? null}
      fileName={bgEditor?.fileName}
      title="Подгонка фона сайта"
      onCancel={() => {
        setBgEditor((prev) => {
          if (prev?.revokeUrl) URL.revokeObjectURL(prev.revokeUrl)
          return null
        })
      }}
      onConfirm={async ({ file }) => {
        const fd = new FormData()
        fd.append('background', file)
        await apiUpload<{ background_url: string }>('/api/admin/branding/background', fd)
        message.success('Фон загружен')
        setBgEditor((prev) => {
          if (prev?.revokeUrl) URL.revokeObjectURL(prev.revokeUrl)
          return null
        })
        await load()
      }}
    />
    </>
  )
}
