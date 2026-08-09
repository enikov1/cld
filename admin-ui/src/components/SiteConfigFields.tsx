import { Form, Input, InputNumber, Select, Switch, Typography } from 'antd'
import type { SiteConfigField } from '../types/siteConfig'

type SiteConfigFieldsProps = {
  fields: SiteConfigField[]
  prefix?: string
}

export default function SiteConfigFields({ fields, prefix }: SiteConfigFieldsProps) {
  if (!fields.length) {
    return <Typography.Text type="secondary">Нет полей для этого раздела.</Typography.Text>
  }

  return (
    <>
      {fields.map((field) => {
        const name = prefix ? `${prefix}.${field.key}` : field.key

        if (field.type === 'bool') {
          return (
            <div key={field.key} data-settings-field={field.key} id={`settings-field-${field.key}`}>
              <Form.Item
                label={field.label}
                name={name}
                valuePropName="checked"
                extra={field.description ?? undefined}
              >
                <Switch checkedChildren="Вкл" unCheckedChildren="Выкл" />
              </Form.Item>
            </div>
          )
        }

        if (field.type === 'int') {
          return (
            <div key={field.key} data-settings-field={field.key} id={`settings-field-${field.key}`}>
              <Form.Item
                label={field.label}
                name={name}
                extra={field.description ?? undefined}
              >
                <InputNumber min={field.min ?? undefined} max={field.max ?? undefined} style={{ width: 160 }} />
              </Form.Item>
            </div>
          )
        }

        if (field.type === 'enum' && field.options) {
          const selectOptions = Object.entries(field.options).map(([value, label]) => ({ value, label }))
          return (
            <div key={field.key} data-settings-field={field.key} id={`settings-field-${field.key}`}>
              <Form.Item
                label={field.label}
                name={name}
                extra={field.description ?? undefined}
              >
                <Select options={selectOptions} style={{ maxWidth: 480 }} />
              </Form.Item>
            </div>
          )
        }

        if (field.type === 'html') {
          const isAiPrompt = field.key.includes('ai_prompt')
          return (
            <div key={field.key} data-settings-field={field.key} id={`settings-field-${field.key}`}>
              <Form.Item
                label={field.label}
                name={name}
                extra={field.description ?? undefined}
              >
                <Input.TextArea
                  rows={isAiPrompt ? 16 : 10}
                  spellCheck={false}
                  className="settings-counters-editor"
                  placeholder={isAiPrompt ? 'Шаблон промпта…' : '<script>...</script>'}
                  style={isAiPrompt ? { fontFamily: 'monospace', fontSize: 12 } : undefined}
                />
              </Form.Item>
            </div>
          )
        }

        return (
          <div key={field.key} data-settings-field={field.key} id={`settings-field-${field.key}`}>
            <Form.Item
              label={field.label}
              name={name}
              extra={field.description ?? undefined}
            >
              <Input.TextArea
                rows={
                  field.key.includes('verification')
                    ? 1
                    : field.key.includes('msg_') || field.key.includes('ui_')
                      ? 2
                      : 1
                }
              />
            </Form.Item>
          </div>
        )
      })}
    </>
  )
}
