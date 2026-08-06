import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  PlusOutlined,
} from '@ant-design/icons'
import {
  Button,
  Col,
  Collapse,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Row,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import type { StudioItem, TaxonomyOption } from '../types'
import { BROADCAST_STATUSES, CONTENT_TYPES } from '../types'

type HomeSectionFilters = {
  content_type?: string
  broadcast_status?: string
  year_mode?: 'current_year'
  studio_id?: number
  genre_id?: number
  country_id?: number
  actor_id?: number
  director_id?: number
  year_from?: number
  year_to?: number
  kp_rating_min?: number
  imdb_rating_min?: number
  tmdb_popularity_min?: number
  views_min?: number
  is_coming_soon?: boolean
  popular_badge_active?: boolean
  has_poster?: boolean
  has_tmdb_id?: boolean
}

type HomeSectionItem = {
  id: number
  title: string
  link_url?: string | null
  filters?: HomeSectionFilters | null
  sort_order: number
  is_active: boolean
  item_limit: number
  show_tabs: boolean
  default_sort: 'latest' | 'popular' | 'rating'
  series_count?: number
}

type SelectOption = { value: number; label: string }

const SORT_OPTIONS = [
  { value: 'latest', label: 'Последние' },
  { value: 'popular', label: 'Популярные' },
  { value: 'rating', label: 'По рейтингу' },
]

const YEAR_MODE_OPTIONS = [
  { value: '', label: 'Вручную (год от/до)' },
  { value: 'current_year', label: 'Только текущий год' },
]

const BOOL_OPTIONS = [
  { value: true, label: 'Да' },
  { value: false, label: 'Нет' },
]

type ContentTypeOption = { value: string; label: string }

const DEFAULT_CONTENT_TYPE_OPTIONS: ContentTypeOption[] = CONTENT_TYPES.map((x) => ({
  value: x.value,
  label: x.label,
}))

function filterSummary(
  filters?: HomeSectionFilters | null,
  contentTypes: ContentTypeOption[] = DEFAULT_CONTENT_TYPE_OPTIONS,
): string {
  if (!filters || Object.keys(filters).length === 0) {
    return 'Без фильтров'
  }

  const parts: string[] = []
  if (filters.year_mode === 'current_year') parts.push('год: текущий')
  if (filters.content_type) {
    parts.push(contentTypes.find((x) => x.value === filters.content_type)?.label ?? filters.content_type)
  }
  if (filters.broadcast_status) {
    parts.push(BROADCAST_STATUSES.find((x) => x.value === filters.broadcast_status)?.label ?? filters.broadcast_status)
  }
  if (filters.genre_id) parts.push(`жанр #${filters.genre_id}`)
  if (filters.country_id) parts.push(`страна #${filters.country_id}`)
  if (filters.actor_id) parts.push(`актёр #${filters.actor_id}`)
  if (filters.director_id) parts.push(`реж. #${filters.director_id}`)
  if (filters.studio_id) parts.push(`студия #${filters.studio_id}`)
  if (filters.year_from || filters.year_to) {
    parts.push(`год ${filters.year_from ?? '…'}–${filters.year_to ?? '…'}`)
  }
  if (filters.kp_rating_min != null) parts.push(`КП ≥ ${filters.kp_rating_min}`)
  if (filters.imdb_rating_min != null) parts.push(`IMDb ≥ ${filters.imdb_rating_min}`)
  if (filters.tmdb_popularity_min != null) parts.push(`TMDB ≥ ${filters.tmdb_popularity_min}`)
  if (filters.views_min != null) parts.push(`просмотры ≥ ${filters.views_min}`)
  if (filters.is_coming_soon != null) parts.push(filters.is_coming_soon ? 'скоро' : 'не скоро')
  if (filters.popular_badge_active != null) parts.push(filters.popular_badge_active ? 'бейдж популярности' : 'без бейджа')
  if (filters.has_poster != null) parts.push(filters.has_poster ? 'с постером' : 'без постера')
  if (filters.has_tmdb_id != null) parts.push(filters.has_tmdb_id ? 'с TMDB' : 'без TMDB')

  return parts.length ? parts.join(', ') : 'Фильтры заданы'
}

function cleanFilters(values: HomeSectionFilters): HomeSectionFilters {
  const out: HomeSectionFilters = {}
  for (const [key, value] of Object.entries(values)) {
    if (value === undefined || value === null || value === '') continue
    ;(out as Record<string, unknown>)[key] = value
  }
  if (out.year_mode === 'current_year') {
    delete out.year_from
    delete out.year_to
  } else {
    delete out.year_mode
  }
  return out
}

export default function HomeSectionsPage() {
  const [items, setItems] = useState<HomeSectionItem[]>([])
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [taxonomy, setTaxonomy] = useState<{
    genres: TaxonomyOption[]
    countries: TaxonomyOption[]
    people: TaxonomyOption[]
  }>({ genres: [], countries: [], people: [] })
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<HomeSectionItem | null>(null)
  const [previewCount, setPreviewCount] = useState<number | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [contentTypeOptions, setContentTypeOptions] = useState<ContentTypeOption[]>(DEFAULT_CONTENT_TYPE_OPTIONS)
  const [form] = Form.useForm()

  const genreOptions = useMemo<SelectOption[]>(
    () => taxonomy.genres.map((g) => ({ value: g.id, label: g.name })),
    [taxonomy.genres],
  )
  const countryOptions = useMemo<SelectOption[]>(
    () => taxonomy.countries.map((c) => ({ value: c.id, label: c.name })),
    [taxonomy.countries],
  )
  const peopleOptions = useMemo<SelectOption[]>(
    () => taxonomy.people.map((p) => ({ value: p.id, label: p.name })),
    [taxonomy.people],
  )
  const studioOptions = useMemo<SelectOption[]>(
    () => studios.map((s) => ({ value: s.id, label: s.title })),
    [studios],
  )

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [sectionsData, taxonomyData, studiosData] = await Promise.all([
        api<{ items: HomeSectionItem[]; content_types?: ContentTypeOption[] }>('/api/admin/home-sections'),
        api<{ genres: TaxonomyOption[]; countries: TaxonomyOption[]; people: TaxonomyOption[] }>(
          '/api/admin/taxonomies/options',
        ),
        api<{ items: StudioItem[] }>('/api/admin/studios'),
      ])
      setItems(sectionsData.items)
      if (sectionsData.content_types?.length) {
        setContentTypeOptions(sectionsData.content_types)
      }
      setTaxonomy({
        genres: taxonomyData.genres ?? [],
        countries: taxonomyData.countries ?? [],
        people: taxonomyData.people ?? [],
      })
      setStudios(studiosData.items ?? [])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function refreshPreview(filters: HomeSectionFilters, itemLimit: number) {
    setPreviewLoading(true)
    try {
      const data = await api<{ count: number; shown: number }>('/api/admin/home-sections/preview', {
        method: 'POST',
        body: JSON.stringify({
          filters: cleanFilters(filters),
          item_limit: itemLimit,
        }),
      })
      setPreviewCount(data.count)
    } catch {
      setPreviewCount(null)
    } finally {
      setPreviewLoading(false)
    }
  }

  function openCreate() {
    setEditing(null)
    setPreviewCount(null)
    form.resetFields()
    form.setFieldsValue({
      is_active: true,
      item_limit: 18,
      show_tabs: true,
      default_sort: 'latest',
      sort_order: (items.length + 1) * 10,
      filters: { year_mode: '' },
    })
    setModalOpen(true)
  }

  function openEdit(row: HomeSectionItem) {
    setEditing(row)
    setPreviewCount(row.series_count ?? null)
    form.setFieldsValue({
      ...row,
      link_url: row.link_url ?? '',
      filters: {
        year_mode: '',
        ...(row.filters ?? {}),
      },
    })
    setModalOpen(true)
  }

  async function save(values: Record<string, unknown>) {
    try {
      const filters = cleanFilters((values.filters ?? {}) as HomeSectionFilters)
      const payload = {
        ...values,
        filters,
        link_url: String(values.link_url ?? '').trim() || null,
        ...(editing ? { id: editing.id } : {}),
      }
      await api('/api/admin/home-sections/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Секция обновлена' : 'Секция добавлена')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/home-sections/${id}`, { method: 'DELETE' })
      message.success('Секция удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function move(id: number, direction: -1 | 1) {
    const index = items.findIndex((i) => i.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= items.length) return

    const next = [...items]
    const tmp = next[index]
    next[index] = next[target]
    next[target] = tmp

    try {
      await api('/api/admin/home-sections/reorder', {
        method: 'POST',
        body: JSON.stringify({ ids: next.map((i) => i.id) }),
      })
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<HomeSectionItem> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    {
      title: 'Заголовок',
      dataIndex: 'title',
      render: (title, row) => (
        <Space direction="vertical" size={0}>
          <span>{title}</span>
          {row.link_url ? <span className="admin-empty-hint">{row.link_url}</span> : null}
        </Space>
      ),
    },
    {
      title: 'Фильтры',
      key: 'filters',
      render: (_, row) => (
        <Space direction="vertical" size={0}>
          <span className="admin-empty-hint">{filterSummary(row.filters, contentTypeOptions)}</span>
          <span>{row.series_count ?? 0} сериалов</span>
        </Space>
      ),
    },
    { title: 'Лимит', dataIndex: 'item_limit', width: 70 },
    {
      title: 'Вкладки',
      dataIndex: 'show_tabs',
      width: 90,
      render: (v) => (v ? <Tag color="blue">AJAX</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Сортировка',
      dataIndex: 'default_sort',
      width: 120,
      render: (v) => SORT_OPTIONS.find((o) => o.value === v)?.label ?? v,
    },
    {
      title: 'Статус',
      dataIndex: 'is_active',
      width: 100,
      render: (v) => (v ? <Tag color="green">Показ</Tag> : <Tag>Скрыта</Tag>),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 260,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => move(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => move(row.id, 1)} />
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить секцию?" onConfirm={() => remove(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  const yearMode = Form.useWatch(['filters', 'year_mode'], form)
  const watchedFilters = Form.useWatch('filters', form)
  const watchedLimit = Form.useWatch('item_limit', form)

  useEffect(() => {
    if (!modalOpen) return
    const timer = window.setTimeout(() => {
      refreshPreview((watchedFilters ?? {}) as HomeSectionFilters, Number(watchedLimit) || 18)
    }, 400)
    return () => window.clearTimeout(timer)
  }, [modalOpen, watchedFilters, watchedLimit])

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <p className="admin-empty-hint">
          Конструктор блоков на главной под студиями: задайте заголовок, фильтры и сортировку карточек.
        </p>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Добавить секцию</Button>
      </div>

      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={false} />

      <Modal
        title={editing ? 'Редактирование секции' : 'Новая секция главной'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        width={760}
      >
        <Form form={form} layout="vertical" onFinish={save}>
          <Row gutter={16}>
            <Col xs={24} md={14}>
              <Form.Item label="Заголовок на главной" name="title" rules={[{ required: true }]}>
                <Input placeholder="Новинки 2026" />
              </Form.Item>
            </Col>
            <Col xs={24} md={10}>
              <Form.Item label="Ссылка заголовка (необязательно)" name="link_url">
                <Input placeholder="/catalog/?year_from=2026" />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item label="Карточек в блоке" name="item_limit">
                <InputNumber min={1} max={60} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item label="Сортировка по умолчанию" name="default_sort">
                <Select options={SORT_OPTIONS} />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item label="Порядок" name="sort_order">
                <InputNumber style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item label="Вкладки AJAX" name="show_tabs" valuePropName="checked">
                <Switch />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item label="Показывать на сайте" name="is_active" valuePropName="checked">
                <Switch />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <div style={{ paddingTop: 30 }}>
                {previewLoading ? (
                  <span className="admin-empty-hint">Подсчёт…</span>
                ) : previewCount != null ? (
                  <Tag color="blue">Найдено: {previewCount}</Tag>
                ) : null}
              </div>
            </Col>
          </Row>

          <Collapse
            defaultActiveKey={['filters']}
            items={[
              {
                key: 'filters',
                label: 'Критерии отбора сериалов',
                children: (
                  <>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="Тип контента" name={['filters', 'content_type']}>
                          <Select allowClear placeholder="Все" options={contentTypeOptions.map((x) => ({ value: x.value, label: x.label }))} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Статус сериала" name={['filters', 'broadcast_status']}>
                          <Select allowClear placeholder="Все" options={BROADCAST_STATUSES.map((x) => ({ value: x.value, label: x.label }))} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Год" name={['filters', 'year_mode']}>
                          <Select options={YEAR_MODE_OPTIONS} />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="Студия" name={['filters', 'studio_id']}>
                          <Select allowClear placeholder="Все" options={studioOptions} showSearch optionFilterProp="label" />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Жанр" name={['filters', 'genre_id']}>
                          <Select allowClear placeholder="Все" options={genreOptions} showSearch optionFilterProp="label" />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Страна" name={['filters', 'country_id']}>
                          <Select allowClear placeholder="Все" options={countryOptions} showSearch optionFilterProp="label" />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="Актёр" name={['filters', 'actor_id']}>
                          <Select allowClear placeholder="Все" options={peopleOptions} showSearch optionFilterProp="label" />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Режиссёр" name={['filters', 'director_id']}>
                          <Select allowClear placeholder="Все" options={peopleOptions} showSearch optionFilterProp="label" />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Год от" name={['filters', 'year_from']}>
                          <InputNumber style={{ width: '100%' }} min={1900} max={2100} disabled={yearMode === 'current_year'} placeholder="Любой" />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="Год до" name={['filters', 'year_to']}>
                          <InputNumber style={{ width: '100%' }} min={1900} max={2100} disabled={yearMode === 'current_year'} placeholder="Любой" />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="КП ≥" name={['filters', 'kp_rating_min']}>
                          <InputNumber style={{ width: '100%' }} min={0} max={10} step={0.1} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="IMDb ≥" name={['filters', 'imdb_rating_min']}>
                          <InputNumber style={{ width: '100%' }} min={0} max={10} step={0.1} />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="TMDB поп. ≥" name={['filters', 'tmdb_popularity_min']}>
                          <InputNumber style={{ width: '100%' }} min={0} step={0.1} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Просмотры ≥" name={['filters', 'views_min']}>
                          <InputNumber style={{ width: '100%' }} min={0} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Раздел «Скоро»" name={['filters', 'is_coming_soon']}>
                          <Select allowClear placeholder="Не важно" options={BOOL_OPTIONS} />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col xs={24} md={8}>
                        <Form.Item label="Бейдж «Популярно»" name={['filters', 'popular_badge_active']}>
                          <Select allowClear placeholder="Не важно" options={BOOL_OPTIONS} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Есть постер" name={['filters', 'has_poster']}>
                          <Select allowClear placeholder="Не важно" options={BOOL_OPTIONS} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={8}>
                        <Form.Item label="Есть TMDB ID" name={['filters', 'has_tmdb_id']}>
                          <Select allowClear placeholder="Не важно" options={BOOL_OPTIONS} />
                        </Form.Item>
                      </Col>
                    </Row>
                  </>
                ),
              },
            ]}
          />
        </Form>
      </Modal>
    </div>
  )
}
