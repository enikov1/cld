import { Button, Collapse, Input, InputNumber, Select, Space, Spin, message } from 'antd'
import { useCallback, useEffect, useState } from 'react'
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

type Props = {
  kpId?: string | null
  open: boolean
}

export default function SeriesScheduleEditor({ kpId, open }: Props) {
  const [seasons, setSeasons] = useState<ScheduleSeason[]>([])
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    if (!kpId || !open) return
    setLoading(true)
    try {
      const data = await api<{ seasons: ScheduleSeason[] }>(`/api/admin/series/${kpId}/schedule`)
      setSeasons(data.seasons ?? [])
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [kpId, open])

  useEffect(() => {
    load()
  }, [load])

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

  async function save() {
    if (!kpId) return
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
      message.success('Расписание сохранено')
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setSaving(false)
    }
  }

  if (!kpId) {
    return <p className="admin-empty-hint">Сначала укажите KP ID и сохраните сериал.</p>
  }

  return (
    <div style={{ marginTop: 16 }}>
      <Space style={{ marginBottom: 12 }}>
        <Button onClick={addSeason} disabled={loading}>Добавить сезон</Button>
        <Button type="primary" onClick={save} loading={saving} disabled={loading}>Сохранить расписание</Button>
      </Space>

      <Spin spinning={loading}>
        <Collapse
          items={seasons.map((season, si) => ({
          key: String(season.season_number),
          label: season.title || `Сезон ${season.season_number}`,
          extra: (
            <Button size="small" onClick={(e) => { e.stopPropagation(); addEpisode(si) }}>
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
}
