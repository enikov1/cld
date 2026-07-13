import {
  Alert,
  Button,
  Card,
  Col,
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
} from 'antd'
import {
  ApiOutlined,
  AppstoreOutlined,
  CommentOutlined,
  GlobalOutlined,
  HomeOutlined,
  LikeOutlined,
  LockOutlined,
  MessageOutlined,
  SkinOutlined,
  TagOutlined,
  ThunderboltOutlined,
  ToolOutlined,
  UserOutlined,
} from '@ant-design/icons'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { apiUpload } from '../api/upload'
import SiteConfigFields from '../components/SiteConfigFields'
import type { SettingItem, ThemeItem } from '../types'
import type { SiteConfigSchema } from '../types/siteConfig'

type SettingsSection =
  | 'branding'
  | 'theme'
  | 'home'
  | 'seo'
  | 'auth'
  | 'comments'
  | 'ratings'
  | 'engagement'
  | 'catalog'
  | 'optimization'
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
  ratings: 'Рейтинги',
  engagement: 'Списки и уведомления',
  catalog: 'Каталог',
  optimization: 'Оптимизация',
  maintenance: 'Обслуживание',
  general: 'Общие',
  admin: 'Админ-панель',
  moderation: 'Модерация',
  integrations: 'Интеграции',
}

function sectionFromHash(): SettingsSection {
  const hash = window.location.hash.replace(/^#/, '')
  if (hash in SECTION_LABELS) {
    return hash as SettingsSection
  }
  return 'branding'
}

export default function SettingsPage() {
  const [settings, setSettings] = useState<SettingItem[]>([])
  const [themes, setThemes] = useState<ThemeItem[]>([])
  const [activeTheme, setActiveTheme] = useState('')
  const [kinopoiskApiKeySet, setKinopoiskApiKeySet] = useState(false)
  const [allohaApiTokenSet, setAllohaApiTokenSet] = useState(false)
  const [tmdbApiKeySet, setTmdbApiKeySet] = useState(false)
  const [tmdbAutoSyncEnabled, setTmdbAutoSyncEnabled] = useState(false)
  const [tmdbLastRunAt, setTmdbLastRunAt] = useState<string | null>(null)
  const [tmdbSyncing, setTmdbSyncing] = useState(false)
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
  const [section, setSection] = useState<SettingsSection>(sectionFromHash)
  const [tabPosition, setTabPosition] = useState<'left' | 'top'>('left')
  const [form] = Form.useForm()

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
    const keys = new Set<string>(['comments_auto_approve'])
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

  useEffect(() => {
    const values: Record<string, unknown> = {
      site_name: settingsMap.site_name,
      site_tagline: settingsMap.site_tagline,
      footer_text: settingsMap.footer_text,
      site_background_header_offset: Number(settingsMap.site_background_header_offset || 200),
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
    }

    const applyFields = (schema: SiteConfigSchema) => {
      Object.values(schema).forEach((group) => {
        group.fields.forEach((field) => {
          if (field.type === 'bool') {
            values[field.key] = settingsMap[field.key] === '1'
          } else if (field.type === 'int') {
            values[field.key] = Number(settingsMap[field.key] ?? field.min ?? 0)
          } else {
            values[field.key] = settingsMap[field.key] ?? ''
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
    const onHashChange = () => setSection(sectionFromHash())
    window.addEventListener('hashchange', onHashChange)
    return () => window.removeEventListener('hashchange', onHashChange)
  }, [])

  function changeSection(key: string) {
    const next = key as SettingsSection
    setSection(next)
    window.location.hash = next
  }

  async function save(values: Record<string, unknown>) {
    const prevPath = settingsMap.admin_path || 'admin'
    try {
      await api('/api/admin/settings', {
        method: 'POST',
        body: JSON.stringify({
          settings: Object.entries(values).map(([key, value]) => {
            if (boolKeys.has(key)) {
              return { key, value: value ? '1' : '0' }
            }
            return { key, value: String(value ?? '') }
          }),
        }),
      })
      const nextPath = String(values.admin_path || 'admin').replace(/^\/+|\/+$/g, '')
      if (nextPath !== prevPath.replace(/^\/+|\/+$/g, '')) {
        message.success(`Настройки сохранены. Откройте админку: /${nextPath}/`)
      } else {
        message.success('Настройки сохранены')
      }
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
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
            PNG или JPG, до 2 МБ. При загруженном фоне шапка сдвигается вниз на заданный отступ.
          </Typography.Paragraph>
          {settingsMap.site_background_url ? (
            <div style={{ marginBottom: 12 }}>
              <img
                src={settingsMap.site_background_url}
                alt="Фон"
                style={{ maxHeight: 120, maxWidth: '100%', objectFit: 'cover', display: 'block', marginBottom: 8, borderRadius: 8 }}
              />
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
            </div>
          ) : null}
          <Upload
            beforeUpload={async (file) => {
              const fd = new FormData()
              fd.append('background', file)
              try {
                await apiUpload<{ background_url: string }>('/api/admin/branding/background', fd)
                message.success('Фон загружен')
                await load()
              } catch (e) {
                message.error(String((e as Error).message))
              }
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
        <Card title="robots.txt" loading={loading} bordered={false}>
          <Typography.Paragraph type="secondary">
            Содержимое файла <Typography.Text code>/robots.txt</Typography.Text>. Оставьте поле пустым, чтобы
            использовать шаблон по умолчанию.
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
          {configSeoFields.seo_verification ? (
            <Card title={configSeoFields.seo_verification.title} size="small" style={{ marginTop: 16 }}>
              <Typography.Paragraph type="secondary">
                Укажите только значение атрибута <Typography.Text code>content</Typography.Text> из meta-тега
                верификации — теги добавятся на сайт автоматически.
              </Typography.Paragraph>
              <SiteConfigFields fields={configSeoFields.seo_verification.fields} />
            </Card>
          ) : null}
          {configSeoFields.seo_counters ? (
            <Card title={configSeoFields.seo_counters.title} size="small" style={{ marginTop: 16 }}>
              <Typography.Paragraph type="secondary">
                Вставьте полный код счётчиков (script, noscript, img). Код выводится перед закрывающим
                тегом <Typography.Text code>{'</body>'}</Typography.Text> на всех страницах.
              </Typography.Paragraph>
              <SiteConfigFields fields={configSeoFields.seo_counters.fields} />
            </Card>
          ) : null}
          {configSeoFields.seo_widgets ? (
            <Card title={configSeoFields.seo_widgets.title} size="small" style={{ marginTop: 16 }}>
              <Typography.Paragraph type="secondary">
                Вставьте HTML/JavaScript-код виджета «Поделиться». Он появится слева под плеером на странице
                сериала, справа останется кнопка добавления сайта в закладки.
              </Typography.Paragraph>
              <SiteConfigFields fields={configSeoFields.seo_widgets.fields} />
            </Card>
          ) : null}
          {configSeoFields.seo_content ? (
            <Card title={configSeoFields.seo_content.title} size="small" style={{ marginTop: 16 }}>
              <SiteConfigFields fields={configSeoFields.seo_content.fields} />
            </Card>
          ) : null}
          <Card title="sitemap.xml" size="small" style={{ marginTop: 16 }} loading={loading}>
            <Typography.Paragraph type="secondary">
              Карта сайта для поисковых систем. Автоматически обновляется по расписанию и при изменении
              контента; здесь можно принудительно пересобрать файл.
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
          </Card>
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
            Комментарии, голоса, лимиты и тексты интерфейса.
          </Typography.Paragraph>
          <SiteConfigFields fields={configSchema.comments?.fields ?? []} />
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
          <SiteConfigFields fields={configSchema.catalog?.fields ?? []} />
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
        <Card title="KinoPoisk API" loading={loading} bordered={false}>
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
            Ключ для получения популярности из The Movie Database. Запасной вариант — переменная <code>TMDB_API_KEY</code> в .env.
            Документация:{' '}
            <a href="https://developer.themoviedb.org/reference/tv-series-details" target="_blank" rel="noreferrer">
              сериалы
            </a>
            {', '}
            <a href="https://developer.themoviedb.org/reference/movie-details" target="_blank" rel="noreferrer">
              фильмы
            </a>
            .
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
            <Alert type="warning" showIcon message="API-ключ TMDB не задан — синхронизация популярности недоступна" style={{ marginBottom: 16 }} />
          )}
          <Form.Item
            label="Автообновление популярности"
            extra="Раз в сутки обновляет поле «Популярность TMDB» для всех сериалов с TMDB ID."
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
                    const res = await api<{ ok: boolean; output?: string; error?: string }>('/api/admin/sync/tmdb-popularity', {
                      method: 'POST',
                      body: JSON.stringify({}),
                    })
                    if (res.output) {
                      message.info(res.output.split('\n').slice(-1)[0] || 'Синхронизация завершена')
                    } else {
                      message.success('Синхронизация завершена')
                    }
                    const autoData = await api<{ last_run_at?: string | null }>('/api/admin/tmdb/auto-sync')
                    setTmdbLastRunAt(autoData.last_run_at ?? null)
                  } catch (e) {
                    message.error(String((e as Error).message))
                  } finally {
                    setTmdbSyncing(false)
                  }
                }}
              >
                Обновить сейчас
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
        </Card>
        </>
      ),
    },
  ]

  return (
    <Form form={form} layout="vertical" onFinish={save} className="settings-page">
      <div className="settings-page__toolbar">
        <Typography.Title level={5} style={{ margin: 0 }}>
          {SECTION_LABELS[section]}
        </Typography.Title>
        <Button type="primary" htmlType="submit" loading={loading}>
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
          <Button type="primary" htmlType="submit" size="large" loading={loading}>
            Сохранить настройки
          </Button>
          <Typography.Text type="secondary">
            Изменения применяются ко всем разделам сразу
          </Typography.Text>
        </Space>
      </div>
    </Form>
  )
}
