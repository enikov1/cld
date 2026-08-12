import { CopyOutlined, EditOutlined } from '@ant-design/icons'
import { Button, Input, Modal, Space, Typography, message } from 'antd'
import { useState } from 'react'
import { api } from '../api/client'
import { useBusyFavicon, useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { loadSeoPromptTemplate } from '../utils/entitySeoAiPrompt'
import {
  fillCollectionImageAiPrompt,
  type CollectionImageAiPromptVars,
} from '../utils/collectionImageAiPrompt'

type CollectionImageAiControlsProps = {
  settingKey: string
  defaultTemplate: string
  label: string
  aspectLabel: string
  buildVars: () => CollectionImageAiPromptVars | null
  /** Текст кнопки открытия промпта (по умолчанию «Промпт {aspectLabel}»). */
  buttonLabel?: string
  /** Текст кнопки шаблона (по умолчанию «Шаблон»). */
  templateButtonLabel?: string
  /** Подсказка в модалке промпта. */
  helpText?: string
}

export default function CollectionImageAiControls({
  settingKey,
  defaultTemplate,
  label,
  aspectLabel,
  buildVars,
  buttonLabel,
  templateButtonLabel = 'Шаблон',
  helpText = 'Скопируйте промпт в ChatGPT / Claude / генератор картинок, сохраните изображение и загрузите его кнопкой выше.',
}: CollectionImageAiControlsProps) {
  const [promptModalOpen, setPromptModalOpen] = useState(false)
  const [promptLoading, setPromptLoading] = useState(false)
  const [promptText, setPromptText] = useState('')

  const [templateModalOpen, setTemplateModalOpen] = useState(false)
  const [templateLoading, setTemplateLoading] = useState(false)
  const [templateSaving, setTemplateSaving] = useState(false)
  const [templateText, setTemplateText] = useState('')

  useDocumentTitle(
    templateModalOpen
      ? `Шаблон промпта — ${label}`
      : promptModalOpen
        ? `Промпт для ИИ — ${label}`
        : null,
  )
  useBusyFavicon(promptLoading || templateLoading || templateSaving)

  async function openPromptModal() {
    const vars = buildVars()
    if (!vars?.name) {
      message.warning('Сначала укажите название подборки')
      return
    }

    setPromptModalOpen(true)
    setPromptLoading(true)
    setPromptText('')
    try {
      const template = await loadSeoPromptTemplate(api, settingKey, defaultTemplate)
      setPromptText(fillCollectionImageAiPrompt(template, vars))
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
      setTemplateText(await loadSeoPromptTemplate(api, settingKey, defaultTemplate))
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
          settings: [{ key: settingKey, value: templateText }],
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

  return (
    <>
      <Button icon={<CopyOutlined />} onClick={() => void openPromptModal()}>
        {buttonLabel ?? `Промпт ${aspectLabel}`}
      </Button>
      <Button icon={<EditOutlined />} onClick={() => void openTemplateModal()}>
        {templateButtonLabel}
      </Button>

      <Modal
        title={`Промпт для ИИ — ${label}`}
        open={promptModalOpen}
        onCancel={() => setPromptModalOpen(false)}
        footer={[
          <Button key="template" onClick={() => void openTemplateModal()}>{templateButtonLabel}</Button>,
          <Button key="copy" type="primary" icon={<CopyOutlined />} onClick={() => void copyPrompt()} disabled={!promptText.trim()}>
            Скопировать
          </Button>,
          <Button key="close" onClick={() => setPromptModalOpen(false)}>Закрыть</Button>,
        ]}
        width={820}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">
          {helpText}
        </Typography.Paragraph>
        {promptLoading ? (
          <Typography.Text type="secondary">Загрузка…</Typography.Text>
        ) : (
          <Input.TextArea
            value={promptText}
            readOnly
            rows={16}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>

      <Modal
        title={`Шаблон промпта — ${label}`}
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
          <Typography.Text code>{'{slug}'}</Typography.Text>,{' '}
          <Typography.Text code>{'{url}'}</Typography.Text>.
          Также редактируется в Настройки → SEO → Промпты для ИИ.
        </Typography.Paragraph>
        <Space style={{ marginBottom: 12 }}>
          <Button
            disabled={templateLoading}
            onClick={() => setTemplateText(defaultTemplate)}
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
            rows={16}
            style={{ fontFamily: 'monospace', fontSize: 12 }}
          />
        )}
      </Modal>
    </>
  )
}
