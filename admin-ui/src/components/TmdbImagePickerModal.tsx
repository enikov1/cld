import { Button, Empty, Modal, Segmented, Select, Space, Spin, Tag, Typography } from 'antd'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { TmdbIcon } from './brandIcons'
import { api } from '../api/client'

export type TmdbImageTarget = 'poster' | 'gallery' | 'brand'
export type TmdbImageSource = 'backdrops' | 'episodes'

export type TmdbImageCandidate = {
  id: string
  kind: string
  file_path: string
  width?: number | null
  height?: number | null
  aspect_ratio?: number | null
  iso_639_1?: string | null
  vote_average?: number
  vote_count?: number
  preview_url: string
  download_url: string
  season_number?: number | null
  episode_number?: number | null
  episode_title?: string | null
}

type Props = {
  open: boolean
  kpId: string | null | undefined
  target: TmdbImageTarget
  onClose: () => void
  onConfirm: (urls: string[]) => Promise<void> | void
}

const TARGET_TITLES: Record<TmdbImageTarget, string> = {
  poster: 'Выбор постера TMDB',
  gallery: 'Выбор изображений для галереи',
  brand: 'Выбор фона (бренд)',
}

function langLabel(code: string | null | undefined): string {
  if (!code) return 'без языка'
  return code.toUpperCase()
}

function episodeLabel(item: TmdbImageCandidate): string {
  const s = item.season_number
  const e = item.episode_number
  if (s == null || e == null) return 'Серия'
  return `S${s}E${e}`
}

export default function TmdbImagePickerModal({ open, kpId, target, onClose, onConfirm }: Props) {
  const multi = target === 'gallery'
  const allowsEpisodes = target === 'gallery' || target === 'brand'
  const [loading, setLoading] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const [candidates, setCandidates] = useState<TmdbImageCandidate[]>([])
  const [seasons, setSeasons] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)
  const [langFilter, setLangFilter] = useState<string>('all')
  const [imageSource, setImageSource] = useState<TmdbImageSource>('backdrops')
  const [seasonFilter, setSeasonFilter] = useState<number | 'all'>('all')
  const [selectedIds, setSelectedIds] = useState<string[]>([])

  const load = useCallback(async () => {
    if (!kpId) return
    setLoading(true)
    setError(null)
    try {
      const params = new URLSearchParams({
        target,
        source: imageSource,
      })
      if (imageSource === 'episodes' && seasonFilter !== 'all') {
        params.set('season', String(seasonFilter))
      }
      const res = await api<{
        ok: boolean
        error?: string
        candidates?: TmdbImageCandidate[]
        seasons?: number[]
      }>(`/api/admin/series/${kpId}/tmdb-images?${params.toString()}`)
      const list = res.candidates ?? []
      setCandidates(list)
      setSeasons(res.seasons ?? [])
      setSelectedIds([])
      if (list.length === 0) {
        setError(
          imageSource === 'episodes'
            ? 'TMDB не вернул кадров из серий (возможно, у серий нет still)'
            : 'TMDB не вернул подходящих изображений',
        )
      }
    } catch (e) {
      setCandidates([])
      setSeasons([])
      setSelectedIds([])
      setError(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [kpId, target, imageSource, seasonFilter])

  useEffect(() => {
    if (!open) return
    setLangFilter('all')
    setImageSource('backdrops')
    setSeasonFilter('all')
    setSelectedIds([])
    setCandidates([])
    setSeasons([])
    setError(null)
  }, [open, target])

  useEffect(() => {
    if (!open) return
    void load()
  }, [open, load])

  const langOptions = useMemo(() => {
    const codes = new Set<string>()
    for (const item of candidates) {
      codes.add(item.iso_639_1 || 'null')
    }
    const opts: { label: string; value: string }[] = [{ label: 'Все', value: 'all' }]
    const sorted = [...codes].sort((a, b) => {
      const rank = (v: string) => (v === 'ru' ? 0 : v === 'null' ? 1 : v === 'en' ? 2 : 3)
      const ra = rank(a)
      const rb = rank(b)
      if (ra !== rb) return ra - rb
      return a.localeCompare(b)
    })
    for (const code of sorted) {
      opts.push({
        label: code === 'null' ? 'Без языка' : code.toUpperCase(),
        value: code,
      })
    }
    return opts
  }, [candidates])

  const filtered = useMemo(() => {
    if (imageSource === 'episodes') return candidates
    if (langFilter === 'all') return candidates
    return candidates.filter((item) => (item.iso_639_1 || 'null') === langFilter)
  }, [candidates, langFilter, imageSource])

  const selected = useMemo(
    () => candidates.filter((item) => selectedIds.includes(item.id)),
    [candidates, selectedIds],
  )

  function toggleSelect(id: string) {
    if (multi) {
      setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
      return
    }
    setSelectedIds([id])
  }

  async function handleOk() {
    if (selected.length === 0) return
    setConfirming(true)
    try {
      await onConfirm(selected.map((item) => item.download_url))
      onClose()
    } catch (e) {
      setError(String((e as Error).message || 'Не удалось сохранить изображение'))
    } finally {
      setConfirming(false)
    }
  }

  const preview = selected[0] ?? null
  const sourceLabel = imageSource === 'episodes' ? 'Кадры из серий' : target === 'poster' ? 'Постеры' : 'Фоны / backdrops'

  return (
    <Modal
      title={TARGET_TITLES[target]}
      open={open}
      onCancel={() => {
        if (!confirming) onClose()
      }}
      width={1080}
      destroyOnHidden
      centered
      okText={multi ? `Добавить выбранные (${selected.length})` : 'Выбрать'}
      cancelText="Отмена"
      confirmLoading={confirming}
      okButtonProps={{ disabled: selected.length === 0 || confirming, icon: <TmdbIcon /> }}
      onOk={() => handleOk()}
      styles={{ body: { paddingTop: 12 } }}
    >
      <Space direction="vertical" size="middle" style={{ width: '100%' }}>
        {error && candidates.length > 0 ? (
          <Typography.Text type="danger">{error}</Typography.Text>
        ) : null}

        {allowsEpisodes ? (
          <Segmented
            value={imageSource}
            onChange={(value) => {
              setImageSource(String(value) as TmdbImageSource)
              setSeasonFilter('all')
              setSelectedIds([])
            }}
            options={[
              { label: 'Фоны TMDB', value: 'backdrops' },
              { label: 'Кадры из серий', value: 'episodes' },
            ]}
            disabled={loading || confirming}
          />
        ) : null}

        <Space wrap size={8} style={{ width: '100%', justifyContent: 'space-between' }}>
          <Typography.Text type="secondary">
            {sourceLabel} · {filtered.length} из {candidates.length}
          </Typography.Text>
          <Space wrap size={8}>
            {imageSource === 'episodes' && seasons.length > 0 ? (
              <Select
                size="small"
                style={{ minWidth: 140 }}
                value={seasonFilter}
                onChange={(value) => {
                  setSeasonFilter(value)
                  setSelectedIds([])
                }}
                disabled={loading || confirming}
                options={[
                  { label: 'Все сезоны', value: 'all' as const },
                  ...[...seasons].reverse().map((num) => ({
                    label: `Сезон ${num}`,
                    value: num,
                  })),
                ]}
              />
            ) : null}
            {imageSource === 'backdrops' ? (
              <Segmented
                size="small"
                value={langFilter}
                onChange={(value) => setLangFilter(String(value))}
                options={langOptions}
                disabled={loading || confirming || candidates.length === 0}
              />
            ) : null}
          </Space>
        </Space>

        <div className="tmdb-image-picker">
          <div className="tmdb-image-picker__list">
            <Spin spinning={loading}>
              {error && !loading && candidates.length === 0 ? (
                <Empty description={error} image={Empty.PRESENTED_IMAGE_SIMPLE} />
              ) : filtered.length === 0 && !loading ? (
                <Empty description="Нет изображений для выбранного фильтра" image={Empty.PRESENTED_IMAGE_SIMPLE} />
              ) : (
                <div
                  className={`tmdb-image-picker__grid${target === 'poster' ? ' is-poster' : ' is-wide'}`}
                  role="listbox"
                  aria-multiselectable={multi}
                  aria-label="Изображения TMDB"
                >
                  {filtered.map((item) => {
                    const active = selectedIds.includes(item.id)
                    return (
                      <button
                        key={item.id}
                        type="button"
                        role="option"
                        aria-selected={active}
                        className={`tmdb-image-picker__item${active ? ' is-active' : ''}`}
                        onClick={() => toggleSelect(item.id)}
                        disabled={confirming}
                      >
                        <span className="tmdb-image-picker__thumb">
                          <img src={item.preview_url} alt="" loading="lazy" />
                        </span>
                        <span className="tmdb-image-picker__meta">
                          {imageSource === 'episodes' ? (
                            <Tag>{episodeLabel(item)}</Tag>
                          ) : (
                            <Tag>{langLabel(item.iso_639_1)}</Tag>
                          )}
                          <span className="tmdb-image-picker__size">
                            {item.width && item.height ? `${item.width}×${item.height}` : '—'}
                          </span>
                          {(item.vote_average ?? 0) > 0 ? (
                            <span className="tmdb-image-picker__score">{item.vote_average?.toFixed(1)}</span>
                          ) : null}
                        </span>
                      </button>
                    )
                  })}
                </div>
              )}
            </Spin>
          </div>

          <div className="tmdb-image-picker__preview">
            {preview ? (
              <>
                <div className="tmdb-image-picker__preview-title">
                  {imageSource === 'episodes' ? (
                    <>
                      {episodeLabel(preview)}
                      {preview.episode_title ? ` · ${preview.episode_title}` : ''}
                    </>
                  ) : (
                    langLabel(preview.iso_639_1)
                  )}
                  {preview.width && preview.height ? ` · ${preview.width}×${preview.height}` : ''}
                  {multi && selected.length > 1 ? ` · выбрано ${selected.length}` : ''}
                </div>
                <div className={`tmdb-image-picker__preview-frame${target === 'poster' ? ' is-poster' : ' is-wide'}`}>
                  <img src={preview.preview_url} alt="Превью TMDB" />
                </div>
                <Button type="link" href={preview.download_url} target="_blank" rel="noreferrer" style={{ paddingInline: 0 }}>
                  Открыть оригинал
                </Button>
              </>
            ) : (
              <Empty
                description={multi ? 'Выберите одно или несколько изображений' : 'Выберите изображение слева'}
                image={Empty.PRESENTED_IMAGE_SIMPLE}
              />
            )}
          </div>
        </div>
      </Space>
    </Modal>
  )
}
