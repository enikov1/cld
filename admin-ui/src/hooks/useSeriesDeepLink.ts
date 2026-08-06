import { useEffect, useRef } from 'react'
import { message } from 'antd'
import type { SetURLSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import type { SeriesItem } from '../types'

type UseSeriesDeepLinkOptions = {
  searchParams: URLSearchParams
  setSearchParams: SetURLSearchParams
  openEdit: (item: SeriesItem) => void
}

/**
 * Opens the series editor from `?id=` / `?series_id=` / `?kp_id=` query params.
 */
export function useSeriesDeepLink({ searchParams, setSearchParams, openEdit }: UseSeriesDeepLinkOptions) {
  const deepLinkHandled = useRef(false)
  const openEditRef = useRef(openEdit)
  openEditRef.current = openEdit

  useEffect(() => {
    if (deepLinkHandled.current) {
      return
    }

    const seriesId = searchParams.get('id')?.trim() || searchParams.get('series_id')?.trim()
    const kpId = searchParams.get('kp_id')?.trim()
    if (!seriesId && !kpId) {
      return
    }

    deepLinkHandled.current = true

    ;(async () => {
      try {
        const params = new URLSearchParams()
        if (seriesId) {
          params.set('id', seriesId)
        } else if (kpId) {
          params.set('kp_id', kpId)
        }
        params.set('with_trashed', '1')
        params.set('per_page', '1')
        const data = await api<{ items: SeriesItem[] }>(`/api/admin/series?${params}`)
        const item = seriesId
          ? data.items.find((row) => String(row.id) === seriesId)
          : data.items.find((row) => row.kp_id === kpId)
        if (!item) {
          message.warning('Сериал не найден')
          return
        }

        openEditRef.current(item)

        const next = new URLSearchParams(searchParams)
        next.delete('id')
        next.delete('series_id')
        next.delete('kp_id')
        setSearchParams(next, { replace: true })
      } catch (e) {
        message.error(String((e as Error).message))
      }
    })()
  }, [searchParams, setSearchParams])
}
