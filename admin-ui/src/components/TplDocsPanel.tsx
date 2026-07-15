import { BookOutlined, CopyOutlined } from '@ant-design/icons'
import { Alert, Card, Collapse, Input, Space, Table, Tag, Typography, message } from 'antd'
import { useMemo, useState } from 'react'
import type { TplContext, TplDocsPayload, TplHint } from '../types/tplDocs'
import { contextsForPath, filterHints } from '../types/tplDocs'

type TplDocsPanelProps = {
  docs: TplDocsPayload | null
  filePath?: string | null
  compact?: boolean
  onInsert?: (text: string) => void
}

function copyText(text: string) {
  navigator.clipboard.writeText(text).then(
    () => message.success('Скопировано'),
    () => message.error('Не удалось скопировать'),
  )
}

function ContextBlock({
  ctxKey,
  ctx,
  compact,
  onInsert,
}: {
  ctxKey: string
  ctx: TplContext
  compact?: boolean
  onInsert?: (text: string) => void
}) {
  const tagColumnWidth = compact ? 140 : 200

  return (
    <div className="tpl-docs-context">
      <Typography.Paragraph type="secondary">{ctx.description}</Typography.Paragraph>

      <Typography.Title level={5}>Переменные</Typography.Title>
      <Table
        size="small"
        pagination={false}
        tableLayout="fixed"
        scroll={{ x: compact ? 420 : 640 }}
        rowKey="name"
        dataSource={ctx.variables}
        columns={[
          {
            title: 'Тег',
            dataIndex: 'name',
            width: tagColumnWidth,
            render: (name: string) => (
              <div className="tpl-docs-tag-cell">
                <Typography.Text code className="tpl-docs-tag-code">
                  {'{' + name + '}'}
                </Typography.Text>
                <Space size={4} className="tpl-docs-tag-actions">
                  <CopyOutlined className="tpl-docs-copy" onClick={() => copyText('{' + name + '}')} />
                  {onInsert ? (
                    <a onClick={() => onInsert('{' + name + '}')}>вставить</a>
                  ) : null}
                </Space>
              </div>
            ),
          },
          {
            title: 'Описание',
            dataIndex: 'description',
            ellipsis: true,
          },
          {
            title: 'Пример',
            dataIndex: 'sample',
            width: compact ? 100 : 140,
            ellipsis: true,
            render: (sample: string | undefined) =>
              sample ? <Typography.Text type="secondary">{sample}</Typography.Text> : '—',
          },
        ]}
      />

      {ctx.flags.length > 0 ? (
        <>
          <Typography.Title level={5}>Условия</Typography.Title>
          <Table
            size="small"
            pagination={false}
            tableLayout="fixed"
            scroll={{ x: 320 }}
            rowKey="syntax"
            dataSource={[...ctx.flags, ...ctx.not_flags]}
            columns={[
              {
                title: 'Синтаксис',
                dataIndex: 'syntax',
                render: (syntax: string) => (
                  <Space wrap>
                    <Typography.Text code className="tpl-docs-tag-code">
                      {syntax}
                    </Typography.Text>
                    <CopyOutlined className="tpl-docs-copy" onClick={() => copyText(syntax)} />
                  </Space>
                ),
              },
            ]}
          />
        </>
      ) : null}

      {ctx.loops.length > 0 ? (
        <>
          <Typography.Title level={5}>Циклы</Typography.Title>
          <Table
            size="small"
            pagination={false}
            tableLayout="fixed"
            scroll={{ x: 420 }}
            rowKey="syntax"
            dataSource={ctx.loops}
            columns={[
              {
                title: 'Синтаксис',
                dataIndex: 'syntax',
                width: 180,
                render: (syntax: string) => (
                  <Typography.Text code className="tpl-docs-tag-code">
                    [{syntax}] ... [/loop]
                  </Typography.Text>
                ),
              },
              { title: 'Описание', dataIndex: 'description', ellipsis: true },
            ]}
          />
        </>
      ) : null}

      <Tag>{ctxKey}</Tag>
    </div>
  )
}

export default function TplDocsPanel({ docs, filePath, compact, onInsert }: TplDocsPanelProps) {
  const [hintQuery, setHintQuery] = useState('')

  const activeContexts = useMemo(() => {
    if (docs?.active_contexts?.length) return docs.active_contexts
    return contextsForPath(filePath ?? null)
  }, [docs?.active_contexts, filePath])

  const contextItems = useMemo(() => {
    if (!docs) return []
    return activeContexts
      .filter((key) => docs.contexts[key])
      .map((key) => ({
        key,
        label: docs.contexts[key].title,
        children: (
          <ContextBlock ctxKey={key} ctx={docs.contexts[key]} compact={compact} onInsert={onInsert} />
        ),
      }))
  }, [activeContexts, compact, docs, onInsert])

  const quickHints = useMemo(() => {
    if (!docs) return [] as TplHint[]
    return filterHints(docs.hints, activeContexts, hintQuery).slice(0, compact ? 8 : 24)
  }, [activeContexts, compact, docs, hintQuery])

  if (!docs) {
    return <Alert type="info" message="Загрузка справки..." showIcon />
  }

  return (
    <div className={'tpl-docs' + (compact ? ' tpl-docs--compact' : '')}>
      {!compact ? (
        <>
          <Typography.Title level={4}>
            <BookOutlined /> Справка по TPL-тегам
          </Typography.Title>
          <Typography.Paragraph type="secondary">
            В редакторе набирайте <Typography.Text code>{'{'}</Typography.Text> или{' '}
            <Typography.Text code>[</Typography.Text> — появятся подсказки. Tab/Enter — вставить.
          </Typography.Paragraph>

          <Collapse
            defaultActiveKey={['syntax']}
            items={docs.syntax.map((section) => ({
              key: section.id,
              label: section.title,
              children: (
                <div>
                  <Typography.Paragraph>{section.description}</Typography.Paragraph>
                  {section.examples.map((ex, i) => (
                    <div key={i} className="tpl-docs-example">
                      <pre>{ex.code}</pre>
                      {ex.note ? <Typography.Text type="secondary">{ex.note}</Typography.Text> : null}
                    </div>
                  ))}
                </div>
              ),
            }))}
          />
        </>
      ) : null}

      {filePath ? (
        <Alert
          className="tpl-docs__file-hint"
          type="info"
          showIcon
          message={`Контекст для файла: ${filePath}`}
          description={
            <>
              Активные разделы: {activeContexts.join(', ')}
              {docs.sample_source ? (
                <>
                  <br />
                  Примеры значений: {docs.sample_source}
                </>
              ) : null}
            </>
          }
        />
      ) : null}

      <Card size="small" title="Быстрые подсказки" className="tpl-docs__quick">
        <Input
          allowClear
          placeholder="Поиск тега..."
          value={hintQuery}
          onChange={(e) => setHintQuery(e.target.value)}
          className="tpl-docs__search"
        />
        <div className="tpl-docs__chips">
          {quickHints.map((hint) => (
            <Tag
              key={hint.kind + hint.insert}
              className="tpl-docs__chip"
              title={hint.detail ?? hint.sample ?? hint.label}
              onClick={() => (onInsert ? onInsert(hint.insert) : copyText(hint.insert))}
            >
              {hint.label}
              {hint.detail ? <span className="tpl-docs__chip-detail"> {hint.detail}</span> : null}
            </Tag>
          ))}
        </div>
      </Card>

      {!compact ? (
        <Collapse defaultActiveKey={activeContexts} items={contextItems} className="tpl-docs__contexts" />
      ) : (
        <Collapse
          key={filePath ?? 'no-file'}
          defaultActiveKey={activeContexts}
          items={contextItems}
          size="small"
          className="tpl-docs__contexts tpl-docs__contexts--compact"
        />
      )}
    </div>
  )
}
