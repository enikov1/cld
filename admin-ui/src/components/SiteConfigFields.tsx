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
            <Form.Item
              key={field.key}
              label={field.label}
              name={name}
              valuePropName="checked"
              extra={field.description ?? undefined}
            >
              <Switch checkedChildren="Вкл" unCheckedChildren="Выкл" />
            </Form.Item>
          )
        }

        if (field.type === 'int') {
          return (
            <Form.Item
              key={field.key}
              label={field.label}
              name={name}
              extra={field.description ?? undefined}
            >
              <InputNumber min={field.min ?? undefined} max={field.max ?? undefined} style={{ width: 160 }} />
            </Form.Item>
          )
        }

        if (field.type === 'enum' && field.options) {
          const selectOptions = Object.entries(field.options).map(([value, label]) => ({ value, label }))
          return (
            <Form.Item
              key={field.key}
              label={field.label}
              name={name}
              extra={field.description ?? undefined}
            >
              <Select options={selectOptions} style={{ maxWidth: 480 }} />
            </Form.Item>
          )
        }

        if (field.type === 'html') {
          return (
            <Form.Item
              key={field.key}
              label={field.label}
              name={name}
              extra={field.description ?? undefined}
            >
              <Input.TextArea
                rows={10}
                spellCheck={false}
                className="settings-counters-editor"
                placeholder="<script>...</script>"
              />
            </Form.Item>
          )
        }

        return (
          <Form.Item
            key={field.key}
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
        )
      })}
    </>
  )
}
