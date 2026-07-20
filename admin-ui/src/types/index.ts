export type TaxonomyItem = {
  id: number
  slug: string
  name: string
  meta_title?: string | null
  meta_description?: string | null
  seo_html?: string | null
  sort_order: number
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
  show_on_home?: boolean
  home_title?: string | null
  home_item_limit?: number
  home_show_tabs?: boolean
  home_default_sort?: 'latest' | 'popular' | 'rating'
  photo_url?: string | null
  series_count?: number
}

export type TaxonomyOption = {
  id: number
  slug: string
  name: string
}

export type TaxonomyType = 'genres' | 'countries' | 'people' | 'years'

export type SeriesLookupResult = {
  source: 'kinopoisk' | 'tmdb'
  id: string
  media_type: string
  title: string
  title_original?: string | null
  year?: number | null
  genres: string[]
  poster_url?: string | null
  rating?: number | null
}

export type SeriesItem = {
  id: number
  kp_id?: string | null
  imdb_id?: string | null
  tmdb_id?: string | null
  tmdb_popularity?: number | null
  slug: string
  title: string
  meta_title?: string | null
  meta_description?: string | null
  title_en?: string | null
  title_original?: string | null
  description?: string | null
  short_description?: string | null
  slogan?: string | null
  poster_url?: string | null
  player_url?: string | null
  year?: number | null
  start_year?: number | null
  end_year?: number | null
  duration_minutes?: number | null
  kp_rating?: number | null
  imdb_rating?: number | null
  kp_votes_count?: number | null
  imdb_votes_count?: number | null
  views_count?: number | null
  views_3d?: number | null
  views_7d?: number | null
  popular_badge_active?: boolean
  content_type?: 'film' | 'series' | null
  broadcast_status?: 'ongoing' | 'paused' | 'completed' | null
  season_number?: number | null
  last_episode_number?: number | null
  premiere_date?: string | null
  genre_ids?: number[]
  country_ids?: number[]
  actor_ids?: number[]
  director_ids?: number[]
  genres?: TaxonomyOption[]
  countries?: TaxonomyOption[]
  actors?: TaxonomyOption[]
  directors?: TaxonomyOption[]
  age_limit?: string | null
  kp_web_url?: string | null
  alloha_token?: string | null
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
  is_pinned: boolean
  is_coming_soon?: boolean
  sort_order: number
  deleted_at?: string | null
  studio_id?: number | null
  studio_ids?: number[]
  studio?: { id: number; slug: string; title: string } | null
  studios?: { id: number; slug: string; title: string; logo_url?: string | null }[]
  collection_ids?: number[]
  collections?: { id: number; slug: string; title: string; is_auto?: boolean }[]
}

export type CollectionItem = {
  id: number
  slug: string
  title: string
  description?: string | null
  meta_title?: string | null
  meta_description?: string | null
  seo_html?: string | null
  cover_url?: string | null
  home_banner_url?: string | null
  sort_order: number
  is_pinned: boolean
  show_on_home?: boolean
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
  studio_id?: number | null
  auto_add_enabled?: boolean
  auto_keywords?: string[] | null
}

export type StudioItem = {
  id: number
  slug: string
  title: string
  description?: string | null
  meta_title?: string | null
  meta_description?: string | null
  seo_html?: string | null
  logo_url?: string | null
  tmdb_id?: number | null
  tmdb_type?: string | null
  sort_order: number
  is_pinned: boolean
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
}

export type StudioSeriesItem = {
  id: number
  studio_id: number
  series_id: number
  rank_order: number
  series?: SeriesItem | null
}

export type CollectionSeriesItem = {
  id: number
  collection_id: number
  series_id: number
  rank_order: number
  is_auto?: boolean
  series?: SeriesItem | null
}

export type CommentItem = {
  id: number
  body: string
  status: string
  created_at: string
  parent_id?: number | null
  is_pinned?: boolean
  author_name?: string
  user?: { id: number; name: string; email: string }
  series?: { id: number; title: string; slug: string }
}

export type SettingItem = {
  id?: number
  key: string
  value?: string | null
}

export type ThemeItem = {
  name: string
  label: string
}

export type UserItem = {
  id: number
  name: string
  email: string
  role: 'user' | 'admin'
  is_blocked: boolean
  last_login_at: string | null
  last_ip: string | null
  registration_ip: string | null
  created_at: string
  updated_at?: string
}

export type AdminStats = {
  series_total: number
  series_active: number
  collections: number
  collections_active: number
  studios: number
  studios_active: number
  comments_total: number
  comments_pending: number
  player_reports_total: number
  player_reports_today: number
  users_total: number
  users_blocked: number
  series_with_player: number
  active_theme: string
}

export type PlayerReportItem = {
  id: number
  series_id: number
  reason: string
  reason_label?: string | null
  message?: string | null
  player_label?: string | null
  ip?: string | null
  created_at?: string
  series?: {
    id: number
    kp_id?: string | null
    title: string
    slug?: string | null
    year?: number | null
    start_year?: number | null
  } | null
  user?: { id: number; name?: string | null; email?: string | null } | null
}

export type CronRunItem = {
  id: number
  job_key: string
  job_label?: string
  command: string
  trigger: string
  status: string
  started_at?: string | null
  finished_at?: string | null
  duration_ms?: number | null
  counts?: Record<string, number | string> | null
  message?: string | null
  error?: string | null
  log?: string | null
  has_log?: boolean
  meta?: Record<string, unknown> | null
  created_at?: string | null
}

export type CronRunJobOption = {
  value: string
  label: string
}

export type SearchStatItem = {
  id: number
  query: string
  hits: number
  suggest_hits: number
  full_hits: number
  last_searched_at: string
  created_at: string
}

export type SearchLogItem = {
  id: number
  query: string
  source: 'suggest' | 'full'
  found: boolean
  results_count: number
  ip: string | null
  created_at: string
}

export type SearchTopQuery = {
  query: string
  count: number
  found_count: number
  not_found_count: number
  share: number
}

export type SearchStatsSummary = {
  unique_queries: number
  total_hits: number
  suggest_hits: number
  full_hits: number
  hits_today: number
  hits_week: number
  total_events: number
  found_events: number
  not_found_events: number
  log_unique_queries: number
  suggest_events: number
  full_events: number
  events_today: number
  events_week: number
}

export type SearchStatsResponse = {
  ready: boolean
  logs_ready: boolean
  view: 'log' | 'aggregated'
  summary: SearchStatsSummary
  top_queries: SearchTopQuery[]
  items: SearchLogItem[]
  aggregated: SearchStatItem[]
}

export type AdminPageKey =
  | 'dashboard'
  | 'nav-menu'
  | 'home-sections'
  | 'reactions'
  | 'taxonomy'
  | 'series'
  | 'collections'
  | 'studios'
  | 'comments'
  | 'player-reports'
  | 'cron-runs'
  | 'users'
  | 'search-stats'
  | 'settings'
  | 'templates'
  | 'sync'
  | 'alloha-sync'

export const BROADCAST_STATUSES = [
  { value: 'ongoing', label: 'Идёт' },
  { value: 'paused', label: 'На паузе' },
  { value: 'completed', label: 'Завершён' },
] as const

export const CONTENT_TYPES = [
  { value: 'series', label: 'Сериал' },
  { value: 'film', label: 'Фильм' },
] as const
