import {
  Button,
  Card,
  Col,
  DatePicker,
  Drawer,
  Form,
  Input,
  InputNumber,
  Popconfirm,
  Row,
  Select,
  Space,
  Switch,
  Table,
  Tabs,
  Tag,
  Tooltip,
  Upload,
  message,
} from 'antd'
import {
  CheckOutlined,
  CopyOutlined,
  DeleteOutlined,
  EditOutlined,
  EyeInvisibleOutlined,
  EyeOutlined,
  ExportOutlined,
  PushpinFilled,
  PushpinOutlined,
  UndoOutlined,
} from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useRef, useState, type CSSProperties } from 'react'
import { useSearchParams } from 'react-router-dom'
import dayjs from 'dayjs'
import { api, apiUpload } from '../api/client'
import SeriesPlayersEditor, { type SeriesPlayersEditorHandle } from '../components/SeriesPlayersEditor'
import SeriesLookupSearch from '../components/SeriesLookupSearch'
import { useSeriesDeepLink } from '../hooks/useSeriesDeepLink'
import SeriesScheduleEditor, { type SeriesScheduleEditorHandle } from '../components/SeriesScheduleEditor'
import type { CollectionItem, SeriesItem, StudioItem, TaxonomyOption } from '../types'
import { BROADCAST_STATUSES, CONTENT_TYPES } from '../types'
import { resolveMediaUrl, siteOrigin } from '../utils/mediaUrl'
import { buildDescriptionAiPrompt } from '../utils/descriptionAiPrompt'

function seriesPublicPath(item: SeriesItem): string {
  const yearNum = Number(item.year || item.start_year || 0)
  let year = yearNum >= 1900 && yearNum <= 2100 ? String(yearNum) : ''
  if (!year && item.premiere_date) {
    const premiereYear = Number(String(item.premiere_date).slice(0, 4))
    if (premiereYear >= 1900 && premiereYear <= 2100) {
      year = String(premiereYear)
    }
  }
  if (!year) year = '0000'
  const slug = (item.slug || '').trim() || 'series'
  return `/${item.id}-${slug}-${year}.html`
}

type SelectOption = { value: number; label: string }

type SeriesListFilters = {
  q?: string
  content_type?: string
  broadcast_status?: string
  studio_id?: number
  genre_id?: number
  country_id?: number
  actor_id?: number
  director_id?: number
  is_active?: string
  is_hidden?: string
  is_pinned?: string
  is_coming_soon?: string
  noindex?: string
  popular_badge_active?: string
  has_poster?: string
  has_tmdb_id?: string
  year_from?: number
  year_to?: number
  kp_rating_min?: number
  imdb_rating_min?: number
  tmdb_popularity_min?: number
  views_min?: number
  sort?: string
  with_trashed?: boolean
}

const BOOL_FILTER_OPTIONS = [
  { value: '', label: 'Все' },
  { value: '1', label: 'Да' },
  { value: '0', label: 'Нет' },
]

const SORT_OPTIONS = [
  { value: 'default', label: 'По умолчанию (закреплённые, порядок)' },
  { value: 'created_desc', label: 'Сначала новые в базе' },
  { value: 'created_asc', label: 'Сначала старые в базе' },
  { value: 'title_asc', label: 'Название А→Я' },
  { value: 'title_desc', label: 'Название Я→А' },
  { value: 'year_desc', label: 'Год: новые → старые' },
  { value: 'year_asc', label: 'Год: старые → новые' },
  { value: 'kp_id_asc', label: 'KP ID ↑' },
  { value: 'kp_id_desc', label: 'KP ID ↓' },
  { value: 'kp_rating_desc', label: 'Рейтинг KP ↓' },
  { value: 'kp_rating_asc', label: 'Рейтинг KP ↑' },
  { value: 'imdb_rating_desc', label: 'Рейтинг IMDb ↓' },
  { value: 'tmdb_popularity_desc', label: 'Популярность TMDB ↓' },
  { value: 'tmdb_popularity_asc', label: 'Популярность TMDB ↑' },
  { value: 'views_desc', label: 'Просмотры ↓' },
  { value: 'views_asc', label: 'Просмотры ↑' },
]

const COLUMN_SORT: Record<string, { asc: string; desc: string }> = {
  kp_id: { asc: 'kp_id_asc', desc: 'kp_id_desc' },
  title: { asc: 'title_asc', desc: 'title_desc' },
  content_type: { asc: 'content_type_asc', desc: 'content_type_desc' },
  broadcast_status: { asc: 'broadcast_status_asc', desc: 'broadcast_status_desc' },
  kp_rating: { asc: 'kp_rating_asc', desc: 'kp_rating_desc' },
  tmdb_popularity: { asc: 'tmdb_popularity_asc', desc: 'tmdb_popularity_desc' },
  views_count: { asc: 'views_asc', desc: 'views_desc' },
  popular_badge_active: { asc: 'popular_badge_asc', desc: 'popular_badge_desc' },
  is_active: { asc: 'is_active_asc', desc: 'is_active_desc' },
}

function columnSortOrder(sort: string | undefined, columnKey: string): 'ascend' | 'descend' | undefined {
  const cfg = COLUMN_SORT[columnKey]
  if (!cfg || !sort) return undefined
  if (sort === cfg.asc) return 'ascend'
  if (sort === cfg.desc) return 'descend'
  return undefined
}

const cellNowrap: CSSProperties = { whiteSpace: 'nowrap' }

function appendFilterParams(params: URLSearchParams, filters: SeriesListFilters) {
  const set = (key: string, value: string | number | boolean | undefined | null) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  }

  set('q', filters.q?.trim())
  set('content_type', filters.content_type)
  set('broadcast_status', filters.broadcast_status)
  set('studio_id', filters.studio_id)
  set('genre_id', filters.genre_id)
  set('country_id', filters.country_id)
  set('actor_id', filters.actor_id)
  set('director_id', filters.director_id)
  set('is_active', filters.is_active)
  set('is_hidden', filters.is_hidden)
  set('is_pinned', filters.is_pinned)
  set('is_coming_soon', filters.is_coming_soon)
  set('noindex', filters.noindex)
  set('popular_badge_active', filters.popular_badge_active)
  set('has_poster', filters.has_poster)
  set('has_tmdb_id', filters.has_tmdb_id)
  set('year_from', filters.year_from)
  set('year_to', filters.year_to)
  set('kp_rating_min', filters.kp_rating_min)
  set('imdb_rating_min', filters.imdb_rating_min)
  set('tmdb_popularity_min', filters.tmdb_popularity_min)
  set('views_min', filters.views_min)
  set('sort', filters.sort && filters.sort !== 'default' ? filters.sort : undefined)
  if (filters.with_trashed) params.set('with_trashed', '1')
}

function mergeTaxonomyOptions(base: SelectOption[], items?: TaxonomyOption[]): SelectOption[] {
  const map = new Map<number, SelectOption>()
  for (const option of base) {
    map.set(option.value, option)
  }
  for (const item of items ?? []) {
    map.set(item.id, { value: item.id, label: item.name })
  }
  return Array.from(map.values())
}

function mergeStudioOptions(
  base: SelectOption[],
  studios?: { id: number; title: string }[] | null,
  studio?: { id: number; title: string } | null,
): SelectOption[] {
  const map = new Map<number, SelectOption>()
  for (const option of base) {
    map.set(option.value, option)
  }
  for (const item of studios ?? []) {
    map.set(item.id, { value: item.id, label: item.title })
  }
  if (studio?.id) {
    map.set(studio.id, { value: studio.id, label: studio.title })
  }
  return Array.from(map.values())
}

function mergeCollectionOptions(
  base: SelectOption[],
  collections?: { id: number; title: string }[] | null,
): SelectOption[] {
  const map = new Map<number, SelectOption>()
  for (const option of base) {
    map.set(option.value, option)
  }
  for (const item of collections ?? []) {
    map.set(item.id, { value: item.id, label: item.title })
  }
  return Array.from(map.values())
}

function seriesListRouteKey(row: SeriesItem): string {
  return String(row.kp_id || row.tmdb_id || row.id)
}

function seriesRouteKey(editing: SeriesItem | null, values: Record<string, unknown>): string | null {
  const kpId = String(values.kp_id ?? editing?.kp_id ?? '').trim()
  if (kpId) return kpId

  const tmdbId = String(values.tmdb_id ?? editing?.tmdb_id ?? '').trim()
  if (tmdbId) return tmdbId

  if (editing?.id) return String(editing.id)

  return null
}

function countCharsWithoutSpaces(text: string): number {
  return text.replace(/\s/g, '').length
}

function seriesToFormValues(item: SeriesItem): Record<string, unknown> {
  return {
    kp_id: item.kp_id,
    imdb_id: item.imdb_id,
    tmdb_id: item.tmdb_id,
    tmdb_popularity: item.tmdb_popularity,
    slug: item.slug,
    title: item.title,
    title_en: item.title_en,
    title_original: item.title_original,
    description: item.description,
    short_description: item.short_description,
    slogan: item.slogan,
    poster_url: item.poster_url,
    year: item.year,
    start_year: item.start_year,
    end_year: item.end_year,
    duration_minutes: item.duration_minutes,
    kp_rating: item.kp_rating,
    imdb_rating: item.imdb_rating,
    kp_votes_count: item.kp_votes_count,
    imdb_votes_count: item.imdb_votes_count,
    content_type: item.content_type,
    broadcast_status: item.broadcast_status,
    season_number: item.season_number,
    last_episode_number: item.last_episode_number,
    premiere_date: item.premiere_date ? dayjs(item.premiere_date) : null,
    age_limit: item.age_limit,
    kp_web_url: item.kp_web_url,
    meta_title: item.meta_title,
    meta_description: item.meta_description,
    studio_id: item.studio_id,
    studio_ids: item.studio_ids ?? (item.studio_id ? [item.studio_id] : []),
    collection_ids: item.collection_ids ?? [],
    sort_order: item.sort_order,
    is_active: item.is_active,
    is_hidden: item.is_hidden,
    noindex: item.noindex,
    is_pinned: item.is_pinned,
    is_coming_soon: item.is_coming_soon,
    genre_ids: item.genre_ids ?? [],
    country_ids: item.country_ids ?? [],
    actor_ids: item.actor_ids ?? [],
    director_ids: item.director_ids ?? [],
  }
}

export default function SeriesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [items, setItems] = useState<SeriesItem[]>([])
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [collections, setCollections] = useState<CollectionItem[]>([])
  const [taxonomy, setTaxonomy] = useState<{ genres: TaxonomyOption[]; countries: TaxonomyOption[]; people: TaxonomyOption[] }>({
    genres: [],
    countries: [],
    people: [],
  })
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(50)
  const [total, setTotal] = useState(0)
  const [filtersOpen, setFiltersOpen] = useState(false)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [drawerTab, setDrawerTab] = useState('main')
  const [mainSubTab, setMainSubTab] = useState('basic')
  const [editing, setEditing] = useState<SeriesItem | null>(null)
  const [importing, setImporting] = useState(false)
  const [importingAlloha, setImportingAlloha] = useState(false)
  const [importingTmdb, setImportingTmdb] = useState(false)
  const [playersRefreshKey, setPlayersRefreshKey] = useState(0)
  const [playersCount, setPlayersCount] = useState(0)
  const [hasSchedule, setHasSchedule] = useState(false)
  const [posterCacheBust, setPosterCacheBust] = useState<number | undefined>(undefined)
  const [saving, setSaving] = useState(false)
  const playersEditorRef = useRef<SeriesPlayersEditorHandle>(null)
  const scheduleEditorRef = useRef<SeriesScheduleEditorHandle>(null)
  const [form] = Form.useForm()
  const [filterForm] = Form.useForm<SeriesListFilters>()
  const posterUrl = Form.useWatch('poster_url', form)
  const watchedDescription = Form.useWatch('description', form)
  const descriptionCharCount = useMemo(
    () => countCharsWithoutSpaces(String(watchedDescription ?? '')),
    [watchedDescription],
  )
  const watchedKpId = Form.useWatch('kp_id', form)
  const watchedImdbId = Form.useWatch('imdb_id', form)
  const watchedTmdbId = Form.useWatch('tmdb_id', form)
  const editorRouteKey = useMemo(
    () => seriesRouteKey(editing, { kp_id: watchedKpId, tmdb_id: watchedTmdbId }),
    [editing, watchedKpId, watchedTmdbId],
  )

  const refreshPlayersCount = useCallback(async (routeKey: string | null | undefined) => {
    if (!routeKey) {
      setPlayersCount(0)
      return
    }
    try {
      const data = await api<{ players: unknown[] }>(`/api/admin/series/${routeKey}/players`)
      setPlayersCount(data.players?.length ?? 0)
    } catch {
      setPlayersCount(0)
    }
  }, [])

  const refreshSchedulePresence = useCallback(async (routeKey: string | null | undefined) => {
    if (!routeKey) {
      setHasSchedule(false)
      return
    }
    try {
      const data = await api<{ seasons: Array<{ episodes?: unknown[] }> }>(`/api/admin/series/${routeKey}/schedule`)
      setHasSchedule((data.seasons ?? []).some((season) => (season.episodes?.length ?? 0) > 0))
    } catch {
      setHasSchedule(false)
    }
  }, [])

  useEffect(() => {
    if (!drawerOpen) return
    void refreshPlayersCount(editorRouteKey)
    void refreshSchedulePresence(editorRouteKey)
  }, [drawerOpen, editorRouteKey, playersRefreshKey, refreshPlayersCount, refreshSchedulePresence])

  const hasKpIdValue = Boolean(String(watchedKpId ?? '').trim())
  const hasImdbIdValue = Boolean(String(watchedImdbId ?? '').trim())
  const hasTmdbIdValue = Boolean(String(watchedTmdbId ?? '').trim())
  const currentSort = (Form.useWatch('sort', filterForm) as string | undefined) ?? 'default'

  const loadStudios = useCallback(async () => {
    const data = await api<{ items: StudioItem[] }>('/api/admin/studios')
    setStudios(data.items)
  }, [])

  const loadCollections = useCallback(async () => {
    const data = await api<{ items: CollectionItem[] }>('/api/admin/collections')
    setCollections(data.items)
  }, [])

  const studioOptions = useMemo(
    () => mergeStudioOptions(
      studios.map((s) => ({ value: s.id, label: s.title })),
      editing?.studios,
      editing?.studio,
    ),
    [studios, editing?.studios, editing?.studio],
  )

  const collectionOptions = useMemo(
    () => mergeCollectionOptions(
      collections.map((c) => ({ value: c.id, label: c.title })),
      editing?.collections,
    ),
    [collections, editing?.collections],
  )

  const autoCollectionTitles = useMemo(
    () => (editing?.collections ?? []).filter((c) => c.is_auto).map((c) => c.title),
    [editing?.collections],
  )

  const loadTaxonomy = useCallback(async () => {
    const data = await api<{ genres: TaxonomyOption[]; countries: TaxonomyOption[]; people: TaxonomyOption[] }>(
      '/api/admin/taxonomies/options',
    )
    setTaxonomy(data)
  }, [])

  const genreOptions = useMemo(
    () => mergeTaxonomyOptions(
      taxonomy.genres.map((g) => ({ value: g.id, label: g.name })),
      editing?.genres,
    ),
    [taxonomy.genres, editing?.genres],
  )
  const countryOptions = useMemo(
    () => mergeTaxonomyOptions(
      taxonomy.countries.map((c) => ({ value: c.id, label: c.name })),
      editing?.countries,
    ),
    [taxonomy.countries, editing?.countries],
  )
  const actorOptions = useMemo(
    () => mergeTaxonomyOptions(
      taxonomy.people.map((p) => ({ value: p.id, label: p.name })),
      editing?.actors,
    ),
    [taxonomy.people, editing?.actors],
  )
  const directorOptions = useMemo(
    () => mergeTaxonomyOptions(
      taxonomy.people.map((p) => ({ value: p.id, label: p.name })),
      editing?.directors,
    ),
    [taxonomy.people, editing?.directors],
  )

  const loadSeries = useCallback(async (nextPage = page, nextPerPage = perPage, filters?: SeriesListFilters) => {
    setLoading(true)
    try {
      const values = filters ?? filterForm.getFieldsValue()
      const params = new URLSearchParams()
      params.set('page', String(nextPage))
      params.set('per_page', String(nextPerPage))
      appendFilterParams(params, values)
      const data = await api<{
        items: SeriesItem[]
        total: number
        page: number
        per_page: number
        last_page: number
      }>(`/api/admin/series?${params}`)
      setItems(data.items)
      setTotal(data.total)
      setPage(data.page)
      setPerPage(data.per_page)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [filterForm, page, perPage])

  useEffect(() => {
    filterForm.setFieldsValue({
      sort: 'default',
      with_trashed: false,
    })
    Promise.all([loadTaxonomy(), loadStudios(), loadCollections()])
      .then(() => loadSeries(1, 50, filterForm.getFieldsValue()))
      .catch((e) => message.error(String((e as Error).message)))
  }, [])

  function applyFilters() {
    loadSeries(1, perPage)
  }

  function resetFilters() {
    filterForm.resetFields()
    filterForm.setFieldsValue({ sort: 'default', with_trashed: false })
    loadSeries(1, perPage, { sort: 'default', with_trashed: false })
  }

  function openCreate() {
    setEditing(null)
    setPlayersCount(0)
    setHasSchedule(false)
    form.resetFields()
    form.setFieldsValue({
      is_active: true,
      is_hidden: false,
      noindex: false,
      is_pinned: false,
      sort_order: 0,
      content_type: 'series',
      broadcast_status: 'ongoing',
    })
    setDrawerTab('main')
    setMainSubTab('basic')
    setDrawerOpen(true)
  }

  const openEdit = useCallback((row: SeriesItem) => {
    setEditing(row)
    setDrawerTab('main')
    setMainSubTab('basic')
    form.setFieldsValue(seriesToFormValues(row))
    setDrawerOpen(true)
    void loadStudios()
  }, [form, loadStudios])

  useSeriesDeepLink({ searchParams, setSearchParams, openEdit })

  async function applyImportedItem(item: SeriesItem) {
    const currentDescription = String(form.getFieldValue('description') ?? '').trim()
    await Promise.all([loadTaxonomy(), loadStudios()])
    setEditing(item)
    const values = seriesToFormValues(item)
    if (currentDescription) {
      values.description = currentDescription
    }
    form.setFieldsValue(values)
  }

  function buildSeriesPayload(values: Record<string, unknown>): Record<string, unknown> {
    const payload = { ...values }
    const premiereDate = payload.premiere_date
    if (premiereDate && dayjs.isDayjs(premiereDate)) {
      payload.premiere_date = premiereDate.format('YYYY-MM-DD')
    } else {
      payload.premiere_date = null
    }

    const durationMinutes = payload.duration_minutes
    if (durationMinutes === null || durationMinutes === undefined || Number(durationMinutes) < 1) {
      payload.duration_minutes = null
    }

    return payload
  }

  async function saveAll() {
    setSaving(true)
    try {
      const values = await form.validateFields()
      const allValues = form.getFieldsValue(true) as Record<string, unknown>
      const payload = buildSeriesPayload({ ...allValues, ...values })

      if (editing) {
        payload.id = editing.id
        if (editing.kp_id) {
          payload.original_kp_id = editing.kp_id
        }
      }

      const res = await api<{ item: SeriesItem }>('/api/admin/series/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })

      setEditing(res.item)
      form.setFieldsValue(seriesToFormValues(res.item))

      const routeKey = seriesRouteKey(res.item, { ...payload, ...res.item })
      if (routeKey) {
        const playersSaved = await playersEditorRef.current?.save({ silent: true, kpId: routeKey })
        if (playersSaved === false) {
          message.error('Сериал сохранён, но не удалось сохранить плееры')
          await loadSeries()
          return
        }

        const scheduleSaved = await scheduleEditorRef.current?.save({ silent: true, kpId: routeKey })
        if (scheduleSaved === false) {
          message.error('Сериал сохранён, но не удалось сохранить расписание')
          await loadSeries()
          return
        }
      }

      message.success(editing ? 'Сохранено' : 'Создано')
      setDrawerOpen(false)
      await loadSeries()
    } catch (e) {
      if (e && typeof e === 'object' && 'errorFields' in e) {
        message.warning('Проверьте обязательные поля на вкладке «Основное»')
        return
      }
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  async function copyId(label: string, value: string | number) {
    try {
      await navigator.clipboard.writeText(String(value))
      message.success(`${label} скопирован`)
    } catch {
      message.error('Не удалось скопировать')
    }
  }

  async function validateIdentifierRequired() {
    const kpId = String(form.getFieldValue('kp_id') ?? '').trim()
    const tmdbId = String(form.getFieldValue('tmdb_id') ?? '').trim()
    if (!kpId && !tmdbId) {
      throw new Error('Укажите KP ID или TMDB ID')
    }
  }

  async function validateIdUnique(
    field: 'kp_id' | 'imdb_id' | 'tmdb_id',
    endpoint: string,
    label: string,
    value: unknown,
  ) {
    const id = String(value ?? '').trim()
    if (!id) return

    const params = new URLSearchParams({ [field]: id })
    if (editing?.id) {
      params.set('except_id', String(editing.id))
    }

    const res = await api<{ exists: boolean; item?: { title?: string } }>(
      `${endpoint}?${params}`,
    )
    if (res.exists) {
      throw new Error(`${label} уже занят${res.item?.title ? `: ${res.item.title}` : ''}`)
    }
  }

  async function validateKpIdUnique(_: unknown, value: unknown) {
    await validateIdUnique('kp_id', '/api/admin/series/check-kp', 'KP ID', value)
  }

  async function validateImdbIdUnique(_: unknown, value: unknown) {
    await validateIdUnique('imdb_id', '/api/admin/series/check-imdb', 'IMDb ID', value)
  }

  async function validateTmdbIdUnique(_: unknown, value: unknown) {
    await validateIdUnique('tmdb_id', '/api/admin/series/check-tmdb', 'TMDB ID', value)
  }

  async function copyDescriptionAiPrompt() {
    const values = form.getFieldsValue()
    const title = String(values.title ?? '').trim()
    const description = String(values.description ?? '').trim()

    if (!title) {
      message.warning('Заполните название')
      return
    }
    if (!description) {
      message.warning('Заполните описание')
      return
    }

    const genreIds: number[] = values.genre_ids ?? []
    const genreNames = genreIds
      .map((id) => genreOptions.find((option) => option.value === id)?.label)
      .filter((name): name is string => Boolean(name))

    const prompt = buildDescriptionAiPrompt({
      title,
      year: values.year,
      contentType: values.content_type,
      genreNames,
      description,
    })

    try {
      await navigator.clipboard.writeText(prompt)
      message.success('Промпт скопирован в буфер обмена')
    } catch {
      message.error('Не удалось скопировать')
    }
  }

  async function importKp() {
    const kpId = form.getFieldValue('kp_id')
    if (!kpId) {
      message.warning('Укажите KP ID')
      return
    }
    setImporting(true)
    try {
      const res = await api<{ item: SeriesItem }>(`/api/admin/series/${kpId}/import-kp`, {
        method: 'POST',
        body: JSON.stringify({
          download_poster: true,
        }),
      })
      await applyImportedItem(res.item)
      setPlayersRefreshKey((key) => key + 1)
      message.success('Данные загружены из KinoPoisk')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setImporting(false)
    }
  }

  async function importAlloha() {
    const values = form.getFieldsValue(true) as Record<string, unknown>
    const kpId = String(values.kp_id ?? editing?.kp_id ?? '').trim()
    const imdbId = String(values.imdb_id ?? editing?.imdb_id ?? '').trim()
    const tmdbId = String(values.tmdb_id ?? editing?.tmdb_id ?? '').trim()
    if (!kpId && !imdbId && !tmdbId) {
      message.warning('Укажите KP ID, IMDb ID или TMDB ID')
      return
    }
    setImportingAlloha(true)
    try {
      const res = await api<{ item: SeriesItem }>('/api/admin/series/import-alloha', {
        method: 'POST',
        body: JSON.stringify({
          kp_id: kpId || undefined,
          download_poster: true,
          sync_metadata: true,
          imdb_id: imdbId || undefined,
          tmdb_id: tmdbId || undefined,
        }),
      })
      await applyImportedItem(res.item)
      setPlayersRefreshKey((key) => key + 1)
      message.success('Данные загружены из Alloha')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setImportingAlloha(false)
    }
  }

  async function importTmdb() {
    const tmdbId = String(form.getFieldValue('tmdb_id') ?? '').trim()
    if (!tmdbId) {
      message.warning('Укажите TMDB ID')
      return
    }
    const kpId = String(form.getFieldValue('kp_id') ?? '').trim()
    setImportingTmdb(true)
    try {
      const res = await api<{ item: SeriesItem }>('/api/admin/series/import-tmdb', {
        method: 'POST',
        body: JSON.stringify({
          tmdb_id: tmdbId,
          kp_id: kpId || undefined,
          download_poster: true,
        }),
      })
      await applyImportedItem(res.item)
      setPlayersRefreshKey((key) => key + 1)
      message.success('Данные загружены из TMDB')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setImportingTmdb(false)
    }
  }

  async function uploadPoster(file: File) {
    const routeKey = seriesRouteKey(editing, form.getFieldsValue(true) as Record<string, unknown>)
    if (!routeKey) {
      message.warning('Укажите KP ID или TMDB ID перед загрузкой постера')
      return false
    }
    const fd = new FormData()
    fd.append('poster', file)
    try {
      const res = await apiUpload<{ poster_url: string }>(`/api/admin/series/${routeKey}/poster`, fd)
      form.setFieldValue('poster_url', res.poster_url)
      setPosterCacheBust(Date.now())
      message.success('Постер загружен')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    }
    return false
  }

  async function togglePin(row: SeriesItem) {
    try {
      await api(`/api/admin/series/${seriesListRouteKey(row)}/pin`, {
        method: 'POST',
        body: JSON.stringify({ pinned: !row.is_pinned }),
      })
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function toggleVisibility(row: SeriesItem) {
    try {
      await api(`/api/admin/series/${seriesListRouteKey(row)}/visibility`, {
        method: 'POST',
        body: JSON.stringify({ is_active: !row.is_active }),
      })
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(row: SeriesItem) {
    try {
      await api(`/api/admin/series/${seriesListRouteKey(row)}`, { method: 'DELETE' })
      message.success('Удалено')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function restore(row: SeriesItem) {
    try {
      await api(`/api/admin/series/${seriesListRouteKey(row)}/restore`, { method: 'POST' })
      message.success('Восстановлено')
      await loadSeries()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const statusTag = (s?: string | null) => {
    const label = BROADCAST_STATUSES.find((x) => x.value === s)?.label ?? s ?? '—'
    const color = s === 'ongoing' ? 'green' : s === 'paused' ? 'gold' : s === 'completed' ? 'blue' : 'default'
    return <Tag color={color}>{label}</Tag>
  }

  const columns: ColumnsType<SeriesItem> = [
    {
      title: 'KP',
      dataIndex: 'kp_id',
      key: 'kp_id',
      width: 108,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'kp_id'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => (
        <Button
          type="link"
          size="small"
          icon={<CopyOutlined />}
          onClick={() => void copyId('KP ID', v)}
          style={{ padding: 0, height: 'auto', whiteSpace: 'nowrap' }}
          title="Скопировать KP ID"
        >
          {v}
        </Button>
      ),
    },
    {
      title: 'Название',
      dataIndex: 'title',
      key: 'title',
      ellipsis: true,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'title'),
      render: (t, r) => (
        <Space size={8} align="start" style={{ maxWidth: '100%' }}>
          {r.poster_url ? (
            <img
              src={resolveMediaUrl(r.poster_url, posterCacheBust)}
              alt=""
              style={{ width: 36, height: 52, objectFit: 'cover', borderRadius: 4, flexShrink: 0 }}
            />
          ) : null}
          <Space direction="vertical" size={0} style={{ minWidth: 0 }}>
            <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', display: 'block', maxWidth: 280 }}>
              {t}{r.deleted_at ? ' (удалён)' : ''}
            </span>
            {r.is_pinned ? <Tag color="orange">Закреплён</Tag> : null}
          </Space>
        </Space>
      ),
    },
    {
      title: 'Тип',
      dataIndex: 'content_type',
      key: 'content_type',
      width: 88,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'content_type'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => CONTENT_TYPES.find((x) => x.value === v)?.label ?? '—',
    },
    {
      title: 'Статус',
      dataIndex: 'broadcast_status',
      key: 'broadcast_status',
      width: 110,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'broadcast_status'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => statusTag(v),
    },
    {
      title: 'Серии',
      key: 'episodes',
      width: 84,
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (_, r) => {
        if (!r.season_number && !r.last_episode_number) return '—'
        const parts: string[] = []
        if (r.season_number) parts.push(`S${r.season_number}`)
        if (r.last_episode_number) parts.push(`E${r.last_episode_number}`)
        return parts.join(' ')
      },
    },
    {
      title: 'KP ★',
      dataIndex: 'kp_rating',
      key: 'kp_rating',
      width: 72,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'kp_rating'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
    },
    {
      title: 'TMDB',
      dataIndex: 'tmdb_popularity',
      key: 'tmdb_popularity',
      width: 80,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'tmdb_popularity'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => (v != null ? Number(v).toFixed(1) : '—'),
    },
    {
      title: 'Просмотры',
      dataIndex: 'views_count',
      key: 'views_count',
      width: 96,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'views_count'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => v ?? 0,
    },
    {
      title: '3 дня',
      dataIndex: 'views_3d',
      key: 'views_3d',
      width: 72,
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => v ?? 0,
    },
    {
      title: 'Популярно',
      dataIndex: 'popular_badge_active',
      key: 'popular_badge_active',
      width: 100,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'popular_badge_active'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => (v ? <Tag color="gold">Да</Tag> : '—'),
    },
    {
      title: 'Сайт',
      dataIndex: 'is_active',
      key: 'is_active',
      width: 84,
      sorter: true,
      sortOrder: columnSortOrder(currentSort, 'is_active'),
      onHeaderCell: () => ({ style: cellNowrap }),
      onCell: () => ({ style: cellNowrap }),
      render: (v) => (v ? <Tag color="green">Виден</Tag> : <Tag>Скрыт</Tag>),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 168,
      fixed: 'right',
      onCell: () => ({ style: cellNowrap }),
      render: (_, row) => (
        <Space size={2} wrap={false}>
          <Tooltip title="Изменить">
            <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(row)} aria-label="Изменить" />
          </Tooltip>
          <Tooltip title={row.is_pinned ? 'Открепить' : 'Закрепить'}>
            <Button
              size="small"
              icon={row.is_pinned ? <PushpinFilled /> : <PushpinOutlined />}
              onClick={() => togglePin(row)}
              aria-label={row.is_pinned ? 'Открепить' : 'Закрепить'}
            />
          </Tooltip>
          <Tooltip title={row.is_active ? 'Скрыть' : 'Показать'}>
            <Button
              size="small"
              icon={row.is_active ? <EyeInvisibleOutlined /> : <EyeOutlined />}
              onClick={() => toggleVisibility(row)}
              aria-label={row.is_active ? 'Скрыть' : 'Показать'}
            />
          </Tooltip>
          <Tooltip title="Открыть на сайте">
            <Button
              size="small"
              icon={<ExportOutlined />}
              href={`${siteOrigin()}${seriesPublicPath(row)}`}
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Открыть на сайте"
            />
          </Tooltip>
          {row.deleted_at ? (
            <Tooltip title="Восстановить">
              <Button size="small" icon={<UndoOutlined />} onClick={() => restore(row)} aria-label="Восстановить" />
            </Tooltip>
          ) : (
            <Popconfirm title="Удалить сериал?" onConfirm={() => remove(row)}>
              <Tooltip title="Удалить">
                <Button size="small" danger icon={<DeleteOutlined />} aria-label="Удалить" />
              </Tooltip>
            </Popconfirm>
          )}
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Space wrap>
          <span className="admin-empty-hint">Всего: {total}</span>
        </Space>
        <Button type="primary" onClick={openCreate}>Добавить</Button>
      </div>

      <Card
        size="small"
        title="Фильтры"
        style={{ marginBottom: 16 }}
        extra={
          <Button type="link" onClick={() => setFiltersOpen((v) => !v)}>
            {filtersOpen ? 'Свернуть' : 'Развернуть'}
          </Button>
        }
      >
        {filtersOpen ? (
          <Form form={filterForm} layout="vertical" onFinish={applyFilters}>
            <Row gutter={16}>
              <Col xs={24} md={12} lg={8}>
                <Form.Item label="Поиск" name="q" extra="Название, KP/IMDb/TMDB ID, slug">
                  <Input allowClear placeholder="Поиск по всем сериалам..." onPressEnter={() => filterForm.submit()} />
                </Form.Item>
              </Col>
              <Col xs={24} md={12} lg={8}>
                <Form.Item label="Сортировка" name="sort">
                  <Select options={SORT_OPTIONS} />
                </Form.Item>
              </Col>
              <Col xs={24} md={12} lg={8}>
                <Form.Item label="Удалённые" name="with_trashed" valuePropName="checked">
                  <Switch checkedChildren="Показать" unCheckedChildren="Скрыть" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Тип" name="content_type">
                  <Select allowClear placeholder="Все" options={CONTENT_TYPES.map((x) => ({ value: x.value, label: x.label }))} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Статус сериала" name="broadcast_status">
                  <Select allowClear placeholder="Все" options={BROADCAST_STATUSES.map((x) => ({ value: x.value, label: x.label }))} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Студия" name="studio_id">
                  <Select allowClear placeholder="Все" options={studioOptions} showSearch optionFilterProp="label" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Жанр" name="genre_id">
                  <Select allowClear placeholder="Все" options={genreOptions} showSearch optionFilterProp="label" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Страна" name="country_id">
                  <Select allowClear placeholder="Все" options={countryOptions} showSearch optionFilterProp="label" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Актёр" name="actor_id">
                  <Select allowClear placeholder="Все" options={actorOptions} showSearch optionFilterProp="label" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Режиссёр" name="director_id">
                  <Select allowClear placeholder="Все" options={directorOptions} showSearch optionFilterProp="label" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Год от" name="year_from">
                  <InputNumber style={{ width: '100%' }} min={1900} max={2100} placeholder="Любой" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Год до" name="year_to">
                  <InputNumber style={{ width: '100%' }} min={1900} max={2100} placeholder="Любой" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="KP ≥" name="kp_rating_min">
                  <InputNumber style={{ width: '100%' }} min={0} max={10} step={0.1} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="IMDb ≥" name="imdb_rating_min">
                  <InputNumber style={{ width: '100%' }} min={0} max={10} step={0.1} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="TMDB поп. ≥" name="tmdb_popularity_min">
                  <InputNumber style={{ width: '100%' }} min={0} step={0.1} />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Просмотры ≥" name="views_min">
                  <InputNumber style={{ width: '100%' }} min={0} />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="На сайте" name="is_active">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Скрыта страница" name="is_hidden">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Закреплён" name="is_pinned">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Раздел «Скоро»" name="is_coming_soon">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Noindex" name="noindex">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Бейдж «Популярно»" name="popular_badge_active">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Есть постер" name="has_poster">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
            </Row>
            <Row gutter={16}>
              <Col xs={24} md={8} lg={6}>
                <Form.Item label="Есть TMDB ID" name="has_tmdb_id">
                  <Select allowClear options={BOOL_FILTER_OPTIONS} placeholder="Все" />
                </Form.Item>
              </Col>
            </Row>
            <Space>
              <Button type="primary" htmlType="submit">Применить</Button>
              <Button onClick={resetFilters}>Сбросить</Button>
            </Space>
          </Form>
        ) : null}
      </Card>

      <Table
        rowKey="id"
        loading={loading}
        columns={columns}
        dataSource={items}
        size="middle"
        pagination={{
          current: page,
          pageSize: perPage,
          total,
          showSizeChanger: true,
          pageSizeOptions: ['20', '50', '100'],
          showTotal: (value) => `Показано ${items.length} из ${value}`,
        }}
        scroll={{ x: 1280 }}
        onChange={(pagination, _filters, sorter) => {
          const nextPage = pagination.current ?? 1
          const nextPerPage = pagination.pageSize ?? perPage
          const single = Array.isArray(sorter) ? sorter[0] : sorter
          const columnKey = String(single?.columnKey ?? single?.field ?? '')
          const cfg = COLUMN_SORT[columnKey]
          let nextSort = currentSort

          if (cfg && single?.order === 'ascend') {
            nextSort = cfg.asc
          } else if (cfg && single?.order === 'descend') {
            nextSort = cfg.desc
          } else if (cfg && !single?.order) {
            nextSort = 'default'
          }

          if (nextSort !== currentSort) {
            filterForm.setFieldsValue({ sort: nextSort })
          }

          const values = { ...filterForm.getFieldsValue(), sort: nextSort } as SeriesListFilters
          void loadSeries(nextPage, nextPerPage, values)
        }}
      />

      <Drawer
        title={editing ? `Редактирование: ${editing.title}` : 'Новый сериал / фильм'}
        open={drawerOpen}
        onClose={() => {
          setDrawerOpen(false)
          setEditing(null)
          setPlayersCount(0)
          setHasSchedule(false)
          form.resetFields()
        }}
        width={820}
        extra={
          <Space>
            <Button onClick={importKp} loading={importing}>Импорт KP</Button>
            <Button onClick={importAlloha} loading={importingAlloha}>Импорт Alloha</Button>
            <Button onClick={importTmdb} loading={importingTmdb}>Импорт TMDB</Button>
            <Button type="primary" loading={saving} onClick={saveAll}>Сохранить</Button>
          </Space>
        }
      >
        <Form form={form} layout="vertical" onFinish={() => void saveAll()} preserve>
        <Tabs
          activeKey={drawerTab}
          onChange={setDrawerTab}
          destroyInactiveTabPane={false}
          items={[
            {
              key: 'main',
              label: 'Основное',
              children: (
          <Tabs
            activeKey={mainSubTab}
            onChange={setMainSubTab}
            destroyInactiveTabPane={false}
            items={[
              {
                key: 'basic',
                label: 'Идентификация',
                children: (
                  <>
                    <SeriesLookupSearch form={form} />
                    <Row gutter={16}>
                      <Col span={8}>
                        <Form.Item
                          label="KP ID"
                          name="kp_id"
                          validateDebounce={400}
                          hasFeedback={hasKpIdValue}
                          dependencies={['tmdb_id']}
                          rules={[
                            { validator: validateIdentifierRequired },
                            { validator: validateKpIdUnique },
                          ]}
                          extra={editing ? 'Можно заменить, если добавили не тот сериал' : undefined}
                        >
                          <Input
                            placeholder="915196"
                            suffix={
                              <CopyOutlined
                                onClick={() => {
                                  const value = form.getFieldValue('kp_id')
                                  if (value) void copyId('KP ID', value)
                                }}
                                style={{ cursor: 'pointer', color: 'rgba(0,0,0,0.45)' }}
                                title="Скопировать"
                              />
                            }
                          />
                        </Form.Item>
                      </Col>
                      <Col span={8}>
                        <Form.Item
                          label="IMDb ID"
                          name="imdb_id"
                          validateDebounce={400}
                          hasFeedback={hasImdbIdValue}
                          rules={[{ validator: validateImdbIdUnique }]}
                        >
                          <Input
                            placeholder="tt0056592"
                            suffix={
                              <CopyOutlined
                                onClick={() => {
                                  const value = form.getFieldValue('imdb_id')
                                  if (value) void copyId('IMDb ID', value)
                                }}
                                style={{ cursor: 'pointer', color: 'rgba(0,0,0,0.45)' }}
                                title="Скопировать"
                              />
                            }
                          />
                        </Form.Item>
                      </Col>
                      <Col span={8}>
                        <Form.Item
                          label="TMDB ID"
                          name="tmdb_id"
                          validateDebounce={400}
                          hasFeedback={hasTmdbIdValue}
                          dependencies={['kp_id']}
                          rules={[
                            { validator: validateIdentifierRequired },
                            { validator: validateTmdbIdUnique },
                          ]}
                        >
                          <Input
                            placeholder="66732"
                            suffix={
                              <CopyOutlined
                                onClick={() => {
                                  const value = form.getFieldValue('tmdb_id')
                                  if (value) void copyId('TMDB ID', value)
                                }}
                                style={{ cursor: 'pointer', color: 'rgba(0,0,0,0.45)' }}
                                title="Скопировать"
                              />
                            }
                          />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Form.Item label="Популярность TMDB" name="tmdb_popularity" extra="Обновляется автоматически из TMDB API вместе со статусом эфира">
                      <Input disabled placeholder="—" />
                    </Form.Item>
                    <Form.Item
                      label="Студии"
                      name="studio_ids"
                      extra="Можно выбрать несколько — сериал появится в каталоге каждой студии"
                    >
                      <Select
                        mode="multiple"
                        allowClear
                        showSearch
                        options={studioOptions}
                        optionFilterProp="label"
                        placeholder="Не выбраны"
                      />
                    </Form.Item>
                    <Form.Item
                      label="Подборки"
                      name="collection_ids"
                      extra={
                        autoCollectionTitles.length
                          ? `Также в подборках автоматически: ${autoCollectionTitles.join(', ')}`
                          : 'Можно выбрать несколько — сериал появится в каждой подборке'
                      }
                    >
                      <Select
                        mode="multiple"
                        allowClear
                        showSearch
                        options={collectionOptions}
                        optionFilterProp="label"
                        placeholder="Не выбраны"
                      />
                    </Form.Item>
                    <Form.Item label="Название (RU)" name="title" rules={[{ required: true }]}><Input /></Form.Item>
                    <Row gutter={16}>
                      <Col span={12}><Form.Item label="Название (EN)" name="title_en"><Input /></Form.Item></Col>
                      <Col span={12}><Form.Item label="Оригинальное название" name="title_original"><Input /></Form.Item></Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={8}>
                        <Form.Item label="Slug" name="slug" extra="Если пусто — из названия">
                          <Input placeholder="avto-iz-nazvaniya" />
                        </Form.Item>
                      </Col>
                      <Col span={8}>
                        <Form.Item label="Тип" name="content_type">
                          <Select options={CONTENT_TYPES.map((x) => ({ value: x.value, label: x.label }))} />
                        </Form.Item>
                      </Col>
                      <Col span={8}>
                        <Form.Item label="Статус сериала" name="broadcast_status">
                          <Select allowClear options={BROADCAST_STATUSES.map((x) => ({ value: x.value, label: x.label }))} />
                        </Form.Item>
                      </Col>
                    </Row>
                  </>
                ),
              },
              {
                key: 'episodes',
                label: 'Сезоны и рейтинги',
                children: (
                  <>
                    <Row gutter={16}>
                      <Col span={6}><Form.Item label="Год" name="year"><InputNumber style={{ width: '100%' }} min={1900} max={2100} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="Старт" name="start_year"><InputNumber style={{ width: '100%' }} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="Финал" name="end_year"><InputNumber style={{ width: '100%' }} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="Минут" name="duration_minutes"><InputNumber style={{ width: '100%' }} min={1} /></Form.Item></Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={8}>
                        <Form.Item label="Дата премьеры" name="premiere_date">
                          <DatePicker style={{ width: '100%' }} format="DD.MM.YYYY" placeholder="15.07.2016" />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={12}>
                        <Form.Item label="Номер сезона" name="season_number" extra="Текущий сезон для вывода в шаблоне">
                          <InputNumber style={{ width: '100%' }} min={1} max={999} placeholder="5" />
                        </Form.Item>
                      </Col>
                      <Col span={12}>
                        <Form.Item label="Номер последней серии" name="last_episode_number" extra="Последняя вышедшая серия">
                          <InputNumber style={{ width: '100%' }} min={1} max={9999} placeholder="12" />
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={6}><Form.Item label="KP" name="kp_rating"><InputNumber step={0.1} min={0} max={10} style={{ width: '100%' }} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="IMDb" name="imdb_rating"><InputNumber step={0.1} min={0} max={10} style={{ width: '100%' }} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="Голосов KP" name="kp_votes_count"><InputNumber style={{ width: '100%' }} min={0} /></Form.Item></Col>
                      <Col span={6}><Form.Item label="Голосов IMDb" name="imdb_votes_count"><InputNumber style={{ width: '100%' }} min={0} /></Form.Item></Col>
                    </Row>
                    <Form.Item label="Возраст" name="age_limit"><Input placeholder="16" /></Form.Item>
                  </>
                ),
              },
              {
                key: 'taxonomy',
                label: 'Жанры и участники',
                children: (
                  <>
                    <Form.Item label="Жанры" name="genre_ids" extra="Справочник: раздел «Справочники»">
                      <Select mode="multiple" allowClear options={genreOptions} optionFilterProp="label" placeholder="Выберите жанры" />
                    </Form.Item>
                    <Form.Item label="Страны" name="country_ids">
                      <Select mode="multiple" allowClear options={countryOptions} optionFilterProp="label" placeholder="Выберите страны" />
                    </Form.Item>
                    <Form.Item label="Актёры" name="actor_ids">
                      <Select mode="multiple" allowClear options={actorOptions} optionFilterProp="label" placeholder="Выберите актёров" />
                    </Form.Item>
                    <Form.Item label="Режиссёры" name="director_ids">
                      <Select mode="multiple" allowClear options={directorOptions} optionFilterProp="label" placeholder="Выберите режиссёров" />
                    </Form.Item>
                  </>
                ),
              },
              {
                key: 'content',
                label: 'Тексты и SEO',
                children: (
                  <>
                    <Form.Item label="Слоган" name="slogan"><Input /></Form.Item>
                    <Form.Item label="Краткое описание" name="short_description"><Input.TextArea rows={2} /></Form.Item>
                    <Form.Item
                      label="Описание"
                      name="description"
                      extra={
                        <Space direction="vertical" size={0}>
                          <span className="admin-empty-hint">{descriptionCharCount} символов без пробелов</span>
                          <Button type="link" size="small" icon={<CopyOutlined />} onClick={copyDescriptionAiPrompt} style={{ padding: 0 }}>
                            Скопировать промпт для ИИ
                          </Button>
                        </Space>
                      }
                    >
                      <Input.TextArea autoSize={{ minRows: 2 }} />
                    </Form.Item>
                    <Form.Item label="Meta title" name="meta_title" extra="Если пусто — название + суффикс из настроек">
                      <Input />
                    </Form.Item>
                    <Form.Item label="Meta description" name="meta_description" extra="Если пусто — краткое описание или обрезка полного">
                      <Input.TextArea rows={2} />
                    </Form.Item>
                    <Form.Item label="Ссылка KinoPoisk" name="kp_web_url"><Input /></Form.Item>
                  </>
                ),
              },
              {
                key: 'media',
                label: 'Постер',
                children: (
                  <>
                    <Form.Item label="URL постера" name="poster_url"><Input placeholder="/storage/posters/... или https://..." /></Form.Item>
                    {posterUrl ? (
                      <img
                        key={posterCacheBust ?? posterUrl}
                        src={resolveMediaUrl(posterUrl, posterCacheBust)}
                        alt="Превью постера"
                        style={{ width: 120, height: 172, objectFit: 'cover', borderRadius: 6, marginBottom: 16 }}
                      />
                    ) : null}
                    <Upload beforeUpload={uploadPoster} showUploadList={false} accept="image/*">
                      <Button>Загрузить постер на сервер</Button>
                    </Upload>
                  </>
                ),
              },
              {
                key: 'visibility',
                label: 'Видимость',
                children: (
                  <>
                    <Row gutter={16}>
                      <Col span={8}><Form.Item label="Порядок" name="sort_order"><InputNumber style={{ width: '100%' }} /></Form.Item></Col>
                      <Col span={8}><Form.Item label="Закрепить вверху" name="is_pinned" valuePropName="checked"><Switch /></Form.Item></Col>
                      <Col span={8}><Form.Item label="Показывать на сайте" name="is_active" valuePropName="checked"><Switch /></Form.Item></Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={12}>
                        <Form.Item label="Скрыть страницу" name="is_hidden" valuePropName="checked" extra="Страница недоступна посетителям (404)">
                          <Switch />
                        </Form.Item>
                      </Col>
                      <Col span={12}>
                        <Form.Item label="Раздел «Скоро»" name="is_coming_soon" valuePropName="checked" extra="Показывать в рейтинге ожидания и на странице премьер">
                          <Switch />
                        </Form.Item>
                      </Col>
                      <Col span={12}>
                        <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="meta robots noindex">
                          <Switch />
                        </Form.Item>
                      </Col>
                    </Row>
                  </>
                ),
              },
            ]}
          />
              ),
            },
            {
              key: 'players',
              label: `Плееры (${playersCount})`,
              forceRender: true,
              children: (
                <SeriesPlayersEditor
                  ref={playersEditorRef}
                  kpId={editorRouteKey}
                  drawerOpen={drawerOpen}
                  refreshKey={playersRefreshKey}
                  onCountChange={setPlayersCount}
                />
              ),
            },
            {
              key: 'schedule',
              label: (
                <span className="series-drawer-tab-label">
                  Расписание
                  {hasSchedule ? <CheckOutlined className="series-drawer-tab-label__check" aria-label="Расписание заполнено" /> : null}
                </span>
              ),
              forceRender: true,
              children: (
                <SeriesScheduleEditor
                  ref={scheduleEditorRef}
                  kpId={editorRouteKey}
                  tmdbId={watchedTmdbId ?? editing?.tmdb_id}
                  drawerOpen={drawerOpen}
                  refreshKey={playersRefreshKey}
                  onHasScheduleChange={setHasSchedule}
                  onBroadcastStatusChange={(status) => {
                    form.setFieldsValue({ broadcast_status: status })
                    if (editing) {
                      setEditing({ ...editing, broadcast_status: status ?? undefined })
                    }
                  }}
                />
              ),
            },
          ]}
        />
        </Form>
      </Drawer>
    </div>
  )
}
