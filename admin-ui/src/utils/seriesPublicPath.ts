type SeriesPathInput = {
  id?: number | string | null
  slug?: string | null
  year?: number | string | null
  start_year?: number | string | null
}

/** Public series URL path — mirrors App\Support\SeriesUrl::path */
export function seriesPublicPath(item: SeriesPathInput): string {
  const yearNum = Number(item.year || item.start_year || 0)
  const year = yearNum >= 1900 && yearNum <= 2100 ? String(yearNum) : '0000'
  const slug = (item.slug || '').trim() || 'series'
  const id = item.id ?? ''
  return `/${id}-${slug}-${year}.html`
}
