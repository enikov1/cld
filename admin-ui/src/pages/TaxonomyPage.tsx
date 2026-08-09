import { Button, Form, Input, InputNumber, Modal, Popconfirm, Select, Space, Switch, Table, Tabs, Tag, Tooltip, Typography, Upload, message } from 'antd'
import { CopyOutlined, EditOutlined, ImportOutlined } from '@ant-design/icons'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api, apiUpload } from '../api/client'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { TaxonomyItem, TaxonomyType } from '../types'
import { resolveMediaUrl } from '../utils/mediaUrl'
import {
  DEFAULT_TAXONOMY_SEO_AI_PROMPT,
  TAXONOMY_SEO_AI_PROMPT_KEY,
  TAXONOMY_TYPE_LABELS,
  fillTaxonomySeoAiPrompt,
  parseTaxonomySeoAiResult,
} from '../utils/taxonomySeoAiPrompt'

const TYPES: { key: TaxonomyType; label: string; urlPrefix: string }[] = [
  { key: 'genres', label: 'Жанры', urlPrefix: 'genre' },
  { key: 'countries', label: 'Страны', urlPrefix: 'country' },
  { key: 'people', label: 'Актёры', urlPrefix: 'person' },
  { key: 'years', label: 'Годы', urlPrefix: 'year' },
]

const HOME_SORT_OPTIONS = [
  { value: 'latest', label: 'Последние' },
  { value: 'popular', label: 'Популярные' },
  { value: 'rating', label: 'По рейтингу' },
]

type SettingRow = { key: string; value: string }

async function loadTaxonomySeoPromptTemplate(): Promise<string> {
  const data = await api<{ items: SettingRow[] }>('/api/admin/settings')
  const value = data.items.find((row) => row.key === TAXONOMY_SEO_AI_PROMPT_KEY)?.value?.trim()
  return value || DEFAULT_TAXONOMY_SEO_AI_PROMPT
}

function TaxonomyTab({ type, label, urlPrefix }: { type: TaxonomyType; label: string; urlPrefix: string }) {
  const { isDark } = useAdminTheme()
  const [items, setItems] = useState<TaxonomyItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<TaxonomyItem | null>(null)
  const [form] = Form.useForm()
  const photoUrl = Form.useWatch('photo_url', form)

  const [promptModalOpen, setPromptModalOpen] = useState(false)
  const [promptLoading, setPromptLoading] = useState(false)
  const [promptText, setPromptText] = useState('')

  const [templateModalOpen, setTemplateModalOpen] = useState(false)
  const [templateLoading, setTemplateLoading] = useState(false)
  const [templateSaving, setTemplateSaving] = useState(false)
  const [templateText, setTemplateText] = useState('')

  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importPreview, setImportPreview] = useState<ReturnType<typeof parseTaxonomySeoAiResult>>(null)
  const [importError, setImportError] = useState('')

  useDocumentTitle(
    importModalOpen
      ? 'Импорт SEO из ИИ'
      : templateModalOpen
        ? 'Шаблон промпта для SEO справочников'
        : promptModalOpen
          ? 'Промпт для ИИ — SEO справочника'
          : modalOpen
            ? editing
              ? `Редактируем: ${editing.name}`
              : `Новый: ${label}`
            : null,
  )
  useBusyFavicon(promptLoading || templateLoading || templateSaving)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await api<{ items: TaxonomyItem[] }>(`/api/admin/taxonomies/${type}`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [type])

  useEffect(() => {
    load()
  }, [load])

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({ is_active: true, is_hidden: false, noindex: true, sort_order: 0, seo_html: '', show_on_home: false, home_item_limit: 18, home_show_tabs: true, home_default_sort: 'latest' })
    setModalOpen(true)
  }

  function openEdit(row: TaxonomyItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
    })
    setModalOpen(true)
  }

  async function save(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      await api(`/api/admin/taxonomies/${type}/upsert`, {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Сохранено' : 'Создано')
      setModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function remove(id: number) {
    try {
      await api(`/api/admin/taxonomies/${type}/${id}`, { method: 'DELETE' })
      message.success('Удалено')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function buildPromptVars() {
    const name = String(form.getFieldValue('name') || '').trim()
    const slug = String(form.getFieldValue('slug') || '').trim()
    return {
      name,
      type,
      typeLabel: TAXONOMY_TYPE_LABELS[type],
      slug,
      url: `/${urlPrefix}/${slug || '{slug}'}/`,
    }
  }

  async function openPromptModal() {
    const vars = buildPromptVars()
    if (!vars.name) {
      message.warning('Сначала укажите название')
      return
    }

    setPromptModalOpen(true)
    setPromptLoading(true)
    setPromptText('')
    try {
      const template = await loadTaxonomySeoPromptTemplate()
      setPromptText(fillTaxonomySeoAiPrompt(template, vars))
    } catch (e) {
      message.error(String((e as Error).message))
      setPromptModalOpen(false)
    } finally {
      setPromptLoading(false)
    }
  }

  async function copyPrompt() {
    if (!promptText.trim()) return
    try {
      await navigator.clipboard.writeText(promptText)
      message.success('Промпт скопирован в буфер обмена')
    } catch {
      message.error('Не удалось скопировать')
    }
  }

  async function openTemplateModal() {
    setTemplateModalOpen(true)
    setTemplateLoading(true)
    try {
      setTemplateText(await loadTaxonomySeoPromptTemplate())
    } catch (e) {
      message.error(String((e as Error).message))
      setTemplateModalOpen(false)
    } finally {
      setTemplateLoading(false)
    }
  }

  async function saveTemplate() {
    setTemplateSaving(true)
    try {
      await api('/api/admin/settings', {
        method: 'POST',
        body: JSON.stringify({
          settings: [{ key: TAXONOMY_SEO_AI_PROMPT_KEY, value: templateText }],
        }),
      })
      message.success('Шаблон промпта сохранён')
      setTemplateModalOpen(false)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setTemplateSaving(false)
    }
  }

  function openImportModal() {
    setImportModalOpen(true)
    setImportText('')
    setImportPreview(null)
    setImportError('')
  }

  function runImportPreview() {
    const payload = importText.trim()
    if (!payload) {
      message.warning('Вставьте JSON-ответ от ИИ')
      return
    }

    const parsed = parseTaxonomySeoAiResult(payload)
    if (!parsed) {
      setImportPreview(null)
      setImportError('Не удалось распознать JSON. Вставьте ответ ИИ целиком или только JSON-блок.')
      message.error('Не удалось распознать JSON')
      return
    }

    setImportError('')
    setImportPreview(parsed)
    message.success('JSON распознан — проверьте предпросмотр')
  }

  function applyImport() {
    if (!importPreview) {
      message.warning('Сначала вставьте JSON и нажмите «Проверить»')
      return
    }

    if (importPreview.meta_title) form.setFieldValue('meta_title', importPreview.meta_title)
    if (importPreview.meta_description) form.setFieldValue('meta_description', importPreview.meta_description)
    if (importPreview.seo_html) form.setFieldValue('seo_html', importPreview.seo_html)

    message.success('SEO-поля заполнены из ответа ИИ')
    setImportModalOpen(false)
    setImportText('')
    setImportPreview(null)
    setImportError('')
  }

  const columns: ColumnsType<TaxonomyItem> = [
    ...(type === 'people'
      ? [{
          title: 'Фото',
          key: 'photo',
          width: 72,
          render: (_: unknown, row: TaxonomyItem) => (
            row.photo_url ? (
              <Tooltip
                title={(
                  <img
                    src={resolveMediaUrl(row.photo_url)}
                    alt=""
                    style={{ width: 120, height: 160, objectFit: 'cover', borderRadius: 6, display: 'block' }}
                  />
                )}
              >
                <img
                  src={resolveMediaUrl(row.photo_url)}
                  alt=""
                  style={{ width: 40, height: 52, objectFit: 'cover', borderRadius: 4, cursor: 'pointer' }}
                />
              </Tooltip>
            ) : (
              <span style={{ color: '#999' }}>—</span>
            )
          ),
        }]
      : []),
    { title: 'Название', dataIndex: 'name', key: 'name' },
    {
      title: 'URL',
      dataIndex: 'slug',
      key: 'slug',
      width: 200,
      render: (slug) => `/${urlPrefix}/${slug}/`,
    },
    {
      title: 'Сериалов',
      dataIndex: 'series_count',
      key: 'series_count',
      width: 100,
      render: (count: number | undefined) => count ?? 0,
    },
    { title: 'Порядок', dataIndex: 'sort_order', key: 'sort_order', width: 90 },
    {
      title: 'Главная',
      key: 'home',
      width: 110,
      render: (_, row) => (row.show_on_home ? <Tag color="blue">Блок</Tag> : <Tag>—</Tag>),
    },
    {
      title: 'SEO',
      key: 'seo',
      width: 90,
      render: (_, row) => (row.meta_title?.trim() || row.seo_html?.trim() ? <Tag color="blue">Есть</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 140,
      render: (_, row) => (
        <Space size={4} wrap>
          {row.is_active ? <Tag color="green">Активен</Tag> : <Tag>Выключен</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыт</Tag> : null}
          {row.noindex ? <Tag>noindex</Tag> : null}
        </Space>
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 160,
      render: (_, row) => (
        <Space>
          <Button size="small" onClick={() => openEdit(row)}>Изменить</Button>
          <Popconfirm title="Удалить?" onConfirm={() => remove(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div>
      <div className="admin-toolbar">
        <span>{label} — URL: /{urlPrefix}/{'{slug}'}/</span>
        <Button type="primary" onClick={openCreate}>Добавить</Button>
      </div>
      <Table rowKey="id" loading={loading} columns={columns} dataSource={items} pagination={{ pageSize: 20 }} />

      <Modal
        title={editing ? `Редактировать: ${label}` : `Новый: ${label}`}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Form form={form} layout="vertical" onFinish={save}>
          <Form.Item label={type === 'years' ? 'Год' : 'Название'} name="name" rules={[{ required: true }]}>
            <Input placeholder={type === 'years' ? '1962' : undefined} />
          </Form.Item>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={type === 'years'
              ? 'Числовой год (YYYY). URL: /year/{slug}/'
              : (editing ? 'Можно изменить. URL: /' + urlPrefix + '/{slug}/' : 'Если пусто — из названия')}
          >
            <Input placeholder={type === 'years' ? '1962' : 'drama'} />
          </Form.Item>
          {type === 'people' ? (
            <>
              <Form.Item label="Фото URL" name="photo_url" extra="Внешний URL будет скачан на сервер при сохранении">
                <Input placeholder="/storage/posters/... или https://..." />
              </Form.Item>
              {photoUrl ? (
                <img
                  src={resolveMediaUrl(photoUrl)}
                  alt=""
                  style={{ width: 80, height: 106, objectFit: 'cover', borderRadius: 6, marginBottom: 12, display: 'block' }}
                />
              ) : null}
              <Upload
                beforeUpload={async (file) => {
                  if (!editing?.id) {
                    message.warning('Сначала сохраните актёра, затем загрузите фото')
                    return false
                  }
                  const fd = new FormData()
                  fd.append('photo', file)
                  try {
                    const res = await apiUpload<{ photo_url: string }>(`/api/admin/taxonomies/people/${editing.id}/photo`, fd)
                    form.setFieldValue('photo_url', res.photo_url)
                    message.success('Фото загружено')
                    await load()
                  } catch (e) {
                    message.error(String((e as Error).message))
                  }
                  return false
                }}
                showUploadList={false}
                accept="image/*"
              >
                <Button style={{ marginBottom: 12 }} disabled={!editing?.id}>
                  Загрузить фото
                </Button>
              </Upload>
            </>
          ) : null}

          <Space wrap style={{ marginBottom: 12 }}>
            <Button icon={<CopyOutlined />} onClick={() => void openPromptModal()}>
              Промпт для ИИ
            </Button>
            <Button icon={<ImportOutlined />} onClick={openImportModal}>
              Импорт из ИИ
            </Button>
            <Button icon={<EditOutlined />} onClick={() => void openTemplateModal()}>
              Шаблон промпта
            </Button>
          </Space>

          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — из названия">
            <Input />
          </Form.Item>
          <Form.Item label="Meta description" name="meta_description">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="SEO-блок (HTML)" name="seo_html">
            <TemplateCodeEditor filePath="taxonomy-seo.html" isDark={isDark} height="220px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            HTML выводится внизу страницы справочника.
          </Typography.Paragraph>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Typography.Title level={5} style={{ marginTop: 8 }}>Блок на главной</Typography.Title>
          <Form.Item label="Показывать на главной" name="show_on_home" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item noStyle shouldUpdate={(prev, cur) => prev.show_on_home !== cur.show_on_home}>
            {({ getFieldValue }) =>
              getFieldValue('show_on_home') ? (
                <>
                  <Form.Item label="Заголовок секции" name="home_title" extra="Если пусто — название справочника">
                    <Input placeholder={type === 'years' ? 'Сериалы 2024' : 'Драмы'} />
                  </Form.Item>
                  <Form.Item label="Карточек в блоке" name="home_item_limit">
                    <InputNumber min={1} max={60} style={{ width: '100%' }} />
                  </Form.Item>
                  <Form.Item label="Сортировка по умолчанию" name="home_default_sort">
                    <Select options={HOME_SORT_OPTIONS} />
                  </Form.Item>
                  <Form.Item label="Вкладки AJAX" name="home_show_tabs" valuePropName="checked">
                    <Switch />
                  </Form.Item>
                </>
              ) : null
            }
          </Form.Item>
          <Form.Item label="Активен" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Скрыть страницу" name="is_hidden" valuePropName="checked" extra="Страница недоступна посетителям (404)">
            <Switch />
          </Form.Item>
          <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="Добавляет meta robots noindex">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Промпт для ИИ — SEO справочника"
        open={promptModalOpen}
        onCancel={() => setPromptModalOpen(false)}
        footer={[
          <Button key="template" onClick={() => void openTemplateModal()}>Шаблон</Button>,
          <Button key="copy" type="primary" icon={<CopyOutlined />} onClick={() => void copyPrompt()} disabled={!promptText.trim()}>
            Скопировать
          </Button>,
          <Button key="close" onClick={() => setPromptModalOpen(false)}>Закрыть</Button>,
        ]}
        width={820}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          Скопируйте промпт в ChatGPT/Claude, затем вставьте JSON-ответ через «Импорт из ИИ».
        </Typography.Paragraph>
        {promptLoading ? (
          <Typography.Text type="secondary">Загрузка…</Typography.Text>
        ) : (
          <Input.TextArea
            value={promptText}
            readOnly
            rows={18}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Шаблон промпта для SEO справочников"
        open={templateModalOpen}
        onCancel={() => setTemplateModalOpen(false)}
        onOk={() => void saveTemplate()}
        okText="Сохранить"
        cancelText="Отмена"
        confirmLoading={templateSaving}
        width={820}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          Плейсхолдеры: <Typography.Text code>{'{name}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{type}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{type_label}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{slug}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{url}'}</Typography.Text>.
          Также редактируется в Настройки → SEO → Промпты для ИИ.
        </Typography.Paragraph>
        <Space style={{ marginBottom: 12 }}>
          <Button
            disabled={templateLoading}
            onClick={() => setTemplateText(DEFAULT_TAXONOMY_SEO_AI_PROMPT)}
          >
            Сбросить к умолчанию
          </Button>
        </Space>
        {templateLoading ? (
          <Typography.Text type="secondary">Загрузка…</Typography.Text>
        ) : (
          <Input.TextArea
            value={templateText}
            onChange={(e) => setTemplateText(e.target.value)}
            rows={18}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Импорт SEO из ИИ"
        open={importModalOpen}
        onCancel={() => setImportModalOpen(false)}
        width={820}
        footer={(
          <Space>
            <Button onClick={runImportPreview}>Проверить</Button>
            <Button type="primary" onClick={applyImport} disabled={!importPreview}>
              Заполнить поля
            </Button>
            <Button onClick={() => setImportModalOpen(false)}>Отмена</Button>
          </Space>
        )}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Typography.Paragraph type="secondary">
          Вставьте JSON-ответ от ИИ (можно с markdown-блоком ```json). Сначала нажмите «Проверить», затем «Заполнить поля».
        </Typography.Paragraph>
        <Input.TextArea
          value={importText}
          onChange={(e) => {
            setImportText(e.target.value)
            setImportPreview(null)
            setImportError('')
          }}
          rows={10}
          placeholder={'{\n  "meta_title": "...",\n  "meta_description": "...",\n  "seo_html": "<p>...</p>"\n}'}
          style={{ fontFamily: 'monospace', fontSize: 12, marginBottom: 16 }}
        />
        {importError ? (
          <Typography.Paragraph type="danger" style={{ marginBottom: 12 }}>
            {importError}
          </Typography.Paragraph>
        ) : null}
        {importPreview ? (
          <Space direction="vertical" size={12} style={{ width: '100%' }}>
            <div>
              <Typography.Text strong>Meta title</Typography.Text>
              <Typography.Paragraph style={{ marginBottom: 0 }}>
                {importPreview.meta_title || <Typography.Text type="secondary">пусто</Typography.Text>}
              </Typography.Paragraph>
            </div>
            <div>
              <Typography.Text strong>Meta description</Typography.Text>
              <Typography.Paragraph style={{ marginBottom: 0 }}>
                {importPreview.meta_description || <Typography.Text type="secondary">пусто</Typography.Text>}
              </Typography.Paragraph>
            </div>
            <div>
              <Typography.Text strong>SEO-блок (HTML)</Typography.Text>
              <Input.TextArea
                value={importPreview.seo_html}
                readOnly
                rows={6}
                style={{ fontFamily: 'monospace', fontSize: 12, marginTop: 4 }}
              />
            </div>
          </Space>
        ) : null}
      </Modal>
    </div>
  )
}

export default function TaxonomyPage() {
  return (
    <div className="admin-page-card">
      <Tabs
        items={TYPES.map((t) => ({
          key: t.key,
          label: t.label,
          children: <TaxonomyTab type={t.key} label={t.label} urlPrefix={t.urlPrefix} />,
        }))}
      />
    </div>
  )
}
