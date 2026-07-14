export type CategoryItem = {
  id: number
  slug: string
  title: string
  description?: string | null
  meta_title?: string | null
  seo_html?: string | null
  sort_order: number
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
}

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

export type SeriesItem = {
  id: number
  kp_id: string
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
  category?: { id: number; slug: string; title: string } | null
  studio_id?: number | null
  studio_ids?: number[]
  studio?: { id: number; slug: string; title: string } | null
  studios?: { id: number; slug: string; title: string; logo_url?: string | null }[]
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
  sort_order: number
  is_pinned: boolean
  is_active: boolean
  is_hidden: boolean
  noindex: boolean
  studio_id?: number | null
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
  created_at: string
}

export type AdminStats = {
  categories: number
  categories_active: number
  series_total: number
  series_active: number
  collections: number
  collections_active: number
  studios: number
  studios_active: number
  comments_total: number
  comments_pending: number
  users_total: number
  users_blocked: number
  series_with_player: number
  active_theme: string
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

export type SearchStatsSummary = {
  unique_queries: number
  total_hits: number
  suggest_hits: number
  full_hits: number
  hits_today: number
  hits_week: number
}

export type SearchStatsResponse = {
  ready: boolean
  summary: SearchStatsSummary
  items: SearchStatItem[]
}

export type AdminPageKey =
  | 'dashboard'
  | 'nav-menu'
  | 'reactions'
  | 'taxonomy'
  | 'series'
  | 'collections'
  | 'studios'
  | 'comments'
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
