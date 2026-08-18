import { CopyOutlined, EditOutlined, ImportOutlined } from '@ant-design/icons'
import { Alert, Button, Input, Modal, Space, Typography, message } from 'antd'
import type { FormInstance } from 'antd/es/form'
import { useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { loadSeoPromptTemplate } from '../utils/entitySeoAiPrompt'
import {
  DEFAULT_SERIES_SEO_AI_PROMPT,
  SERIES_SEO_AI_PROMPT_KEY,
  parseSeriesSeoAiResult,
  type SeriesSeoAiResult,
} from '../utils/seriesSeoAiPrompt'

type SeriesSeoAiControlsProps = {
  form: FormInstance
  seriesKey: string | null
}

export default function SeriesSeoAiControls({ form, seriesKey }: SeriesSeoAiControlsProps) {
  const [promptModalOpen, setPromptModalOpen] = useState(false)
  const [promptLoading, setPromptLoading] = useState(false)
  const [promptText, setPromptText] = useState('')
  const [promptWarnings, setPromptWarnings] = useState<string[]>([])

  const [templateModalOpen, setTemplateModalOpen] = useState(false)
  const [templateLoading, setTemplateLoading] = useState(false)
  const [templateSaving, setTemplateSaving] = useState(false)
  const [templateText, setTemplateText] = useState('')

  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importPreview, setImportPreview] = useState<SeriesSeoAiResult | null>(null)
  const [importError, setImportError] = useState('')

  useDocumentTitle(
    importModalOpen
      ? 'Импорт SEO-статьи сериала из ИИ'
      : templateModalOpen
        ? 'Шаблон промпта для SEO-статьи сериала'
        : promptModalOpen
          ? 'Промпт для ИИ — SEO-статья сериала'
          : null,
  )
  useBusyFavicon(promptLoading || templateLoading || templateSaving)

  async function openPromptModal() {
    if (!seriesKey) {
      message.warning('Сначала сохраните сериал')
      return
    }

    setPromptModalOpen(true)
    setPromptLoading(true)
    setPromptText('')
    setPromptWarnings([])
    try {
      const data = await api<{ prompt?: string; warnings?: string[] }>(
        `/api/admin/series/${encodeURIComponent(seriesKey)}/seo-ai-prompt`,
      )
      setPromptText(data.prompt?.trim() ?? '')
      setPromptWarnings(data.warnings ?? [])
      if (!data.prompt?.trim()) {
        message.warning('Промпт пустой')
      }
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
      setTemplateText(await loadSeoPromptTemplate(api, SERIES_SEO_AI_PROMPT_KEY, DEFAULT_SERIES_SEO_AI_PROMPT))
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
          settings: [{ key: SERIES_SEO_AI_PROMPT_KEY, value: templateText }],
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

    const parsed = parseSeriesSeoAiResult(payload)
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

  return (
    <>
      <Space wrap style={{ marginBottom: 12 }}>
        <Button icon={<CopyOutlined />} onClick={() => void openPromptModal()} disabled={!seriesKey}>
          Промпт для ИИ
        </Button>
        <Button icon={<ImportOutlined />} onClick={openImportModal}>
          Импорт из ИИ
        </Button>
        <Button icon={<EditOutlined />} onClick={() => void openTemplateModal()}>
          Шаблон промпта
        </Button>
      </Space>

      <Modal
        title="Промпт для ИИ — SEO-статья сериала"
        open={promptModalOpen}
        onCancel={() => setPromptModalOpen(false)}
        footer={[
          <Button key="template" onClick={() => void openTemplateModal()}>Шаблон</Button>,
          <Button key="copy" type="primary" icon={<CopyOutlined />} onClick={() => void copyPrompt()} disabled={!promptText.trim()}>
            Скопировать
          </Button>,
          <Button key="close" onClick={() => setPromptModalOpen(false)}>Закрыть</Button>,
        ]}
        width={900}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Typography.Paragraph type="secondary">
          Промпт собирается на сервере: жанры, страны, актёры, озвучки, график серий и описания эпизодов из TMDB.
          Скопируйте в ChatGPT/Claude, затем вставьте JSON-ответ через «Импорт из ИИ».
        </Typography.Paragraph>
        {promptWarnings.length > 0 ? (
          <Alert
            type="warning"
            showIcon
            style={{ marginBottom: 12 }}
            message="Замечания при сборе данных"
            description={(
              <ul style={{ margin: 0, paddingLeft: 20 }}>
                {promptWarnings.map((warning) => (
                  <li key={warning}>{warning}</li>
                ))}
              </ul>
            )}
          />
        ) : null}
        {promptLoading ? (
          <Typography.Text type="secondary">Сбор данных о сериале…</Typography.Text>
        ) : (
          <Input.TextArea
            value={promptText}
            readOnly
            rows={22}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Шаблон промпта для SEO-статьи сериала"
        open={templateModalOpen}
        onCancel={() => setTemplateModalOpen(false)}
        onOk={() => void saveTemplate()}
        okText="Сохранить"
        cancelText="Отмена"
        confirmLoading={templateSaving}
        width={900}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          Плейсхолдеры: <Typography.Text code>{'{title}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{slug}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{url}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{series_context}'}</Typography.Text> (контекст подставляется на сервере).
          Также редактируется в Настройки → SEO → Промпты для ИИ.
        </Typography.Paragraph>
        <Space style={{ marginBottom: 12 }}>
          <Button
            disabled={templateLoading}
            onClick={() => setTemplateText(DEFAULT_SERIES_SEO_AI_PROMPT)}
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
            rows={20}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title="Импорт SEO-статьи сериала из ИИ"
        open={importModalOpen}
        onCancel={() => setImportModalOpen(false)}
        width={900}
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
          placeholder={'{\n  "meta_title": "...",\n  "meta_description": "...",\n  "seo_html": "<h2>...</h2><p>...</p>"\n}'}
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
                rows={12}
                style={{ fontFamily: 'monospace', fontSize: 12, marginTop: 4 }}
              />
            </div>
          </Space>
        ) : null}
      </Modal>
    </>
  )
}
