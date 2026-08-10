type SeriesPathInput = {
  id?: number | string | null
  slug?: string | null
  year?: number | string | null
  start_year?: number | string | null
  premiere_date?: string | null
}

/** Public series URL path — mirrors App\Support\SeriesUrl::path */
export function seriesPublicPath(item: SeriesPathInput): string {
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
  const id = item.id ?? ''
  return `/${id}-${slug}-${year}.html`
}
