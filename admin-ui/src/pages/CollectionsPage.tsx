import { CopyOutlined, DeleteOutlined, EditOutlined, ExportOutlined } from '@ant-design/icons'
import { Button, Col, Empty, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Space, Switch, Table, Tag, Tooltip, Typography, Upload, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api, apiUpload } from '../api/client'
import EntitySeoAiControls from '../components/EntitySeoAiControls'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { CollectionItem, CollectionSeriesItem, SeriesItem, StudioItem } from '../types'
import {
  LARGE_PROMPT_THRESHOLD,
  downloadTextFile,
  formatCharCount,
  type AiImportPreview,
  type AiPromptResponse,
} from '../utils/collectionAiPrompt'
import {
  COLLECTION_SEO_AI_PROMPT_KEY,
  DEFAULT_COLLECTION_SEO_AI_PROMPT,
} from '../utils/entitySeoAiPrompt'
import { siteOrigin } from '../utils/mediaUrl'

function collectionPublicPath(slug: string): string {
  return `/collections/${slug}/`
}

function collectionPublicUrl(slug: string): string {
  return `${siteOrigin()}${collectionPublicPath(slug)}`
}

function mergeSeriesOptions(
  base: { value: number; label: string }[],
  selectedIds: number[],
  series: SeriesItem[],
) {
  const map = new Map<number, { value: number; label: string }>()
  for (const option of base) {
    map.set(option.value, option)
  }
  for (const id of selectedIds) {
    if (map.has(id)) continue
    const item = series.find((s) => s.id === id)
    if (item) {
      map.set(id, { value: id, label: `${item.title} (${item.kp_id || item.tmdb_id || item.id})` })
    }
  }
  return Array.from(map.values())
}

export default function CollectionsPage() {
  const { isDark } = useAdminTheme()
  const [collections, setCollections] = useState<CollectionItem[]>([])
  const [collectionsTotal, setCollectionsTotal] = useState(0)
  const [collectionsPage, setCollectionsPage] = useState(1)
  const [collectionsPerPage, setCollectionsPerPage] = useState(50)
  const [collectionsQuery, setCollectionsQuery] = useState('')
  const [collectionsSearch, setCollectionsSearch] = useState('')
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [series, setSeries] = useState<SeriesItem[]>([])
  const [activeSlug, setActiveSlug] = useState<string>('')
  const [items, setItems] = useState<CollectionSeriesItem[]>([])
  const [loading, setLoading] = useState(false)
  const [collectionsLoading, setCollectionsLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CollectionItem | null>(null)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [autoSyncing, setAutoSyncing] = useState(false)
  const [promptModalOpen, setPromptModalOpen] = useState(false)
  const [promptLoading, setPromptLoading] = useState(false)
  const [promptData, setPromptData] = useState<AiPromptResponse | null>(null)
  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importPayload, setImportPayload] = useState('')
  const [importLoading, setImportLoading] = useState(false)
  const [importPreview, setImportPreview] = useState<AiImportPreview | null>(null)
  const [importCreating, setImportCreating] = useState(false)
  const [form] = Form.useForm()
  const [addForm] = Form.useForm()
  const watchedSeriesIds = (Form.useWatch('series_ids', form) as number[] | undefined) ?? []
  const watchedSlug = String(Form.useWatch('slug', form) ?? '').trim()
  const watchedTitle = String(Form.useWatch('title', form) ?? '').trim()

  useDocumentTitle(
    importModalOpen
      ? 'Импорт подборок из ИИ'
      : promptModalOpen
        ? 'Промпт для ИИ'
        : addModalOpen
          ? 'Добавить сериалы в подборку'
          : modalOpen
            ? editing
              ? `Редактируем подборку — ${editing.title}`
              : 'Новая подборка'
            : null,
  )
  useBusyFavicon(autoSyncing || promptLoading || importLoading || importCreating)

  const seriesOptions = useMemo(
    () => mergeSeriesOptions(
      series.map((s) => ({
        value: s.id,
        label: `${s.title} (${s.kp_id || s.tmdb_id || s.id})`,
      })),
      watchedSeriesIds,
      series,
    ),
    [series, watchedSeriesIds],
  )

  const activeCollection = useMemo(
    () => collections.find((c) => c.slug === activeSlug) ?? null,
    [collections, activeSlug],
  )

  const loadStudios = useCallback(async () => {
    const data = await api<{ items: StudioItem[] }>('/api/admin/studios')
    setStudios(data.items)
  }, [])

  const studioOptions = useMemo(
    () => studios.map((s) => ({ value: s.id, label: s.title })),
    [studios],
  )

  const loadCollections = useCallback(async (page = collectionsPage, perPage = collectionsPerPage, q = collectionsQuery) => {
    setCollectionsLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('page', String(page))
      params.set('per_page', String(perPage))
      if (q.trim()) params.set('q', q.trim())
      const data = await api<{ items: CollectionItem[]; total: number; page: number; per_page: number }>(
        `/api/admin/collections?${params}`,
      )
      setCollections(data.items)
      setCollectionsTotal(data.total)
      setCollectionsPage(data.page)
      setCollectionsPerPage(data.per_page)
    } finally {
      setCollectionsLoading(false)
    }
  }, [collectionsPage, collectionsPerPage, collectionsQuery])

  const loadSeries = useCallback(async () => {
    const data = await api<{ items: SeriesItem[] }>('/api/admin/series?per_page=2000')
    setSeries(data.items)
  }, [])

  const loadItems = useCallback(async (slug: string) => {
    if (!slug) {
      setItems([])
      return
    }
    setLoading(true)
    try {
      const data = await api<{ items: CollectionSeriesItem[] }>(`/api/admin/collections/${slug}/items`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadCollectionSeriesIds = useCallback(async (slug: string) => {
    const data = await api<{ series_ids: number[]; manual_series_ids?: number[] }>(`/api/admin/collections/${slug}/items`)
    return data.manual_series_ids ?? data.series_ids ?? []
  }, [])

  useEffect(() => {
    Promise.all([loadCollections(1, collectionsPerPage, ''), loadSeries(), loadStudios()]).catch((e) => message.error(String((e as Error).message)))
  }, [loadSeries, loadStudios]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    loadItems(activeSlug)
  }, [activeSlug, loadItems])

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({
      is_active: true,
      is_pinned: false,
      show_on_home: false,
      is_hidden: false,
      noindex: false,
      auto_add_enabled: false,
      auto_keywords: [],
      series_ids: [],
      sort_order: 0,
      seo_html: '',
    })
    setModalOpen(true)
  }

  async function openEdit(row: CollectionItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
      auto_keywords: row.auto_keywords ?? [],
      auto_add_enabled: row.auto_add_enabled ?? false,
      series_ids: [],
    })
    setModalOpen(true)
    try {
      const seriesIds = await loadCollectionSeriesIds(row.slug)
      form.setFieldValue('series_ids', seriesIds)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function saveCollection(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      const res = await api<{ item: CollectionItem; series_ids?: number[]; manual_series_ids?: number[]; auto_sync?: { added: number; removed: number } }>(
        '/api/admin/collections/upsert',
        {
          method: 'POST',
          body: JSON.stringify(payload),
        },
      )
      const autoSync = res.auto_sync
      if (autoSync && (autoSync.added > 0 || autoSync.removed > 0)) {
        message.success(`Подборка сохранена. Автоподбор: +${autoSync.added}, −${autoSync.removed}`)
      } else {
        message.success(editing ? 'Подборка обновлена' : 'Подборка создана')
      }
      setModalOpen(false)
      setActiveSlug(res.item.slug)
      await loadCollections()
      await loadItems(res.item.slug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function runAutoSync() {
    const slug = editing?.slug || activeSlug
    if (!slug) {
      message.warning('Сначала сохраните подборку')
      return
    }
    setAutoSyncing(true)
    try {
      const res = await api<{ added: number; removed: number }>(`/api/admin/collections/${slug}/auto-sync`, {
        method: 'POST',
      })
      message.success(`Автоподбор: добавлено ${res.added}, удалено ${res.removed}`)
      if (editing) {
        const seriesIds = await loadCollectionSeriesIds(slug)
        form.setFieldValue('series_ids', seriesIds)
      }
      await Promise.all([loadItems(slug), loadCollections()])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setAutoSyncing(false)
    }
  }

  async function addSeries(values: { series_ids: number[] }) {
    if (!activeSlug) return
    try {
      const res = await api<{ ok: boolean; added: number; skipped: number }>(`/api/admin/collections/${activeSlug}/items`, {
        method: 'POST',
        body: JSON.stringify({
          items: values.series_ids.map((series_id, i) => ({ series_id, rank_order: i + 1 })),
        }),
      })
      if (res.skipped > 0) {
        message.warning(`Добавлено: ${res.added}, пропущено: ${res.skipped}`)
      } else {
        message.success('Сериалы добавлены в подборку')
      }
      setAddModalOpen(false)
      addForm.resetFields()
      await Promise.all([loadItems(activeSlug), loadCollections()])
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeItem(seriesKey: string) {
    if (!activeSlug) return
    try {
      await api(`/api/admin/collections/${activeSlug}/items/${encodeURIComponent(seriesKey)}`, { method: 'DELETE' })
      message.success('Сериал удалён из подборки')
      await Promise.all([loadItems(activeSlug), loadCollections()])
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeCollection(row: CollectionItem) {
    try {
      await api(`/api/admin/collections/${encodeURIComponent(row.slug)}`, { method: 'DELETE' })
      message.success('Подборка удалена')
      if (activeSlug === row.slug) {
        setActiveSlug('')
        setItems([])
      }
      await loadCollections()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function openPromptModal() {
    setPromptModalOpen(true)
    setPromptLoading(true)
    setPromptData(null)
    try {
      const data = await api<AiPromptResponse>('/api/admin/collections/ai-prompt')
      setPromptData(data)
    } catch (e) {
      message.error(String((e as Error).message))
      setPromptModalOpen(false)
    } finally {
      setPromptLoading(false)
    }
  }

  async function copyPrompt() {
    if (!promptData?.prompt) return
    try {
      await navigator.clipboard.writeText(promptData.prompt)
      message.success('Промпт скопирован в буфер обмена')
    } catch {
      message.error('Не удалось скопировать — попробуйте скачать файл')
    }
  }

  function downloadPrompt() {
    if (!promptData?.prompt) return
    downloadTextFile('collections-ai-prompt.txt', promptData.prompt)
    message.success('Файл скачан')
  }

  function openImportModal() {
    setImportModalOpen(true)
    setImportPayload('')
    setImportPreview(null)
  }

  async function runImportPreview() {
    const payload = importPayload.trim()
    if (!payload) {
      message.warning('Вставьте JSON-ответ от ИИ')
      return
    }
    setImportLoading(true)
    try {
      const res = await api<AiImportPreview>('/api/admin/collections/ai-import', {
        method: 'POST',
        body: JSON.stringify({ payload, dry_run: true }),
      })
      setImportPreview(res)
      if (res.errors.length) {
        message.warning(res.errors.join(' '))
      } else if (res.items.length === 0) {
        message.warning('Нет подборок для создания')
      } else {
        message.success(`Готово к созданию: ${res.items.length}`)
      }
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setImportLoading(false)
    }
  }

  async function runImportCreate() {
    const payload = importPayload.trim()
    if (!payload) {
      message.warning('Сначала вставьте JSON и проверьте')
      return
    }
    setImportCreating(true)
    try {
      const res = await api<AiImportPreview>('/api/admin/collections/ai-import', {
        method: 'POST',
        body: JSON.stringify({ payload, dry_run: false }),
      })
      setImportPreview(res)
      if (res.created > 0) {
        message.success(`Создано подборок: ${res.created}`)
        await loadCollections()
        setImportModalOpen(false)
      } else {
        message.warning('Подборки не созданы — проверьте предпросмотр')
      }
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setImportCreating(false)
    }
  }

  const importPreviewColumns: ColumnsType<AiImportPreview['items'][number]> = [
    { title: 'Название', dataIndex: 'title', key: 'title' },
    { title: 'Slug', dataIndex: 'slug', key: 'slug', width: 140 },
    { title: 'Сериалов', dataIndex: 'series_count', key: 'series_count', width: 90 },
    {
      title: 'Keywords',
      key: 'keywords',
      width: 160,
      render: (_, row) => (row.auto_keywords?.length ? row.auto_keywords.join(', ') : '—'),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 100,
      render: (_, row) => (
        <Tag color={row.status === 'created' ? 'green' : row.status === 'ready' ? 'blue' : 'default'}>
          {row.status === 'created' ? 'Создана' : row.status === 'ready' ? 'Готово' : row.status}
        </Tag>
      ),
    },
  ]

  async function copyCollectionLink(slug: string) {
    try {
      await navigator.clipboard.writeText(collectionPublicUrl(slug))
      message.success('Ссылка скопирована')
    } catch {
      message.error('Не удалось скопировать')
    }
  }

  const collectionColumns: ColumnsType<CollectionItem> = [
    {
      title: 'Название',
      dataIndex: 'title',
      key: 'title',
      ellipsis: true,
      render: (title, row) => (
        <div>
          <div>{title}</div>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {collectionPublicPath(row.slug)}
          </Typography.Text>
        </div>
      ),
    },
    {
      title: '#',
      key: 'items_count',
      width: 56,
      render: (_, row) => row.items_count ?? 0,
    },
    {
      title: 'Метки',
      key: 'badges',
      width: 120,
      render: (_, row) => (
        <Space size={[4, 4]} wrap>
          {row.meta_title?.trim() || row.seo_html?.trim() ? <Tag color="blue">SEO</Tag> : null}
          {row.show_on_home ? <Tag color="cyan">Главная</Tag> : null}
          {row.auto_add_enabled ? <Tag color="purple">Авто</Tag> : null}
          {row.is_pinned ? <Tag color="orange">Pin</Tag> : null}
        </Space>
      ),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 100,
      render: (_, row) => (
        <Space size={[4, 4]} wrap>
          {row.is_active ? <Tag color="green">Активна</Tag> : <Tag>Off</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыта</Tag> : null}
        </Space>
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 144,
      fixed: 'right',
      render: (_, row) => (
        <Space size={4} onClick={(e) => e.stopPropagation()}>
          <Tooltip title="Открыть на сайте">
            <Button
              size="small"
              icon={<ExportOutlined />}
              href={collectionPublicUrl(row.slug)}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Открыть на сайте"
            />
          </Tooltip>
          <Tooltip title="Скопировать ссылку">
            <Button
              size="small"
              icon={<CopyOutlined />}
              onClick={() => void copyCollectionLink(row.slug)}
              aria-label="Скопировать ссылку"
            />
          </Tooltip>
          <Tooltip title="Изменить">
            <Button
              size="small"
              icon={<EditOutlined />}
              onClick={() => void openEdit(row)}
              aria-label="Изменить"
            />
          </Tooltip>
          <Popconfirm
            title="Удалить подборку?"
            description={`«${row.title}» и все сериалы в ней будут отвязаны.`}
            onConfirm={() => void removeCollection(row)}
            okText="Удалить"
            cancelText="Отмена"
            okButtonProps={{ danger: true }}
          >
            <Tooltip title="Удалить">
              <Button size="small" danger icon={<DeleteOutlined />} aria-label="Удалить" />
            </Tooltip>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  const itemColumns: ColumnsType<CollectionSeriesItem> = [
    { title: '#', dataIndex: 'rank_order', key: 'rank_order', width: 60 },
    { title: 'ID', key: 'id', width: 80, render: (_, r) => r.series?.id ?? '—' },
    { title: 'KP / TMDB', key: 'ext_id', width: 120, render: (_, r) => r.series?.kp_id || r.series?.tmdb_id || '—' },
    { title: 'Название', key: 'title', render: (_, r) => r.series?.title ?? '—' },
    { title: 'Год', key: 'year', width: 80, render: (_, r) => r.series?.year ?? '—' },
    {
      title: 'Источник',
      key: 'source',
      width: 90,
      render: (_, r) => (r.is_auto ? <Tag color="purple">Авто</Tag> : <Tag>Ручной</Tag>),
    },
    {
      title: '',
      key: 'actions',
      width: 100,
      render: (_, r) =>
        r.series?.id ? (
          <Popconfirm title="Убрать из подборки?" onConfirm={() => removeItem(String(r.series!.id))}>
            <Button size="small" danger type="link">Убрать</Button>
          </Popconfirm>
        ) : null,
    },
  ]

  const coverSlug = editing?.slug || watchedSlug

  function resolveCoverUploadSlug(): string {
    return String(editing?.slug ?? form.getFieldValue('slug') ?? '').trim()
  }

  function resolveCoverUploadTitle(): string {
    return String(form.getFieldValue('title') ?? '').trim()
  }

  async function uploadCollectionImage(file: File, variant: 'cover' | 'banner') {
    const slug = resolveCoverUploadSlug()
    const title = resolveCoverUploadTitle()
    if (!slug && !title) {
      message.warning('Сначала укажите slug или название подборки')
      return false
    }
    const fd = new FormData()
    fd.append('cover', file)
    fd.append('variant', variant)
    if (title) {
      fd.append('title', title)
    }
    try {
      const res = await apiUpload<{ cover_url?: string | null; home_banner_url?: string | null; slug?: string }>(
        `/api/admin/collections/${encodeURIComponent(slug || '_draft')}/cover`,
        fd,
      )
      if (variant === 'banner') {
        form.setFieldValue('home_banner_url', res.home_banner_url ?? '')
      } else {
        form.setFieldValue('cover_url', res.cover_url ?? '')
      }
      if (!slug && res.slug) {
        form.setFieldValue('slug', res.slug)
      }
      message.success(variant === 'banner' ? 'Баннер загружен' : 'Обложка загружена')
    } catch (e) {
      message.error(String((e as Error).message))
    }
    return false
  }

  return (
    <Row gutter={[16, 16]} className="collections-page">
      <Col xs={24} xl={13}>
        <div className="admin-page-card collections-page__list">
          <div className="admin-toolbar collections-page__toolbar">
            <div className="collections-page__toolbar-title">
              <strong>Подборки</strong>
              <Typography.Text type="secondary">/collections/{'{slug}'}/</Typography.Text>
            </div>
            <Space wrap className="collections-page__toolbar-actions">
              <Input.Search
                allowClear
                placeholder="Поиск по названию или slug"
                value={collectionsSearch}
                onChange={(e) => setCollectionsSearch(e.target.value)}
                onSearch={(value) => {
                  setCollectionsQuery(value)
                  void loadCollections(1, collectionsPerPage, value)
                }}
                style={{ width: 220 }}
              />
              <Button onClick={() => void openPromptModal()}>Промпт для ИИ</Button>
              <Button onClick={openImportModal}>Импорт из ИИ</Button>
              <Button type="primary" onClick={openCreate}>Создать</Button>
            </Space>
          </div>
          <Table
            rowKey="id"
            loading={collectionsLoading}
            columns={collectionColumns}
            dataSource={collections}
            pagination={{
              current: collectionsPage,
              pageSize: collectionsPerPage,
              total: collectionsTotal,
              showSizeChanger: true,
              pageSizeOptions: ['20', '50', '100'],
              onChange: (page, pageSize) => {
                void loadCollections(page, pageSize, collectionsQuery)
              },
            }}
            size="small"
            scroll={{ x: 680 }}
            onRow={(row) => ({
              onClick: () => setActiveSlug(row.slug),
              style: { cursor: 'pointer', background: row.slug === activeSlug ? '#e6f4ff' : undefined },
            })}
          />
        </div>
      </Col>

      <Col xs={24} xl={11}>
        <div className="admin-page-card">
          {!activeSlug ? (
            <Empty description="Выберите подборку слева" />
          ) : (
            <>
              <div className="admin-toolbar">
                <div>
                  <strong>{activeCollection?.title}</strong>
                  <div className="admin-empty-hint">{collectionPublicPath(activeSlug)}</div>
                </div>
                <Space wrap>
                  <Tooltip title="Открыть на сайте">
                    <Button
                      icon={<ExportOutlined />}
                      href={collectionPublicUrl(activeSlug)}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      На сайте
                    </Button>
                  </Tooltip>
                  <Button icon={<CopyOutlined />} onClick={() => void copyCollectionLink(activeSlug)}>
                    Ссылка
                  </Button>
                  <Button onClick={() => activeCollection && void openEdit(activeCollection)}>Изменить</Button>
                  {activeCollection ? (
                    <Popconfirm
                      title="Удалить подборку?"
                      description={`«${activeCollection.title}» будет удалена без возможности восстановления.`}
                      onConfirm={() => void removeCollection(activeCollection)}
                      okText="Удалить"
                      cancelText="Отмена"
                      okButtonProps={{ danger: true }}
                    >
                      <Button danger icon={<DeleteOutlined />}>Удалить</Button>
                    </Popconfirm>
                  ) : null}
                  <Button type="primary" onClick={() => setAddModalOpen(true)} disabled={!series.length}>
                    Добавить сериалы
                  </Button>
                </Space>
              </div>
              <Table rowKey="id" loading={loading} columns={itemColumns} dataSource={items} pagination={false} size="small" scroll={{ x: 720 }} />
            </>
          )}
        </div>
      </Col>

      <Modal
        title={editing ? 'Редактировать подборку' : 'Новая подборка'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
        footer={(_, { OkBtn, CancelBtn }) => (
          <>
            <Button
              onClick={() => void runAutoSync()}
              loading={autoSyncing}
              disabled={!form.getFieldValue('auto_add_enabled')}
            >
              Применить автоподбор
            </Button>
            <CancelBtn />
            <OkBtn />
          </>
        )}
      >
        <Form form={form} layout="vertical" onFinish={saveCollection}>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={editing ? 'Slug нельзя изменить после создания' : 'Если пусто — будет создан автоматически из названия'}
          >
            <Input placeholder="novinki-2026" disabled={!!editing} />
          </Form.Item>
          <Form.Item label="Название" name="title" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Студия" name="studio_id" extra="Опционально — привязка подборки к студии">
            <Select allowClear options={studioOptions} placeholder="Не выбрана" />
          </Form.Item>
          <Form.Item
            label="Сериалы (вручную)"
            name="series_ids"
            extra="Только ручные позиции. Автодобавленные по ключевым словам сохраняются отдельно и здесь не редактируются."
          >
            <Select
              mode="multiple"
              allowClear
              showSearch
              options={seriesOptions}
              optionFilterProp="label"
              placeholder="Выберите один или несколько"
              disabled={!series.length}
            />
          </Form.Item>
          <Form.Item label="Автодобавление по словам" name="auto_add_enabled" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item
            label="Ключевые слова"
            name="auto_keywords"
            extra="Через запятую или Enter. Ищется в названии, описании и жанрах сериала (без учёта регистра)."
          >
            <Select
              mode="tags"
              tokenSeparators={[',', ';']}
              placeholder="вампир, vampire, упыри"
              open={false}
            />
          </Form.Item>
          <EntitySeoAiControls
            form={form}
            settingKey={COLLECTION_SEO_AI_PROMPT_KEY}
            defaultTemplate={DEFAULT_COLLECTION_SEO_AI_PROMPT}
            entityLabel="подборки"
            buildVars={() => {
              const name = String(form.getFieldValue('title') || '').trim()
              if (!name) return null
              const slug = String(form.getFieldValue('slug') || editing?.slug || '').trim()
              return {
                name,
                slug,
                url: `/collections/${slug || '{slug}'}/`,
              }
            }}
          />
          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — «{название} — подборка сериалов»">
            <Input />
          </Form.Item>
          <Form.Item label="Описание" name="description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item label="Meta description" name="meta_description" extra="Если пусто — из описания или шаблона">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="SEO-блок (HTML)" name="seo_html" extra="Выводится внизу страницы подборки">
            <TemplateCodeEditor filePath="collection-seo.html" isDark={isDark} height="220px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            HTML-текст для SEO внизу страницы подборки.
          </Typography.Paragraph>
          <Form.Item label="Обложка для каталога (4:3)" name="cover_url" extra="Используется на странице /collections/">
            <Input />
          </Form.Item>
          <Upload
            beforeUpload={(file) => uploadCollectionImage(file, 'cover')}
            showUploadList={false}
            accept="image/*"
          >
            <Button style={{ marginBottom: 12 }} disabled={!coverSlug && !watchedTitle}>
              Загрузить обложку 4:3
            </Button>
          </Upload>
          <Form.Item label="Баннер для главной (16:4)" name="home_banner_url" extra="Широкий баннер для блока на главной странице">
            <Input />
          </Form.Item>
          <Upload
            beforeUpload={(file) => uploadCollectionImage(file, 'banner')}
            showUploadList={false}
            accept="image/*"
          >
            <Button style={{ marginBottom: 12 }} disabled={!coverSlug && !watchedTitle}>
              Загрузить баннер 16:4
            </Button>
          </Upload>
          <Row gutter={16}>
            <Col span={6}>
              <Form.Item label="Порядок" name="sort_order"><InputNumber style={{ width: '100%' }} /></Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Закрепить" name="is_pinned" valuePropName="checked"><Switch /></Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="На главной" name="show_on_home" valuePropName="checked" extra="Промо-блок">
                <Switch />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Активна" name="is_active" valuePropName="checked"><Switch /></Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={6}>
              <Form.Item label="Скрыть" name="is_hidden" valuePropName="checked"><Switch /></Form.Item>
            </Col>
          </Row>
          <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="meta robots noindex">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Добавить сериалы в подборку"
        open={addModalOpen}
        onCancel={() => setAddModalOpen(false)}
        onOk={() => addForm.submit()}
        okText="Добавить"
        cancelText="Отмена"
        destroyOnHidden
      >
        <Form form={addForm} layout="vertical" onFinish={addSeries}>
          <Form.Item label="Сериалы" name="series_ids" rules={[{ required: true, message: 'Выберите сериалы' }]}>
            <Select mode="multiple" options={seriesOptions} optionFilterProp="label" placeholder="Выберите один или несколько" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Промпт для ИИ"
        open={promptModalOpen}
        onCancel={() => setPromptModalOpen(false)}
        width={920}
        footer={(_, { CancelBtn }) => (
          <Space>
            <Button onClick={() => void copyPrompt()} disabled={!promptData?.prompt}>Скопировать</Button>
            <Button onClick={downloadPrompt} disabled={!promptData?.prompt}>Скачать .txt</Button>
            <CancelBtn />
          </Space>
        )}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        {promptLoading ? (
          <Typography.Paragraph type="secondary">Сборка промпта из базы…</Typography.Paragraph>
        ) : promptData ? (
          <>
            <Typography.Paragraph type="secondary">
              Сериалов: {promptData.series_count}, существующих подборок: {promptData.collections_count},
              {' '}размер: {formatCharCount(promptData.char_count)} символов
            </Typography.Paragraph>
            {promptData.char_count > LARGE_PROMPT_THRESHOLD ? (
              <Typography.Paragraph type="warning">
                Промпт очень большой — рекомендуется скачать файл, а не копировать в буфер.
              </Typography.Paragraph>
            ) : null}
            <Input.TextArea value={promptData.prompt} readOnly rows={18} style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </>
        ) : null}
      </Modal>

      <Modal
        title="Импорт подборок из ИИ"
        open={importModalOpen}
        onCancel={() => setImportModalOpen(false)}
        width={960}
        footer={(_, { CancelBtn }) => (
          <Space>
            <Button onClick={() => void runImportPreview()} loading={importLoading}>Проверить</Button>
            <Button
              type="primary"
              onClick={() => void runImportCreate()}
              loading={importCreating}
              disabled={!importPreview?.items.length}
            >
              Создать подборки
            </Button>
            <CancelBtn />
          </Space>
        )}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Typography.Paragraph type="secondary">
          Вставьте JSON-ответ от ИИ (можно с markdown-блоком ```json). Сначала нажмите «Проверить», затем «Создать подборки».
        </Typography.Paragraph>
        <Input.TextArea
          value={importPayload}
          onChange={(e) => {
            setImportPayload(e.target.value)
            setImportPreview(null)
          }}
          rows={10}
          placeholder='{"collections":[{"title":"...","slug":"...","series_ids":[1,2]}]}'
          style={{ fontFamily: 'monospace', fontSize: 12, marginBottom: 16 }}
        />
        {importPreview?.errors.length ? (
          <Typography.Paragraph type="danger" style={{ marginBottom: 12 }}>
            {importPreview.errors.join(' ')}
          </Typography.Paragraph>
        ) : null}
        {importPreview?.skipped.length ? (
          <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
            Пропущено: {importPreview.skipped.map((s) => `${s.title || `#${s.index + 1}`} (${s.reason})`).join('; ')}
          </Typography.Paragraph>
        ) : null}
        {importPreview?.items.length ? (
          <Table
            rowKey={(row) => `${row.slug}-${row.index}`}
            size="small"
            pagination={false}
            columns={importPreviewColumns}
            dataSource={importPreview.items}
          />
        ) : null}
      </Modal>
    </Row>
  )
}
