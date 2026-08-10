import {
  BookOutlined,
  CopyOutlined,
  DownloadOutlined,
  MenuOutlined,
  SearchOutlined,
} from '@ant-design/icons'
import { Alert, Button, Drawer, Empty, Input, Space, Spin, Typography, message } from 'antd'
import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { adminAuthHeaders } from '../auth/tokenStorage'

export type TplGuideBlock =
  | { type: 'h2' | 'h3'; id: string; text: string }
  | { type: 'p' | 'note'; text: string }
  | { type: 'code'; code: string; caption?: string }
  | { type: 'ul' | 'ol'; items: string[] }
  | { type: 'table'; headers: string[]; rows: string[][] }
  | {
      type: 'tags'
      kind?: string
      items: Array<{ name: string; syntax?: string; raw?: string; description?: string }>
    }

export type TplGuideArticle = {
  id: string
  group: string
  title: string
  summary?: string
  blocks: TplGuideBlock[]
}

export type TplGuidePayload = {
  version: string
  title: string
  subtitle: string
  nav: Array<{ group: string; items: Array<{ id: string; title: string }> }>
  articles: TplGuideArticle[]
  search_index: Array<{
    id: string
    title: string
    group: string
    text: string
    anchors: Array<{ id: string; title: string }>
  }>
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function formatInline(text: string): string {
  return escapeHtml(text)
    .replace(/`([^`]+)`/g, '<code class="tpl-guide-inline-code">$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
}

function copyText(value: string) {
  void navigator.clipboard.writeText(value).then(
    () => message.success('Скопировано'),
    () => message.error('Не удалось скопировать'),
  )
}

function GuideBlock({ block }: { block: TplGuideBlock }) {
  if (block.type === 'h2') {
    return (
      <Typography.Title level={3} id={block.id} className="tpl-guide-h">
        {block.text}
      </Typography.Title>
    )
  }
  if (block.type === 'h3') {
    return (
      <Typography.Title level={4} id={block.id} className="tpl-guide-h">
        {block.text}
      </Typography.Title>
    )
  }
  if (block.type === 'p') {
    return <p className="tpl-guide-p" dangerouslySetInnerHTML={{ __html: formatInline(block.text) }} />
  }
  if (block.type === 'note') {
    return (
      <Alert
        type="info"
        showIcon
        className="tpl-guide-note"
        message={<span dangerouslySetInnerHTML={{ __html: formatInline(block.text) }} />}
      />
    )
  }
  if (block.type === 'code') {
    return (
      <div className="tpl-guide-code">
        {block.caption ? <div className="tpl-guide-code__caption">{block.caption}</div> : null}
        <div className="tpl-guide-code__toolbar">
          <Button
            type="text"
            size="small"
            icon={<CopyOutlined />}
            onClick={() => copyText(block.code)}
          >
            Копировать
          </Button>
        </div>
        <pre>
          <code>{block.code}</code>
        </pre>
      </div>
    )
  }
  if (block.type === 'ul' || block.type === 'ol') {
    const Tag = block.type === 'ol' ? 'ol' : 'ul'
    return (
      <Tag className="tpl-guide-list">
        {block.items.map((item, i) => (
          <li key={i} dangerouslySetInnerHTML={{ __html: formatInline(item) }} />
        ))}
      </Tag>
    )
  }
  if (block.type === 'table') {
    return (
      <div className="tpl-guide-table-wrap">
        <table className="tpl-guide-table">
          <thead>
            <tr>
              {block.headers.map((h) => (
                <th key={h}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {block.rows.map((row, ri) => (
              <tr key={ri}>
                {row.map((cell, ci) => (
                  <td key={ci} dangerouslySetInnerHTML={{ __html: formatInline(cell) }} />
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }
  if (block.type === 'tags') {
    return (
      <div className="tpl-guide-tags">
        {block.items.map((tag) => (
          <div key={tag.name} className="tpl-guide-tag">
            <div className="tpl-guide-tag__head">
              <code>{tag.name}</code>
              <Space size={4}>
                <Button
                  type="text"
                  size="small"
                  icon={<CopyOutlined />}
                  onClick={() => copyText(tag.syntax || tag.name)}
                />
                {tag.raw ? (
                  <Button type="link" size="small" onClick={() => copyText(tag.raw!)}>
                    |raw
                  </Button>
                ) : null}
              </Space>
            </div>
            {tag.syntax && tag.syntax !== tag.name ? (
              <div className="tpl-guide-tag__syntax">
                <code>{tag.syntax}</code>
              </div>
            ) : null}
            {tag.description ? <div className="tpl-guide-tag__desc">{tag.description}</div> : null}
          </div>
        ))}
      </div>
    )
  }
  return null
}

function NavList({
  nav,
  activeId,
  onSelect,
}: {
  nav: TplGuidePayload['nav']
  activeId: string
  onSelect: (id: string) => void
}) {
  return (
    <nav className="tpl-guide-nav">
      {nav.map((group) => (
        <div key={group.group} className="tpl-guide-nav__group">
          <div className="tpl-guide-nav__label">{group.group}</div>
          {group.items.map((item) => (
            <button
              key={item.id}
              type="button"
              className={'tpl-guide-nav__item' + (item.id === activeId ? ' is-active' : '')}
              onClick={() => onSelect(item.id)}
            >
              {item.title}
            </button>
          ))}
        </div>
      ))}
    </nav>
  )
}

async function downloadGuideHtml() {
  const res = await fetch('/api/admin/templates/guide/download', {
    credentials: 'same-origin',
    headers: {
      Accept: 'text/html',
      ...adminAuthHeaders(),
    },
  })
  if (!res.ok) {
    throw new Error(`Не удалось скачать (${res.status})`)
  }
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'tpl-doc.html'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export default function TplGuidePage() {
  const { articleId } = useParams<{ articleId?: string }>()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [guide, setGuide] = useState<TplGuidePayload | null>(null)
  const [query, setQuery] = useState('')
  const [mobileNav, setMobileNav] = useState(false)
  const [downloading, setDownloading] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    api<TplGuidePayload>('/api/admin/templates/guide')
      .then((data) => {
        if (!cancelled) setGuide(data)
      })
      .catch((err: Error) => {
        if (!cancelled) message.error(err.message || 'Не удалось загрузить справку')
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  const articlesById = useMemo(() => {
    const map = new Map<string, TplGuideArticle>()
    for (const article of guide?.articles ?? []) map.set(article.id, article)
    return map
  }, [guide])

  const activeId = articleId && articlesById.has(articleId) ? articleId : guide?.articles[0]?.id ?? 'intro'
  const active = articlesById.get(activeId) ?? null

  useEffect(() => {
    if (!guide || articleId) return
    if (guide.articles[0]) {
      navigate(`/tpl-docs/${guide.articles[0].id}`, { replace: true })
    }
  }, [guide, articleId, navigate])

  const searchHits = useMemo(() => {
    const q = query.trim().toLowerCase()
    if (!q || !guide) return []
    return guide.search_index
      .filter((item) => item.text.includes(q) || item.title.toLowerCase().includes(q))
      .slice(0, 50)
  }, [guide, query])

  function selectArticle(id: string) {
    setQuery('')
    setMobileNav(false)
    navigate(`/tpl-docs/${id}`)
  }

  async function onDownload() {
    setDownloading(true)
    try {
      await downloadGuideHtml()
      message.success('Файл tpl-doc.html скачан')
    } catch (err) {
      message.error(err instanceof Error ? err.message : 'Ошибка скачивания')
    } finally {
      setDownloading(false)
    }
  }

  if (loading) {
    return (
      <div className="tpl-guide tpl-guide--loading">
        <Spin size="large" />
      </div>
    )
  }

  if (!guide) {
    return <Empty description="Справка недоступна" />
  }

  return (
    <div className="tpl-guide">
      <aside className="tpl-guide__sidebar">
        <div className="tpl-guide__brand">
          <div>
            <Typography.Title level={4} style={{ margin: 0 }}>
              <BookOutlined /> {guide.title}
            </Typography.Title>
            <Typography.Text type="secondary">v{guide.version}</Typography.Text>
          </div>
          <Button
            icon={<DownloadOutlined />}
            onClick={() => void onDownload()}
            loading={downloading}
            title="Скачать HTML локально"
          >
            HTML
          </Button>
        </div>
        <Typography.Paragraph type="secondary" className="tpl-guide__subtitle">
          {guide.subtitle}
        </Typography.Paragraph>
        <Input
          allowClear
          prefix={<SearchOutlined />}
          placeholder="Поиск: {series.title}, loop, layout…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="tpl-guide__search"
        />
        <NavList nav={guide.nav} activeId={activeId} onSelect={selectArticle} />
        <div className="tpl-guide__sidebar-foot">
          <Link to="/templates">Открыть редактор шаблонов</Link>
        </div>
      </aside>

      <section className="tpl-guide__content">
        <div className="tpl-guide__mobile-bar">
          <Button icon={<MenuOutlined />} onClick={() => setMobileNav(true)}>
            Разделы
          </Button>
          <Button icon={<DownloadOutlined />} loading={downloading} onClick={() => void onDownload()}>
            Скачать
          </Button>
        </div>

        {query.trim() ? (
          <div className="tpl-guide__hits">
            <Typography.Title level={3}>Результаты поиска</Typography.Title>
            {searchHits.length === 0 ? (
              <Empty description="Ничего не найдено" />
            ) : (
              searchHits.map((hit) => (
                <button
                  key={hit.id}
                  type="button"
                  className="tpl-guide-hit"
                  onClick={() => selectArticle(hit.id)}
                >
                  <strong>{hit.title}</strong>
                  <span>{hit.group}</span>
                </button>
              ))
            )}
          </div>
        ) : active ? (
          <article className="tpl-guide-article">
            <Typography.Title level={2} style={{ marginTop: 0 }}>
              {active.title}
            </Typography.Title>
            {active.summary ? (
              <Typography.Paragraph type="secondary" className="tpl-guide-article__summary">
                {active.summary}
              </Typography.Paragraph>
            ) : null}
            {active.blocks.map((block, index) => (
              <GuideBlock key={index} block={block} />
            ))}
          </article>
        ) : (
          <Empty description="Статья не найдена" />
        )}
      </section>

      <Drawer
        title="Разделы справки"
        placement="left"
        open={mobileNav}
        onClose={() => setMobileNav(false)}
        width={300}
      >
        <Input
          allowClear
          prefix={<SearchOutlined />}
          placeholder="Поиск…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          style={{ marginBottom: 12 }}
        />
        <NavList nav={guide.nav} activeId={activeId} onSelect={selectArticle} />
      </Drawer>
    </div>
  )
}
