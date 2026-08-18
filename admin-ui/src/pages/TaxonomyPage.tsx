import { Button, Form, Input, InputNumber, Modal, Popconfirm, Progress, Select, Space, Switch, Table, Tabs, Tag, Tooltip, Typography, Upload, message } from 'antd'
import { CloudDownloadOutlined, CopyOutlined, DeleteOutlined, EditOutlined, ImportOutlined, StopOutlined } from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, apiUpload, ApiError } from '../api/client'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { TaxonomyItem, TaxonomyType } from '../types'
import { resolveMediaUrl } from '../utils/mediaUrl'
import {
  DEFAULT_TAXONOMY_SEO_AI_PROMPT,
  TAXONOMY_SEO_AI_PROMPT_KEY,
  TAXONOMY_TYPE_LABELS,
  fillTaxonomySeoAiPrompt,
  parseTaxonomySeoAiResult,
} from '../utils/taxonomySeoAiPrompt'

const TYPES: { key: TaxonomyType; label: string; urlPrefix: string }[] = [
  { key: 'genres', label: 'Жанры', urlPrefix: 'genre' },
  { key: 'countries', label: 'Страны', urlPrefix: 'country' },
  { key: 'people', label: 'Актёры', urlPrefix: 'person' },
  { key: 'years', label: 'Годы', urlPrefix: 'year' },
  { key: 'voices', label: 'Озвучки', urlPrefix: 'voice' },
]

const HOME_SORT_OPTIONS = [
  { value: 'latest', label: 'Последние' },
  { value: 'popular', label: 'Популярные' },
  { value: 'rating', label: 'По рейтингу' },
]

type SettingRow = { key: string; value: string }

type VoiceSyncProgress = {
  status?: 'idle' | 'running' | 'stopped' | 'done' | 'failed'
  total?: number
  processed?: number
  synced?: number
  skipped?: number
  failed?: number
  catalog?: number
  current?: string
  message?: string
}

function voiceProgressBarStatus(status: VoiceSyncProgress['status'], syncing: boolean): 'active' | 'success' | 'exception' | 'normal' {
  if (status === 'failed' || status === 'stopped') return 'exception'
  if (status === 'done') return 'success'
  if (syncing || status === 'running') return 'active'
  return 'normal'
}

function isTransientSyncError(error: unknown): boolean {
  if (error instanceof ApiError) {
    return [408, 429, 500, 502, 503, 504].includes(error.status)
  }
  return error instanceof TypeError
}

function wait(ms: number): Promise<void> {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms)
  })
}

async function loadTaxonomySeoPromptTemplate(): Promise<string> {
  const data = await api<{ items: SettingRow[] }>('/api/admin/settings')
  const value = data.items.find((row) => row.key === TAXONOMY_SEO_AI_PROMPT_KEY)?.value?.trim()
  return value || DEFAULT_TAXONOMY_SEO_AI_PROMPT
}

function TaxonomyTab({
  type,
  label,
  urlPrefix,
  deepLinkId,
  onDeepLinkHandled,
}: {
  type: TaxonomyType
  label: string
  urlPrefix: string
  deepLinkId?: string | null
  onDeepLinkHandled?: () => void
}) {
  const { isDark } = useAdminTheme()
  const [items, setItems] = useState<TaxonomyItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<TaxonomyItem | null>(null)
  const [form] = Form.useForm()
  const photoUrl = Form.useWatch('photo_url', form)
  const deepLinkHandled = useRef(false)

  const [promptModalOpen, setPromptModalOpen] = useState(false)
  const [promptLoading, setPromptLoading] = useState(false)
  const [promptText, setPromptText] = useState('')

  const [templateModalOpen, setTemplateModalOpen] = useState(false)
  const [templateLoading, setTemplateLoading] = useState(false)
  const [templateSaving, setTemplateSaving] = useState(false)
  const [templateText, setTemplateText] = useState('')

  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importPreview, setImportPreview] = useState<ReturnType<typeof parseTaxonomySeoAiResult>>(null)
  const [importError, setImportError] = useState('')
  const [allohaSyncing, setAllohaSyncing] = useState(false)
  const [voiceSyncPercent, setVoiceSyncPercent] = useState(0)
  const [voiceSyncProgress, setVoiceSyncProgress] = useState<VoiceSyncProgress | null>(null)
  const [deletingAll, setDeletingAll] = useState(false)
  const voiceSyncLoopRef = useRef(false)
  const voiceSyncAbortRef = useRef(false)

  useDocumentTitle(
    importModalOpen
      ? 'Импорт SEO из ИИ'
      : templateModalOpen
        ? 'Шаблон промпта для SEO справочников'
        : promptModalOpen
          ? 'Промпт для ИИ — SEO справочника'
          : modalOpen
            ? editing
              ? `Редактируем: ${editing.name}`
              : `Новый: ${label}`
            : null,
  )
  useBusyFavicon(promptLoading || templateLoading || templateSaving || allohaSyncing)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ items: TaxonomyItem[] }>(`/api/admin/taxonomies/${type}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [type])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    if (deepLinkHandled.current || !deepLinkId || loading || items.length === 0) {
      return
    }
    deepLinkHandled.current = true
    const row = items.find((item) => String(item.id) === deepLinkId)
    if (row) {
      openEdit(row)
    } else {
      message.warning('Элемент справочника не найден')
    }
    onDeepLinkHandled?.()
  }, [deepLinkId, items, loading, onDeepLinkHandled]) // eslint-disable-line react-hooks/exhaustive-deps

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({ is_active: true, is_hidden: false, noindex: true, sort_order: 0, seo_html: '', show_on_home: false, home_item_limit: 18, home_show_tabs: true, home_default_sort: 'latest' })
    setModalOpen(true)
  }

  function openEdit(row: TaxonomyItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
    })
    setModalOpen(true)
  }

  async function save(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      await api(`/api/admin/taxonomies/${type}/upsert`, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Сохранено' : 'Создано')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/taxonomies/${type}/${id}`, { method: 'DELETE' })
      message.success('Удалено')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const loadVoiceProgress = useCallback(async () => {
    const res = await api<{
      progress?: VoiceSyncProgress
      percent?: number
      done?: boolean
      stopped?: boolean
    }>('/api/admin/taxonomies/voices/sync-alloha/progress')
    if (res.progress) setVoiceSyncProgress(res.progress)
    setVoiceSyncPercent(res.percent ?? 0)
    return res
  }, [])

  const runVoiceSyncLoop = useCallback(async (restart: boolean) => {
    if (type !== 'voices' || voiceSyncLoopRef.current) return

    voiceSyncLoopRef.current = true
    voiceSyncAbortRef.current = false
    setAllohaSyncing(true)
    let nextRestart = restart
    let transientErrors = 0

    try {
      while (!voiceSyncAbortRef.current) {
        try {
          const res = await api<{
            ok: boolean
            done?: boolean
            stopped?: boolean
            percent?: number
            message?: string
            progress?: VoiceSyncProgress
            synced?: number
            skipped?: number
            failed?: number
            catalog?: number
          }>('/api/admin/taxonomies/voices/sync-alloha', {
            method: 'POST',
            body: JSON.stringify({
              restart: nextRestart,
              continue: !nextRestart,
            }),
          })

          nextRestart = false
          transientErrors = 0
          if (res.progress) setVoiceSyncProgress(res.progress)
          setVoiceSyncPercent(res.percent ?? 0)

          if (res.stopped || res.progress?.status === 'stopped') {
            message.warning(res.message || 'Синхронизация остановлена')
            break
          }

          if (res.done) {
            message.success(
              res.message
              || `Готово: озвучек у сериалов ${res.catalog ?? 0}, сериалов с озвучками ${res.synced ?? 0}`,
            )
            await load()
            break
          }
        } catch (e) {
          nextRestart = false
          if (voiceSyncAbortRef.current) break
          if (isTransientSyncError(e) && transientErrors < 8) {
            transientErrors += 1
            setVoiceSyncProgress((prev) => prev
              ? { ...prev, message: `Сервер не ответил вовремя, повтор ${transientErrors} из 8…` }
              : prev)
            await wait(1200 * transientErrors)
            continue
          }
          message.error(String((e as Error).message))
          break
        }
      }
    } finally {
      voiceSyncLoopRef.current = false
      setAllohaSyncing(false)
      try {
        await loadVoiceProgress()
      } catch {
        /* progress panel is optional after sync */
      }
    }
  }, [load, loadVoiceProgress, type])

  async function stopVoiceSync() {
    voiceSyncAbortRef.current = true
    try {
      const res = await api<{ message?: string; progress?: VoiceSyncProgress; percent?: number }>(
        '/api/admin/taxonomies/voices/sync-alloha/stop',
        { method: 'POST' },
      )
      if (res.progress) setVoiceSyncProgress(res.progress)
      setVoiceSyncPercent(res.percent ?? 0)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeAllVoices() {
    if (type !== 'voices' || allohaSyncing) return
    setDeletingAll(true)
    try {
      const res = await api<{ deleted?: number }>('/api/admin/taxonomies/voices', { method: 'DELETE' })
      message.success(`Удалено озвучек: ${res.deleted ?? 0}`)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setDeletingAll(false)
    }
  }

  useEffect(() => {
    if (type !== 'voices') return
    let cancelled = false
    loadVoiceProgress().then((res) => {
      if (cancelled) return
      if (res.progress?.status === 'running') {
        void runVoiceSyncLoop(false)
      }
    }).catch(() => {
      /* progress is optional */
    })
    return () => {
      cancelled = true
    }
  }, [type, loadVoiceProgress, runVoiceSyncLoop])

  useEffect(() => {
    if (type !== 'voices') return
    if (!allohaSyncing && voiceSyncProgress?.status !== 'running') return

    const timer = window.setInterval(() => {
      loadVoiceProgress().catch(() => {
        /* live progress is best-effort */
      })
    }, 400)

    return () => {
      window.clearInterval(timer)
    }
  }, [type, allohaSyncing, voiceSyncProgress?.status, loadVoiceProgress])

  function buildPromptVars() {
    const name = String(form.getFieldValue('name') || '').trim()
    const slug = String(form.getFieldValue('slug') || '').trim()
    return {
      name,
      type,
      typeLabel: TAXONOMY_TYPE_LABELS[type],
      slug,
      url: `/${urlPrefix}/${slug || '{slug}'}/`,
    }
  }

  async function openPromptModal() {
    const vars = buildPromptVars()
    if (!vars.name) {
      message.warning('Сначала укажите название')
      return
    }

    setPromptModalOpen(true)
    setPromptLoading(true)
    setPromptText('')
    try {
      const template = await loadTaxonomySeoPromptTemplate()
      setPromptText(fillTaxonomySeoAiPrompt(template, vars))
    } catch (e) {
      message.error(String((e as Error).message))
      setPromptModalOpen(false)
    } finally {
      setPromptLoading(false)
    }
  }

  async function copyPrompt() {
    if (!promptText.trim()) return
    try {
      await navigator.clipboard.writeText(promptText)
      message.success('Промпт скопирован в буфер обмена')
    } catch {
      message.error('Не удалось скопировать')
    }
  }

  async function openTemplateModal() {
    setTemplateModalOpen(true)
    setTemplateLoading(true)
    try {
      setTemplateText(await loadTaxonomySeoPromptTemplate())
    } catch (e) {
      message.error(String((e as Error).message))
      setTemplateModalOpen(false)
    } finally {
      setTemplateLoading(false)
    }
  }

  async function saveTemplate() {
    setTemplateSaving(true)
    try {
      await api('/api/admin/settings', {
        method: 'POST',
        body: JSON.stringify({
          settings: [{ key: TAXONOMY_SEO_AI_PROMPT_KEY, value: templateText }],
        }),
      })
      message.success('Шаблон промпта сохранён')
      setTemplateModalOpen(false)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setTemplateSaving(false)
    }
  }

  function openImportModal() {
    setImportModalOpen(true)
    setImportText('')
    setImportPreview(null)
    setImportError('')
  }

  function runImportPreview() {
    const payload = importText.trim()
    if (!payload) {
      message.warning('Вставьте JSON-ответ от ИИ')
      return
    }

    const parsed = parseTaxonomySeoAiResult(payload)
    if (!parsed) {
      setImportPreview(null)
      setImportError('Не удалось распознать JSON. Вставьте ответ ИИ целиком или только JSON-блок.')
      message.error('Не удалось распознать JSON')
      return
    }

    setImportError('')
    setImportPreview(parsed)
    message.success('JSON распознан — проверьте предпросмотр')
  }

  function applyImport() {
    if (!importPreview) {
      message.warning('Сначала вставьте JSON и нажмите «Проверить»')
      return
    }

    if (importPreview.meta_title) form.setFieldValue('meta_title', importPreview.meta_title)
    if (importPreview.meta_description) form.setFieldValue('meta_description', importPreview.meta_description)
    if (importPreview.seo_html) form.setFieldValue('seo_html', importPreview.seo_html)

    message.success('SEO-поля заполнены из ответа ИИ')
    setImportModalOpen(false)
    setImportText('')
    setImportPreview(null)
    setImportError('')
  }

  const columns: ColumnsType<TaxonomyItem> = [
    ...(type === 'people'
      ? [{
          title: 'Фото',
          key: 'photo',
          width: 72,
          render: (_: unknown, row: TaxonomyItem) => (
            row.photo_url ? (
              <Tooltip
                title={(
                  <img
                    src={resolveMediaUrl(row.photo_url)}
                    alt=""
                    style={{ width: 120, height: 160, objectFit: 'cover', borderRadius: 6, display: 'block' }}
                  />
                )}
              >
                <img
                  src={resolveMediaUrl(row.photo_url)}
                  alt=""
                  style={{ width: 40, height: 52, objectFit: 'cover', borderRadius: 4, cursor: 'pointer' }}
                />
              </Tooltip>
            ) : (
              <span style={{ color: '#999' }}>—</span>
            )
          ),
        }]
      : []),
    { title: 'Название', dataIndex: 'name', key: 'name' },
    {
      title: 'URL',
      dataIndex: 'slug',
      key: 'slug',
      width: 200,
      render: (slug) => `/${urlPrefix}/${slug}/`,
    },
    {
      title: 'Сериалов',
      dataIndex: 'series_count',
      key: 'series_count',
      width: 100,
      render: (count: number | undefined) => count ?? 0,
    },
    { title: 'Порядок', dataIndex: 'sort_order', key: 'sort_order', width: 90 },
    {
      title: 'Главная',
      key: 'home',
      width: 110,
      render: (_, row) => (row.show_on_home ? <Tag color="blue">Блок</Tag> : <Tag>—</Tag>),
    },
    {
      title: 'SEO',
      key: 'seo',
      width: 90,
      render: (_, row) => (row.meta_title?.trim() || row.seo_html?.trim() ? <Tag color="blue">Есть</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 140,
      render: (_, row) => (
        <Space size={4} wrap>
          {row.is_active ? <Tag color="green">Активен</Tag> : <Tag>Выключен</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыт</Tag> : null}
          {row.noindex ? <Tag>noindex</Tag> : null}
        </Space>
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 160,
      render: (_, row) => (
        <Space>
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить?" onConfirm={() => remove(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <div className="admin-toolbar">
        <span>
          {type === 'voices'
            ? 'Озвучки — студии перевода (LostFilm, дубляж). URL: /voice/{slug}/'
            : label + ' — URL: /' + urlPrefix + '/{slug}/'}
        </span>
        <Space>
          {type === 'voices' ? (
            allohaSyncing && voiceSyncProgress?.status !== 'stopped' ? (
              <Button icon={<StopOutlined />} onClick={() => void stopVoiceSync()}>
                Остановить
              </Button>
            ) : allohaSyncing ? (
              <Button disabled>Остановка…</Button>
            ) : voiceSyncProgress?.status === 'running' ? (
              <Button icon={<CloudDownloadOutlined />} onClick={() => void runVoiceSyncLoop(false)}>
                Продолжить
              </Button>
            ) : (
              <Button icon={<CloudDownloadOutlined />} onClick={() => void runVoiceSyncLoop(true)}>
                Загрузить из Alloha
              </Button>
            )
          ) : null}
          {type === 'voices' ? (
            <Popconfirm
              title="Удалить все озвучки?"
              description="Будут удалены все студии и их привязки к сериалам."
              okText="Удалить все"
              okButtonProps={{ danger: true }}
              cancelText="Отмена"
              onConfirm={() => void removeAllVoices()}
              disabled={allohaSyncing || deletingAll || items.length === 0}
            >
              <Button
                danger
                icon={<DeleteOutlined />}
                loading={deletingAll}
                disabled={allohaSyncing || items.length === 0}
              >
                Удалить все
              </Button>
            </Popconfirm>
          ) : null}
          <Button type="primary" onClick={openCreate}>Добавить</Button>
        </Space>
      </div>
      {type === 'voices' ? (
        <>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8 }}>
            В справочник попадают только озвучки, которые реально есть у сериалов. Полный каталог Alloha не загружается.
          </Typography.Paragraph>
          {voiceSyncProgress?.message || allohaSyncing ? (
            <div style={{ marginBottom: 16 }}>
              <Progress
                percent={voiceSyncPercent}
                status={voiceProgressBarStatus(voiceSyncProgress?.status, allohaSyncing)}
              />
              {voiceSyncProgress?.status === 'running' && voiceSyncProgress.current ? (
                <Typography.Text style={{ display: 'block' }}>
                  Сейчас: {voiceSyncProgress.current}
                </Typography.Text>
              ) : null}
              <Typography.Text type="secondary" style={{ display: 'block' }}>
                {voiceSyncProgress?.message
                  || `Обработано ${voiceSyncProgress?.processed ?? 0} из ${voiceSyncProgress?.total ?? 0}`}
              </Typography.Text>
              {(voiceSyncProgress?.total ?? 0) > 0 ? (
                <Typography.Text type="secondary" style={{ display: 'block' }}>
                  Привязано: {voiceSyncProgress?.synced ?? 0}, пропущено: {voiceSyncProgress?.skipped ?? 0}, ошибок: {voiceSyncProgress?.failed ?? 0}
                </Typography.Text>
              ) : null}
            </div>
          ) : null}
        </>
      ) : null}
      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={{ pageSize: 20 }} />

      <Modal
        title={editing ? `Редактировать: ${label}` : `Новый: ${label}`}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Form form={form} layout="vertical" onFinish={save}>
          <Form.Item label={type === 'years' ? 'Год' : 'Название'} name="name" rules={[{ required: true }]}>
            <Input placeholder={type === 'years' ? '1962' : type === 'voices' ? 'LostFilm' : undefined} />
          </Form.Item>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={type === 'years'
              ? 'Числовой год (YYYY). URL: /year/{slug}/'
              : (editing ? 'Можно изменить. URL: /' + urlPrefix + '/{slug}/' : 'Если пусто — из названия')}
          >
            <Input placeholder={type === 'years' ? '1962' : 'drama'} />
          </Form.Item>
          {type === 'people' ? (
            <>
              <Form.Item label="Фото URL" name="photo_url" extra="Внешний URL будет скачан на сервер при сохранении">
                <Input placeholder="/storage/posters/... или https://..." />
              </Form.Item>
              {photoUrl ? (
                <img
                  src={resolveMediaUrl(photoUrl)}
                  alt=""
                  style={{ width: 80, height: 106, objectFit: 'cover', borderRadius: 6, marginBottom: 12, display: 'block' }}
                />
              ) : null}
              <Upload
                beforeUpload={async (file) => {
                  if (!editing?.id) {
                    message.warning('Сначала сохраните актёра, затем загрузите фото')
                    return false
                  }
                  const fd = new FormData()
                  fd.append('photo', file)
                  try {
                    const res = await apiUpload<{ photo_url: string }>(`/api/admin/taxonomies/people/${editing.id}/photo`, fd)
                    form.setFieldValue('photo_url', res.photo_url)
                    message.success('Фото загружено')
                    await load()
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                  return false
                }}
                showUploadList={false}
                accept="image/*"
              >
                <Button style={{ marginBottom: 12 }} disabled={!editing?.id}>
                  Загрузить фото
                </Button>
              </Upload>
            </>
          ) : null}

          <Space wrap style={{ marginBottom: 12 }}>
            <Button icon={<CopyOutlined />} onClick={() => void openPromptModal()}>
              Промпт для ИИ
            </Button>
            <Button icon={<ImportOutlined />} onClick={openImportModal}>
              Импорт из ИИ
            </Button>
            <Button icon={<EditOutlined />} onClick={() => void openTemplateModal()}>
              Шаблон промпта
            </Button>
          </Space>

          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — из названия">
            <Input />
          </Form.Item>
          <Form.Item label="Meta description" name="meta_description">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="SEO-блок (HTML)" name="seo_html">
            <TemplateCodeEditor filePath="taxonomy-seo.html" isDark={isDark} height="220px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            HTML выводится внизу страницы справочника.
          </Typography.Paragraph>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Typography.Title level={5} style={{ marginTop: 8 }}>Блок на главной</Typography.Title>
          <Form.Item label="Показывать на главной" name="show_on_home" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item noStyle shouldUpdate={(prev, cur) => prev.show_on_home !== cur.show_on_home}>
            {({ getFieldValue }) =>
              getFieldValue('show_on_home') ? (
                <>
                  <Form.Item label="Заголовок секции" name="home_title" extra="Если пусто — название справочника">
                    <Input placeholder={type === 'years' ? 'Сериалы 2024' : 'Драмы'} />
                  </Form.Item>
                  <Form.Item label="Карточек в блоке" name="home_item_limit">
                    <InputNumber min={1} max={60} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item label="Сортировка по умолчанию" name="home_default_sort">
                    <Select options={HOME_SORT_OPTIONS} />
                  </Form.Item>
                  <Form.Item label="Вкладки AJAX" name="home_show_tabs" valuePropName="checked">
                    <Switch />
                  </Form.Item>
                </>
              ) : null
            }
          </Form.Item>
          <Form.Item label="Активен" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Скрыть страницу" name="is_hidden" valuePropName="checked" extra="Страница недоступна посетителям (404)">
            <Switch />
          </Form.Item>
          <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="Добавляет meta robots noindex">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Промпт для ИИ — SEO справочника"
        open={promptModalOpen}
        onCancel={() => setPromptModalOpen(false)}
        footer={[
          <Button key="template" onClick={() => void openTemplateModal()}>Шаблон</Button>,
          <Button key="copy" type="primary" icon={<CopyOutlined />} onClick={() => void copyPrompt()} disabled={!promptText.trim()}>
            Скопировать
          </Button>,
          <Button key="close" onClick={() => setPromptModalOpen(false)}>Закрыть</Button>,
        ]}
        width={820}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          Скопируйте промпт в ChatGPT/Claude, затем вставьте JSON-ответ через «Импорт из ИИ».
        </Typography.Paragraph>
        {promptLoading ? (
          <Typography.Text type="secondary">Загрузка…</Typography.Text>
        ) : (
          <Input.TextArea
            value={promptText}
            readOnly
            rows={18}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Шаблон промпта для SEO справочников"
        open={templateModalOpen}
        onCancel={() => setTemplateModalOpen(false)}
        onOk={() => void saveTemplate()}
        okText="Сохранить"
        cancelText="Отмена"
        confirmLoading={templateSaving}
        width={820}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          Плейсхолдеры: <Typography.Text code>{'{name}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{type}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{type_label}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{slug}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{url}'}</Typography.Text>.
          Также редактируется в Настройки → SEO → Промпты для ИИ.
        </Typography.Paragraph>
        <Space style={{ marginBottom: 12 }}>
          <Button
            disabled={templateLoading}
            onClick={() => setTemplateText(DEFAULT_TAXONOMY_SEO_AI_PROMPT)}
          >
            Сбросить к умолчанию
          </Button>
        </Space>
        {templateLoading ? (
          <Typography.Text type="secondary">Загрузка…</Typography.Text>
        ) : (
          <Input.TextArea
            value={templateText}
            onChange={(e) => setTemplateText(e.target.value)}
            rows={18}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Импорт SEO из ИИ"
        open={importModalOpen}
        onCancel={() => setImportModalOpen(false)}
        width={820}
        footer={(
          <Space>
            <Button onClick={runImportPreview}>Проверить</Button>
            <Button type="primary" onClick={applyImport} disabled={!importPreview}>
              Заполнить поля
            </Button>
            <Button onClick={() => setImportModalOpen(false)}>Отмена</Button>
          </Space>
        )}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Typography.Paragraph type="secondary">
          Вставьте JSON-ответ от ИИ (можно с markdown-блоком ```json). Сначала нажмите «Проверить», затем «Заполнить поля».
        </Typography.Paragraph>
        <Input.TextArea
          value={importText}
          onChange={(e) => {
            setImportText(e.target.value)
            setImportPreview(null)
            setImportError('')
          }}
          rows={10}
          placeholder={'{\n  "meta_title": "...",\n  "meta_description": "...",\n  "seo_html": "<p>...</p>"\n}'}
          style={{ fontFamily: 'monospace', fontSize: 12, marginBottom: 16 }}
        />
        {importError ? (
          <Typography.Paragraph type="danger" style={{ marginBottom: 12 }}>
            {importError}
          </Typography.Paragraph>
        ) : null}
        {importPreview ? (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            <div>
              <Typography.Text strong>Meta title</Typography.Text>
              <Typography.Paragraph style={{ marginBottom: 0 }}>
                {importPreview.meta_title || <Typography.Text type="secondary">пусто</Typography.Text>}
              </Typography.Paragraph>
            </div>
            <div>
              <Typography.Text strong>Meta description</Typography.Text>
              <Typography.Paragraph style={{ marginBottom: 0 }}>
                {importPreview.meta_description || <Typography.Text type="secondary">пусто</Typography.Text>}
              </Typography.Paragraph>
            </div>
            <div>
              <Typography.Text strong>SEO-блок (HTML)</Typography.Text>
              <Input.TextArea
                value={importPreview.seo_html}
                readOnly
                rows={6}
                style={{ fontFamily: 'monospace', fontSize: 12, marginTop: 4 }}
              />
            </div>
          </Space>
        ) : null}
      </Modal>
    </div>
  )
}

export default function TaxonomyPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const typeParam = searchParams.get('type')?.trim()
  const initialType = TYPES.some((t) => t.key === typeParam) ? (typeParam as TaxonomyType) : 'genres'
  const [activeType, setActiveType] = useState<TaxonomyType>(initialType)
  const deepLinkId = searchParams.get('id')?.trim() || null

  useEffect(() => {
    if (TYPES.some((t) => t.key === typeParam)) {
      setActiveType(typeParam as TaxonomyType)
    }
  }, [typeParam])

  const clearDeepLink = useCallback(() => {
    const next = new URLSearchParams(searchParams)
    next.delete('id')
    next.delete('type')
    setSearchParams(next, { replace: true })
  }, [searchParams, setSearchParams])

  return (
    <div className="admin-page-card">
      <Tabs
        activeKey={activeType}
        onChange={(key) => setActiveType(key as TaxonomyType)}
        items={TYPES.map((t) => ({
          key: t.key,
          label: t.label,
          children: (
            <TaxonomyTab
              type={t.key}
              label={t.label}
              urlPrefix={t.urlPrefix}
              deepLinkId={activeType === t.key ? deepLinkId : null}
              onDeepLinkHandled={clearDeepLink}
            />
          ),
        }))}
      />
    </div>
  )
}
