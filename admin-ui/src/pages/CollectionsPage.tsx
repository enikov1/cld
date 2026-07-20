import { Button, Col, Empty, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Switch, Table, Tag, Typography, Upload, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { apiUpload } from '../api/upload'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { CollectionItem, CollectionSeriesItem, SeriesItem, StudioItem } from '../types'

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
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [series, setSeries] = useState<SeriesItem[]>([])
  const [activeSlug, setActiveSlug] = useState<string>('')
  const [items, setItems] = useState<CollectionSeriesItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CollectionItem | null>(null)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [autoSyncing, setAutoSyncing] = useState(false)
  const [form] = Form.useForm()
  const [addForm] = Form.useForm()
  const watchedSeriesIds = (Form.useWatch('series_ids', form) as number[] | undefined) ?? []
  const watchedSlug = String(Form.useWatch('slug', form) ?? '').trim()
  const watchedTitle = String(Form.useWatch('title', form) ?? '').trim()

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

  const loadCollections = useCallback(async () => {
    const data = await api<{ items: CollectionItem[] }>('/api/admin/collections')
    setCollections(data.items)
  }, [])

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
    const data = await api<{ series_ids: number[] }>(`/api/admin/collections/${slug}/items`)
    return data.series_ids ?? []
  }, [])

  useEffect(() => {
    Promise.all([loadCollections(), loadSeries(), loadStudios()]).catch((e) => message.error(String((e as Error).message)))
  }, [loadCollections, loadSeries, loadStudios])

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
      const res = await api<{ item: CollectionItem; series_ids?: number[]; auto_sync?: { added: number; removed: number } }>(
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
      await loadItems(slug)
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
      await loadItems(activeSlug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeItem(seriesKey: string) {
    if (!activeSlug) return
    try {
      await api(`/api/admin/collections/${activeSlug}/items/${encodeURIComponent(seriesKey)}`, { method: 'DELETE' })
      message.success('Сериал удалён из подборки')
      await loadItems(activeSlug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const collectionColumns: ColumnsType<CollectionItem> = [
    { title: 'Slug (URL)', dataIndex: 'slug', key: 'slug', width: 160, render: (slug) => `/collections/${slug}/` },
    { title: 'Название', dataIndex: 'title', key: 'title' },
    { title: 'Порядок', dataIndex: 'sort_order', width: 80 },
    {
      title: 'SEO',
      key: 'seo',
      width: 80,
      render: (_, row) => (row.meta_title?.trim() || row.seo_html?.trim() ? <Tag color="blue">Есть</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Главная',
      key: 'home',
      width: 80,
      render: (_, row) => (row.show_on_home ? <Tag color="blue">Да</Tag> : null),
    },
    {
      title: 'Авто',
      key: 'auto',
      width: 70,
      render: (_, row) => (row.auto_add_enabled ? <Tag color="purple">Да</Tag> : null),
    },
    {
      title: '',
      key: 'pin',
      width: 90,
      render: (_, r) => (r.is_pinned ? <Tag color="orange">Закреплена</Tag> : null),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 150,
      render: (_, row) => (
        <>
          {row.is_active ? <Tag color="green">Активна</Tag> : <Tag>Выключена</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыта</Tag> : null}
          {row.noindex ? <Tag>noindex</Tag> : null}
        </>
      ),
    },
    {
      title: '',
      key: 'edit',
      width: 90,
      render: (_, row) => (
        <Button
          size="small"
          onClick={(e) => {
            e.stopPropagation()
            void openEdit(row)
          }}
        >
          Изменить
        </Button>
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
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={10}>
        <div className="admin-page-card">
          <div className="admin-toolbar">
            <span>Подборки — URL: /collections/{'{slug}'}/</span>
            <Button type="primary" onClick={openCreate}>Создать</Button>
          </div>
          <Table
            rowKey="id"
            columns={collectionColumns}
            dataSource={collections}
            pagination={false}
            size="small"
            onRow={(row) => ({
              onClick: () => setActiveSlug(row.slug),
              style: { cursor: 'pointer', background: row.slug === activeSlug ? '#e6f4ff' : undefined },
            })}
          />
        </div>
      </Col>

      <Col xs={24} xl={14}>
        <div className="admin-page-card">
          {!activeSlug ? (
            <Empty description="Выберите подборку слева" />
          ) : (
            <>
              <div className="admin-toolbar">
                <div>
                  <strong>{activeCollection?.title}</strong>
                  <div className="admin-empty-hint">/collections/{activeSlug}/</div>
                </div>
                <Button type="primary" onClick={() => setAddModalOpen(true)} disabled={!series.length}>
                  Добавить сериалы
                </Button>
              </div>
              <Table rowKey="id" loading={loading} columns={itemColumns} dataSource={items} pagination={false} size="small" />
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
            label="Сериалы"
            name="series_ids"
            extra="Ручной выбор сериалов в подборке. Автоматически добавленные сериалы тоже отображаются здесь."
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
    </Row>
  )
}
