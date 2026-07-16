import { Alert, Avatar, Form, Input, List, Spin, Tag, Typography, message } from 'antd'
import type { FormInstance } from 'antd'
import { useEffect, useRef, useState } from 'react'
import { api } from '../api/client'
import type { SeriesLookupResult } from '../types'
import { resolveMediaUrl } from '../utils/mediaUrl'

type Props = {
  form: FormInstance
  onSelect?: (item: SeriesLookupResult) => void
}

function sourceLabel(source: SeriesLookupResult['source']): string {
  return source === 'kinopoisk' ? 'KP' : 'TMDB'
}

function sourceColor(source: SeriesLookupResult['source']): string {
  return source === 'kinopoisk' ? 'orange' : 'blue'
}

function mediaTypeLabel(mediaType: string): string {
  if (mediaType === 'tv' || mediaType === 'series') return 'Сериал'
  if (mediaType === 'movie' || mediaType === 'film') return 'Фильм'
  return mediaType
}

export default function SeriesLookupSearch({ form, onSelect }: Props) {
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(false)
  const [results, setResults] = useState<SeriesLookupResult[]>([])
  const [warnings, setWarnings] = useState<string[]>([])
  const [open, setOpen] = useState(false)
  const debounceRef = useRef<number | null>(null)

  useEffect(() => {
    if (debounceRef.current) {
      window.clearTimeout(debounceRef.current)
    }

    const trimmed = query.trim()
    if (trimmed.length < 2) {
      setResults([])
      setWarnings([])
      setLoading(false)
      return
    }

    if (/^https?:\/\//i.test(trimmed)) {
      setResults([])
      setWarnings([])
      setLoading(false)
      return
    }

    setLoading(true)
    debounceRef.current = window.setTimeout(() => {
      void (async () => {
        try {
          const params = new URLSearchParams({ q: trimmed, limit: '10' })
          const res = await api<{ results: SeriesLookupResult[]; warnings?: string[] }>(
            `/api/admin/series/lookup?${params}`,
          )
          setResults(res.results ?? [])
          setWarnings(res.warnings ?? [])
          setOpen(true)
        } catch (e) {
          setResults([])
          setWarnings([String((e as Error).message)])
          setOpen(true)
        } finally {
          setLoading(false)
        }
      })()
    }, 400)

    return () => {
      if (debounceRef.current) {
        window.clearTimeout(debounceRef.current)
      }
    }
  }, [query])

  function handleSelect(item: SeriesLookupResult) {
    if (item.source === 'kinopoisk') {
      form.setFieldsValue({ kp_id: item.id })
    } else {
      form.setFieldsValue({
        tmdb_id: item.id,
        content_type: item.media_type === 'tv' || item.media_type === 'series' ? 'series' : 'film',
      })
    }
    onSelect?.(item)
    setOpen(false)
    setQuery('')
    setResults([])
  }

  async function parseKpIdFromUrl(rawUrl: string) {
    const targetUrl = rawUrl.trim()
    if (!/^https?:\/\//i.test(targetUrl)) {
      message.warning('Вставьте полную ссылку (https://...)')
      return
    }

    setLoading(true)
    try {
      const params = new URLSearchParams({ url: targetUrl })
      const res = await api<{ kp_id: string }>(`/api/admin/series/parse-kp-from-url?${params}`)
      const kpId = String(res.kp_id ?? '').trim()
      if (!kpId) {
        throw new Error('KP ID не найден в ответе')
      }
      form.setFieldsValue({ kp_id: kpId })
      message.success(`KP ID ${kpId} подставлен`)
      setOpen(false)
      setResults([])
      setWarnings([])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }

  const showPanel = open && (loading || results.length > 0 || warnings.length > 0 || query.trim().length >= 2)

  return (
    <Form.Item label="Поиск по названию" extra="Kinopoisk + TMDB — выбор подставит соответствующий ID. Можно вставить ссылку lordserials.fan и нажать Enter — KP ID подставится автоматически.">
      <div style={{ position: 'relative' }}>
        <Input.Search
          placeholder="Название сериала или фильма…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onSearch={(value) => {
            const trimmed = value.trim()
            if (/^https?:\/\//i.test(trimmed)) {
              void parseKpIdFromUrl(trimmed)
            }
          }}
          onFocus={() => {
            if (query.trim().length >= 2) setOpen(true)
          }}
          onBlur={() => {
            window.setTimeout(() => setOpen(false), 150)
          }}
          allowClear
          loading={loading}
        />

        {showPanel ? (
          <div
            style={{
              position: 'absolute',
              zIndex: 20,
              top: '100%',
              left: 0,
              right: 0,
              marginTop: 4,
              background: '#fff',
              border: '1px solid #d9d9d9',
              borderRadius: 8,
              boxShadow: '0 6px 16px rgba(0,0,0,0.08)',
              maxHeight: 360,
              overflow: 'auto',
            }}
          >
            {warnings.length > 0 ? (
              <Alert
                type="warning"
                showIcon
                style={{ margin: 8 }}
                message={warnings.join(' · ')}
              />
            ) : null}

            {loading ? (
              <div style={{ padding: 16, textAlign: 'center' }}>
                <Spin size="small" />
              </div>
            ) : null}

            {!loading && results.length === 0 && query.trim().length >= 2 ? (
              <Typography.Text type="secondary" style={{ display: 'block', padding: 16 }}>
                Ничего не найдено
              </Typography.Text>
            ) : null}

            {!loading && results.length > 0 ? (
              <List
                size="small"
                dataSource={results}
                renderItem={(item) => {
                  const meta = [
                    item.year ? String(item.year) : null,
                    item.genres.length > 0 ? item.genres.slice(0, 3).join(', ') : null,
                    mediaTypeLabel(item.media_type),
                    item.rating != null ? `★ ${item.rating}` : null,
                  ].filter(Boolean)

                  return (
                    <List.Item
                      style={{ cursor: 'pointer', paddingInline: 12 }}
                      onMouseDown={(e) => e.preventDefault()}
                      onClick={() => handleSelect(item)}
                    >
                      <List.Item.Meta
                        avatar={
                          item.poster_url ? (
                            <Avatar
                              shape="square"
                              size={48}
                              src={(
                                <img
                                  src={resolveMediaUrl(item.poster_url)}
                                  alt=""
                                  referrerPolicy="no-referrer"
                                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                                />
                              )}
                            />
                          ) : (
                            <Avatar shape="square" size={48}>?</Avatar>
                          )
                        }
                        title={(
                          <span>
                            {item.title}
                            {' '}
                            <Tag color={sourceColor(item.source)} style={{ marginInlineStart: 4 }}>
                              {sourceLabel(item.source)}
                            </Tag>
                          </span>
                        )}
                        description={(
                          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {[meta.join(' · '), item.title_original].filter(Boolean).join(' — ')}
                          </Typography.Text>
                        )}
                      />
                    </List.Item>
                  )
                }}
              />
            ) : null}
          </div>
        ) : null}
      </div>
    </Form.Item>
  )
}
