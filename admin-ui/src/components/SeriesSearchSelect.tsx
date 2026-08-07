import { Select, Spin } from 'antd'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { api } from '../api/client'

export type SeriesSearchOption = {
  id: number
  title: string
  kp_id?: string | null
  path: string
}

type SeriesSearchSelectProps = {
  value?: number | null
  onChange?: (value: number | null, option?: SeriesSearchOption | null) => void
  disabled?: boolean
  placeholder?: string
  /** Pre-selected series shown before search */
  initialOption?: SeriesSearchOption | null
}

export default function SeriesSearchSelect({
  value,
  onChange,
  disabled,
  placeholder = 'Начните вводить название сериала…',
  initialOption = null,
}: SeriesSearchSelectProps) {
  const [options, setOptions] = useState<SeriesSearchOption[]>(initialOption ? [initialOption] : [])
  const [loading, setLoading] = useState(false)
  const [search, setSearch] = useState('')
  const timerRef = useRef<number | null>(null)

  useEffect(() => {
    if (initialOption && !options.some((o) => o.id === initialOption.id)) {
      setOptions((prev) => [initialOption, ...prev])
    }
  }, [initialOption, options])

  const selectOptions = useMemo(
    () => options.map((item) => ({
      value: item.id,
      label: `${item.title}${item.kp_id ? ` (KP ${item.kp_id})` : ''}`,
      item,
    })),
    [options],
  )

  const fetchOptions = useCallback(async (query: string) => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      if (query.trim()) params.set('q', query.trim())
      params.set('limit', '20')
      const data = await api<{ items: SeriesSearchOption[] }>(
        `/api/admin/redirects/series-options?${params}`,
      )
      setOptions((prev) => {
        const map = new Map<number, SeriesSearchOption>()
        for (const item of prev) map.set(item.id, item)
        for (const item of data.items) map.set(item.id, item)
        return Array.from(map.values())
      })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchOptions('').catch(() => {
      /* ignore */
    })
  }, [fetchOptions])

  const handleSearch = (next: string) => {
    setSearch(next)
    if (timerRef.current) {
      window.clearTimeout(timerRef.current)
    }
    timerRef.current = window.setTimeout(() => {
      fetchOptions(next).catch(() => {
        /* ignore */
      })
    }, 350)
  }

  return (
    <Select
      showSearch
      allowClear
      disabled={disabled}
      placeholder={placeholder}
      value={value ?? undefined}
      filterOption={false}
      onSearch={handleSearch}
      notFoundContent={loading ? <Spin size="small" /> : search.trim() ? 'Ничего не найдено' : 'Введите запрос'}
      options={selectOptions}
      optionRender={(option) => {
        const item = (option.data as { item?: SeriesSearchOption }).item
        if (!item) return option.label
        return (
          <div>
            <div>{item.title}</div>
            <div style={{ fontSize: 12, opacity: 0.65 }}>{item.path}</div>
          </div>
        )
      }}
      onChange={(next, option) => {
        const selected = Array.isArray(option)
          ? null
          : ((option as { item?: SeriesSearchOption } | undefined)?.item ?? null)
        onChange?.(typeof next === 'number' ? next : null, selected)
      }}
    />
  )
}
