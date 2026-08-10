import {
  CopyOutlined,
  DeleteOutlined,
  UploadOutlined,
} from '@ant-design/icons'
import {
  Button,
  Empty,
  Input,
  Pagination,
  Popconfirm,
  Segmented,
  Space,
  Spin,
  Tag,
  Tooltip,
  Typography,
  Upload,
  message,
} from 'antd'
import { useCallback, useEffect, useState } from 'react'
import { api, apiUpload } from '../api/client'
import { resolveMediaUrl, siteOrigin } from '../utils/mediaUrl'

export type MediaItem = {
  url: string
  path: string
  type: 'poster' | 'branding' | string
  name: string
  size?: number | null
  mtime?: number | null
  mime?: string | null
}

type MediaListResponse = {
  items: MediaItem[]
  total: number
  page: number
  per_page: number
  last_page: number
}

function formatBytes(bytes: number | null | undefined): string {
  if (bytes == null || bytes <= 0) return '—'
  if (bytes < 1024) return `${bytes} Б`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(bytes < 10 * 1024 ? 1 : 0)} КБ`
  return `${(bytes / (1024 * 1024)).toFixed(2)} МБ`
}

function formatMtime(mtime: number | null | undefined): string {
  if (!mtime) return ''
  try {
    return new Date(mtime * 1000).toLocaleString('ru-RU')
  } catch {
    return ''
  }
}

async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    try {
      const el = document.createElement('textarea')
      el.value = text
      el.setAttribute('readonly', '')
      el.style.position = 'fixed'
      el.style.left = '-9999px'
      document.body.appendChild(el)
      el.select()
      const ok = document.execCommand('copy')
      document.body.removeChild(el)
      return ok
    } catch {
      return false
    }
  }
}

type MediaLibraryPageProps = {
  /** Compact mode for MediaPickerModal */
  picker?: boolean
  onPick?: (item: MediaItem) => void
  typeFilter?: 'poster' | 'branding' | 'all'
}

export default function MediaLibraryPage({
  picker = false,
  onPick,
  typeFilter: initialType = 'all',
}: MediaLibraryPageProps = {}) {
  const [items, setItems] = useState<MediaItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(picker ? 24 : 48)
  const [query, setQuery] = useState('')
  const [search, setSearch] = useState('')
  const [type, setType] = useState<'all' | 'poster' | 'branding'>(
    initialType === 'all' ? 'all' : initialType,
  )
  const [loading, setLoading] = useState(false)
  const [uploading, setUploading] = useState(false)

  const load = useCallback(
    async (nextPage = page, nextPerPage = perPage, q = query, nextType = type) => {
      setLoading(true)
      try {
        const params = new URLSearchParams()
        params.set('page', String(nextPage))
        params.set('per_page', String(nextPerPage))
        if (q.trim()) params.set('q', q.trim())
        if (nextType !== 'all') params.set('type', nextType)
        const data = await api<MediaListResponse>(`/api/admin/media?${params}`)
        setItems(data.items)
        setTotal(data.total)
        setPage(data.page)
        setPerPage(data.per_page)
      } catch (e) {
        message.error(String((e as Error).message))
      } finally {
        setLoading(false)
      }
    },
    [page, perPage, query, type],
  )

  useEffect(() => {
    void load(1, perPage, query, type)
    // eslint-disable-next-line react-hooks/exhaustive-deps -- initial + filter changes via handlers
  }, [])

  function applySearch() {
    const next = search.trim()
    setQuery(next)
    setPage(1)
    void load(1, perPage, next, type)
  }

  function changeType(next: 'all' | 'poster' | 'branding') {
    setType(next)
    setPage(1)
    void load(1, perPage, query, next)
  }

  async function handleUpload(file: File) {
    setUploading(true)
    const fd = new FormData()
    fd.append('file', file)
    try {
      await apiUpload<{ ok: boolean; item: MediaItem }>('/api/admin/media/upload', fd)
      message.success('Файл загружен')
      await load(1, perPage, query, type)
      setPage(1)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setUploading(false)
    }
    return false
  }

  async function handleDelete(item: MediaItem) {
    try {
      const params = new URLSearchParams({ path: item.path })
      await api(`/api/admin/media?${params}`, { method: 'DELETE' })
      message.success('Удалено')
      await load(page, perPage, query, type)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function handleCopy(item: MediaItem) {
    const ok = await copyText(item.url)
    if (ok) message.success('URL скопирован')
    else message.error('Не удалось скопировать')
  }

  return (
    <div className={picker ? 'media-library media-library--picker' : 'media-library'}>
      <Space wrap style={{ width: '100%', marginBottom: 16, justifyContent: 'space-between' }}>
        <Space wrap>
          <Input.Search
            allowClear
            placeholder="Поиск по имени файла"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onSearch={applySearch}
            style={{ width: picker ? 220 : 280 }}
          />
          <Segmented
            value={type}
            onChange={(v) => changeType(v as 'all' | 'poster' | 'branding')}
            options={[
              { value: 'all', label: 'Все' },
              { value: 'poster', label: 'Постеры' },
              { value: 'branding', label: 'Брендинг' },
            ]}
          />
        </Space>
        <Upload beforeUpload={handleUpload} showUploadList={false} accept="image/*" disabled={uploading}>
          <Button type="primary" icon={<UploadOutlined />} loading={uploading}>
            Загрузить
          </Button>
        </Upload>
      </Space>

      <Spin spinning={loading}>
        {items.length === 0 && !loading ? (
          <Empty description="Нет файлов" />
        ) : (
          <div className="media-library__grid">
            {items.map((item) => (
              <div
                key={item.path}
                className={`media-library__card${picker ? ' media-library__card--pickable' : ''}`}
                onClick={picker && onPick ? () => onPick(item) : undefined}
                onKeyDown={
                  picker && onPick
                    ? (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                          e.preventDefault()
                          onPick(item)
                        }
                      }
                    : undefined
                }
                role={picker ? 'button' : undefined}
                tabIndex={picker ? 0 : undefined}
              >
                <div className="media-library__thumb">
                  <img src={resolveMediaUrl(item.url)} alt={item.name} loading="lazy" />
                </div>
                <div className="media-library__meta">
                  <Tooltip title={item.path}>
                    <Typography.Text ellipsis className="media-library__name">
                      {item.name}
                    </Typography.Text>
                  </Tooltip>
                  <div className="media-library__sub">
                    <Tag style={{ marginInlineEnd: 0 }}>{item.type === 'branding' ? 'branding' : 'poster'}</Tag>
                    <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                      {formatBytes(item.size)}
                      {item.mtime ? ` · ${formatMtime(item.mtime)}` : ''}
                    </Typography.Text>
                  </div>
                  {!picker ? (
                    <Space size={4} wrap style={{ marginTop: 6 }}>
                      <Button
                        size="small"
                        icon={<CopyOutlined />}
                        onClick={(e) => {
                          e.stopPropagation()
                          void handleCopy(item)
                        }}
                      >
                        URL
                      </Button>
                      <Button
                        size="small"
                        onClick={(e) => {
                          e.stopPropagation()
                          const origin = siteOrigin()
                          window.open(origin ? `${origin}${item.url}` : item.url, '_blank', 'noopener,noreferrer')
                        }}
                      >
                        Открыть
                      </Button>
                      <Popconfirm
                        title="Удалить файл?"
                        description={item.path}
                        okText="Удалить"
                        cancelText="Отмена"
                        okButtonProps={{ danger: true }}
                        onConfirm={() => handleDelete(item)}
                      >
                        <Button
                          size="small"
                          danger
                          icon={<DeleteOutlined />}
                          onClick={(e) => e.stopPropagation()}
                        />
                      </Popconfirm>
                    </Space>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
        )}
      </Spin>

      {total > 0 ? (
        <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
          <Pagination
            current={page}
            pageSize={perPage}
            total={total}
            showSizeChanger={!picker}
            pageSizeOptions={['24', '48', '96']}
            onChange={(p, ps) => {
              setPage(p)
              setPerPage(ps)
              void load(p, ps, query, type)
            }}
            showTotal={(t) => `${t} файлов`}
          />
        </div>
      ) : null}
    </div>
  )
}
