import { Alert, Button, Collapse, Dropdown, Input, InputNumber, Select, Space, Spin, Typography, message } from 'antd'
import type { MenuProps } from 'antd'
import { forwardRef, useCallback, useEffect, useImperativeHandle, useState } from 'react'
import { api } from '../api/client'

type ScheduleEpisode = {
  episode_number: number
  title?: string | null
  release_at?: string | null
  release_at_iso?: string | null
  status: 'released' | 'scheduled'
  voice?: string | null
}

type ScheduleSeason = {
  season_number: number
  title?: string | null
  episodes: ScheduleEpisode[]
}

export type SeriesScheduleEditorHandle = {
  save: (options?: { silent?: boolean }) => Promise<boolean>
}

type Props = {
  kpId?: string | null
  tmdbId?: string | null
  drawerOpen: boolean
  onBroadcastStatusChange?: (status: 'ongoing' | 'paused' | 'completed' | null) => void
}

const SeriesScheduleEditor = forwardRef<SeriesScheduleEditorHandle, Props>(function SeriesScheduleEditor(
  { kpId, tmdbId, drawerOpen, onBroadcastStatusChange },
  ref,
) {
  const [seasons, setSeasons] = useState<ScheduleSeason[]>([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [importing, setImporting] = useState(false)
  const [tmdbConfigured, setTmdbConfigured] = useState(true)
  const [resolvedTmdbId, setResolvedTmdbId] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!kpId || !drawerOpen) return
    setLoading(true)
    try {
      const data = await api<{
        seasons: ScheduleSeason[]
        tmdb_id?: string | null
        tmdb_api_key_set?: boolean
      }>(`/api/admin/series/${kpId}/schedule`)
      setSeasons(data.seasons ?? [])
      setResolvedTmdbId(data.tmdb_id ?? null)
      setTmdbConfigured(data.tmdb_api_key_set !== false)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [kpId, drawerOpen])

  useEffect(() => {
    if (!drawerOpen || !kpId) return
    load()
  }, [kpId, drawerOpen, load])

  useEffect(() => {
    if (tmdbId !== undefined) {
      setResolvedTmdbId(tmdbId?.trim() ? tmdbId.trim() : null)
    }
  }, [tmdbId])

  const saveSchedule = useCallback(
    async (options?: { silent?: boolean }) => {
      if (!kpId) return true

      setSaving(true)
      try {
        const payload = {
          seasons: seasons.map((s) => ({
            season_number: s.season_number,
            title: s.title,
            episodes: s.episodes.map((e) => ({
              episode_number: e.episode_number,
              title: e.title,
              release_at: e.release_at_iso || null,
              status: e.status,
              voice: e.voice || null,
            })),
          })),
        }
        const res = await api<{ seasons: ScheduleSeason[] }>(`/api/admin/series/${kpId}/schedule`, {
          method: 'POST',
          body: JSON.stringify(payload),
        })
        setSeasons(res.seasons ?? [])
        if (!options?.silent) {
          message.success('Расписание сохранено')
        }
        return true
      } catch (e) {
        if (!options?.silent) {
          message.error(String((e as Error).message))
        }
        return false
      } finally {
        setSaving(false)
      }
    },
    [kpId, seasons],
  )

  useImperativeHandle(ref, () => ({ save: saveSchedule }), [saveSchedule])

  const importFromTmdb = useCallback(
    async (mode: 'replace' | 'merge') => {
      if (!kpId) return

      setImporting(true)
      try {
        const res = await api<{
          ok: boolean
          seasons: ScheduleSeason[]
          meta?: {
            seasons_count: number
            episodes_count: number
            tmdb_status?: string | null
            broadcast_status_mapped?: string | null
          }
          broadcast_status?: 'ongoing' | 'paused' | 'completed' | null
          broadcast_status_changed?: boolean
          error?: string
        }>(`/api/admin/series/${kpId}/schedule/import-tmdb`, {
          method: 'POST',
          body: JSON.stringify({
            mode,
            persist: false,
            update_broadcast_status: true,
            seasons:
              mode === 'merge'
                ? seasons.map((s) => ({
                    season_number: s.season_number,
                    title: s.title,
                    episodes: s.episodes.map((e) => ({
                      episode_number: e.episode_number,
                      title: e.title,
                      release_at: e.release_at_iso || null,
                      status: e.status,
                      voice: e.voice || null,
                    })),
                  }))
                : undefined,
          }),
        })

        setSeasons(res.seasons ?? [])
        if (res.broadcast_status !== undefined) {
          onBroadcastStatusChange?.(res.broadcast_status)
        }
        const meta = res.meta
        const statusHint = res.broadcast_status_changed
          ? ` · статус: ${res.broadcast_status ?? '—'}`
          : ''
        message.success(
          meta
            ? `Импортировано из TMDB: ${meta.seasons_count} сез., ${meta.episodes_count} сер.${statusHint} — проверьте и сохраните`
            : `Данные TMDB загружены${statusHint} — проверьте и сохраните`,
        )
      } catch (e) {
        message.error(String((e as Error).message))
      } finally {
        setImporting(false)
      }
    },
    [kpId, seasons, onBroadcastStatusChange],
  )

  function addSeason() {
    const nextNum = seasons.length ? Math.max(...seasons.map((s) => s.season_number)) + 1 : 1
    setSeasons([...seasons, { season_number: nextNum, title: `Сезон ${nextNum}`, episodes: [] }])
  }

  function addEpisode(seasonIndex: number) {
    const next = [...seasons]
    const season = next[seasonIndex]
    const epNum = season.episodes.length
      ? Math.max(...season.episodes.map((e) => e.episode_number)) + 1
      : 1
    season.episodes = [...season.episodes, { episode_number: epNum, title: `Серия ${epNum}`, status: 'scheduled' }]
    setSeasons(next)
  }

  if (!kpId) {
    return <p className="admin-empty-hint">Сначала укажите KP ID и сохраните сериал.</p>
  }

  const canImportTmdb = Boolean(resolvedTmdbId) && tmdbConfigured
  const importMenu: MenuProps['items'] = [
    {
      key: 'replace',
      label: 'Заменить текущее расписание',
      onClick: () => void importFromTmdb('replace'),
    },
    {
      key: 'merge',
      label: 'Дополнить / обновить (сохранить озвучку)',
      onClick: () => void importFromTmdb('merge'),
    },
  ]

  return (
    <div style={{ marginTop: 16 }}>
      {!tmdbConfigured ? (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 12 }}
          message="API-ключ TMDB не настроен — импорт расписания недоступен"
        />
      ) : !resolvedTmdbId ? (
        <Alert
          type="info"
          showIcon
          style={{ marginBottom: 12 }}
          message="Укажите TMDB ID на вкладке «Основное», чтобы импортировать график серий"
        />
      ) : null}

      <Space style={{ marginBottom: 12 }} wrap>
        <Dropdown menu={{ items: importMenu }} disabled={!canImportTmdb || loading || importing}>
          <Button type="primary" loading={importing} disabled={!canImportTmdb || loading}>
            Импорт из TMDB
          </Button>
        </Dropdown>
        <Button onClick={addSeason} disabled={loading || importing}>
          Добавить сезон
        </Button>
        <Button onClick={() => saveSchedule()} loading={saving} disabled={loading || importing}>
          Сохранить расписание
        </Button>
      </Space>

      {resolvedTmdbId ? (
        <Typography.Paragraph type="secondary" style={{ marginTop: 0 }}>
          TMDB ID: {resolvedTmdbId}. Импорт подставляет даты оригинального эфира и статус сериала (идёт /
          завершён; ручная «пауза» сохраняется). После загрузки проверьте и сохраните расписание.
        </Typography.Paragraph>
      ) : null}

      <Spin spinning={loading || importing}>
        {seasons.length === 0 && !loading ? (
          <Typography.Paragraph type="secondary">
            Расписание пустое. Импортируйте из TMDB или добавьте сезон вручную.
          </Typography.Paragraph>
        ) : null}

        <Collapse
          items={seasons.map((season, si) => ({
            key: String(season.season_number),
            label: `${season.title || `Сезон ${season.season_number}`} · ${season.episodes.length} сер.`,
            extra: (
              <Button
                size="small"
                onClick={(e) => {
                  e.stopPropagation()
                  addEpisode(si)
                }}
              >
                + серия
              </Button>
            ),
            children: (
              <Space direction="vertical" style={{ width: '100%' }} size="middle">
                <Space wrap>
                  <span>Сезон №</span>
                  <InputNumber
                    min={1}
                    value={season.season_number}
                    onChange={(v) => {
                      const next = [...seasons]
                      next[si] = { ...season, season_number: Number(v) || 1 }
                      setSeasons(next)
                    }}
                  />
                  <Input
                    placeholder="Название сезона"
                    value={season.title ?? ''}
                    onChange={(e) => {
                      const next = [...seasons]
                      next[si] = { ...season, title: e.target.value }
                      setSeasons(next)
                    }}
                    style={{ width: 220 }}
                  />
                  <Button
                    danger
                    size="small"
                    onClick={() => {
                      setSeasons(seasons.filter((_, i) => i !== si))
                    }}
                  >
                    Удалить сезон
                  </Button>
                </Space>

                {season.episodes.map((ep, ei) => (
                  <Space key={`${si}-${ei}`} wrap align="start">
                    <InputNumber
                      min={1}
                      value={ep.episode_number}
                      onChange={(v) => {
                        const next = [...seasons]
                        const eps = [...next[si].episodes]
                        eps[ei] = { ...ep, episode_number: Number(v) || 1 }
                        next[si] = { ...season, episodes: eps }
                        setSeasons(next)
                      }}
                    />
                    <Input
                      placeholder="Название серии"
                      value={ep.title ?? ''}
                      onChange={(e) => {
                        const next = [...seasons]
                        const eps = [...next[si].episodes]
                        eps[ei] = { ...ep, title: e.target.value }
                        next[si] = { ...season, episodes: eps }
                        setSeasons(next)
                      }}
                      style={{ width: 200 }}
                    />
                    <Input
                      type="date"
                      value={ep.release_at_iso ?? ''}
                      onChange={(e) => {
                        const next = [...seasons]
                        const eps = [...next[si].episodes]
                        eps[ei] = { ...ep, release_at_iso: e.target.value || null }
                        next[si] = { ...season, episodes: eps }
                        setSeasons(next)
                      }}
                      style={{ width: 150 }}
                    />
                    <Select
                      style={{ width: 130 }}
                      value={ep.status}
                      onChange={(v) => {
                        const next = [...seasons]
                        const eps = [...next[si].episodes]
                        eps[ei] = { ...ep, status: v }
                        next[si] = { ...season, episodes: eps }
                        setSeasons(next)
                      }}
                      options={[
                        { value: 'released', label: 'Вышла' },
                        { value: 'scheduled', label: 'Ожидается' },
                      ]}
                    />
                    <Input
                      placeholder="Озвучка"
                      value={ep.voice ?? ''}
                      onChange={(e) => {
                        const next = [...seasons]
                        const eps = [...next[si].episodes]
                        eps[ei] = { ...ep, voice: e.target.value }
                        next[si] = { ...season, episodes: eps }
                        setSeasons(next)
                      }}
                      style={{ width: 120 }}
                    />
                    <Button
                      danger
                      size="small"
                      onClick={() => {
                        const next = [...seasons]
                        next[si] = {
                          ...season,
                          episodes: season.episodes.filter((_, i) => i !== ei),
                        }
                        setSeasons(next)
                      }}
                    >
                      ×
                    </Button>
                  </Space>
                ))}
              </Space>
            ),
          }))}
        />
      </Spin>
    </div>
  )
})

export default SeriesScheduleEditor
