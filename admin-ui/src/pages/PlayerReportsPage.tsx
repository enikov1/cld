import { Button, Popconfirm, Space, Table, Tag, Typography, message } from 'antd'
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import type { PlayerReportItem } from '../types'
import { siteOrigin } from '../utils/mediaUrl'

function seriesPublicPath(item: NonNullable<PlayerReportItem['series']>): string {
  const yearNum = Number(item.year || item.start_year || 0)
  let year = yearNum >= 1900 && yearNum <= 2100 ? String(yearNum) : '0000'
  const slug = (item.slug || '').trim() || 'series'
  return `/${item.id}-${slug}-${year}.html`
}

export default function PlayerReportsPage() {
  const [items, setItems] = useState<PlayerReportItem[]>([])
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(50)
  const [total, setTotal] = useState(0)

  const load = useCallback(async (nextPage = page, nextPerPage = perPage) => {
    setLoading(true)
    try {
      const data = await api<{
        items: PlayerReportItem[]
        total: number
        page: number
        per_page: number
      }>(`/api/admin/player-reports?page=${nextPage}&per_page=${nextPerPage}`)
      setItems(data.items)
      setTotal(data.total)
      setPage(data.page)
      setPerPage(data.per_page)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [page, perPage])

  useEffect(() => {
    void load(1, perPage)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function remove(id: number) {
    try {
      await api(`/api/admin/player-reports/${id}`, { method: 'DELETE' })
      message.success('Жалоба удалена')
      await load(page, perPage)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const columns: ColumnsType<PlayerReportItem> = [
    { title: 'ID', dataIndex: 'id', width: 70 },
    {
      title: 'Сериал',
      key: 'series',
      width: 220,
      ellipsis: true,
      render: (_, r) => {
        if (!r.series) return '—'
        return (
          <Space direction="vertical" size={0}>
            <span>{r.series.title}</span>
            <Space size="small" wrap>
              {r.series.kp_id ? (
                <Link to={`/series?kp_id=${encodeURIComponent(r.series.kp_id)}`}>В админке</Link>
              ) : r.series.id ? (
                <Link to={`/series?kp_id=${encodeURIComponent(String(r.series.id))}`}>В админке</Link>
              ) : null}
              <a href={`${siteOrigin()}${seriesPublicPath(r.series)}`} target="_blank" rel="noopener noreferrer">
                На сайте
              </a>
            </Space>
          </Space>
        )
      },
    },
    {
      title: 'Причина',
      key: 'reason',
      width: 280,
      render: (_, r) => (
        <Space direction="vertical" size={0}>
          <span>{r.reason_label || r.reason}</span>
          {r.player_label ? <Tag>{r.player_label}</Tag> : null}
        </Space>
      ),
    },
    {
      title: 'Сообщение',
      dataIndex: 'message',
      ellipsis: true,
      render: (v) => v || <Typography.Text type="secondary">—</Typography.Text>,
    },
    {
      title: 'Отправитель',
      key: 'user',
      width: 160,
      render: (_, r) => r.user?.name || r.user?.email || 'Гость',
    },
    {
      title: 'IP',
      dataIndex: 'ip',
      width: 130,
      render: (v) => v || '—',
    },
    {
      title: 'Дата',
      dataIndex: 'created_at',
      width: 170,
      render: (v) => (v ? new Date(v).toLocaleString('ru-RU') : '—'),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 110,
      render: (_, r) => (
        <Popconfirm title="Удалить жалобу?" onConfirm={() => remove(r.id)}>
          <Button size="small" danger>Удалить</Button>
        </Popconfirm>
      ),
    },
  ]

  function onTableChange(pagination: TablePaginationConfig) {
    const nextPage = pagination.current || 1
    const nextPerPage = pagination.pageSize || perPage
    setPage(nextPage)
    setPerPage(nextPerPage)
    void load(nextPage, nextPerPage)
  }

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <Typography.Text type="secondary">Всего жалоб: {total}</Typography.Text>
      </div>
      <Table
        rowKey="id"
        loading={loading}
        columns={columns}
        dataSource={items}
        onChange={onTableChange}
        pagination={{
          current: page,
          pageSize: perPage,
          total,
          showSizeChanger: true,
        }}
      />
    </div>
  )
}
