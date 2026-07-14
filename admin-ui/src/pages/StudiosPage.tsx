import { Button, Col, Empty, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Switch, Table, Tag, Tooltip, Typography, Upload, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { apiUpload } from '../api/upload'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { SeriesItem, StudioItem, StudioSeriesItem } from '../types'
import { resolveMediaUrl } from '../utils/mediaUrl'

function StudioLogoPreview({
  url,
  size = 40,
  large = 120,
}: {
  url?: string | null
  size?: number
  large?: number
}) {
  if (!url?.trim()) {
    return <span style={{ color: '#999' }}>—</span>
  }

  const src = resolveMediaUrl(url)

  return (
    <Tooltip
      title={(
        <img
          src={src}
          alt=""
          style={{
            width: large,
            maxHeight: large,
            objectFit: 'contain',
            borderRadius: 6,
            display: 'block',
            background: '#fff',
            padding: 8,
          }}
        />
      )}
    >
      <img
        src={src}
        alt=""
        style={{
          width: size,
          height: size,
          objectFit: 'contain',
          borderRadius: 4,
          cursor: 'pointer',
          background: 'rgba(0,0,0,0.04)',
          padding: 4,
          display: 'block',
        }}
      />
    </Tooltip>
  )
}

export default function StudiosPage() {
  const { isDark } = useAdminTheme()
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [series, setSeries] = useState<SeriesItem[]>([])
  const [activeSlug, setActiveSlug] = useState<string>('')
  const [items, setItems] = useState<StudioSeriesItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<StudioItem | null>(null)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [form] = Form.useForm()
  const [addForm] = Form.useForm()
  const logoUrl = Form.useWatch('logo_url', form)
  const seriesOptions = useMemo(
    () => series.map((s) => ({ value: s.kp_id, label: `${s.title} (${s.kp_id})` })),
    [series],
  )

  const activeStudio = useMemo(
    () => studios.find((s) => s.slug === activeSlug) ?? null,
    [studios, activeSlug],
  )

  const loadStudios = useCallback(async () => {
    const data = await api<{ items: StudioItem[] }>('/api/admin/studios')
    setStudios(data.items)
  }, [])

  const loadSeries = useCallback(async () => {
    const data = await api<{ items: SeriesItem[] }>('/api/admin/series')
    setSeries(data.items)
  }, [])

  const loadItems = useCallback(async (slug: string) => {
    if (!slug) {
      setItems([])
      return
    }
    setLoading(true)
    try {
      const data = await api<{ items: StudioSeriesItem[] }>(`/api/admin/studios/${slug}/items`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    Promise.all([loadStudios(), loadSeries()]).catch((e) => message.error(String((e as Error).message)))
  }, [loadStudios, loadSeries])

  useEffect(() => {
    loadItems(activeSlug)
  }, [activeSlug, loadItems])

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({ is_active: true, is_pinned: false, is_hidden: false, noindex: false, sort_order: 0, seo_html: '' })
    setModalOpen(true)
  }

  function openEdit(row: StudioItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
    })
    setModalOpen(true)
  }

  async function saveStudio(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      const res = await api<{ item: StudioItem }>('/api/admin/studios/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Студия обновлена' : 'Студия создана')
      setModalOpen(false)
      setActiveSlug(res.item.slug)
      await loadStudios()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function addSeries(values: { kp_ids: string[] }) {
    if (!activeSlug) return
    try {
      await api(`/api/admin/studios/${activeSlug}/items`, {
        method: 'POST',
        body: JSON.stringify({
          items: values.kp_ids.map((kp_id, i) => ({ kp_id, rank_order: i + 1 })),
        }),
      })
      message.success('Сериалы добавлены в студию')
      setAddModalOpen(false)
      addForm.resetFields()
      await loadItems(activeSlug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeItem(kpId: string) {
    if (!activeSlug) return
    try {
      await api(`/api/admin/studios/${activeSlug}/items/${kpId}`, { method: 'DELETE' })
      message.success('Сериал удалён из студии')
      await loadItems(activeSlug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const studioColumns: ColumnsType<StudioItem> = [
    {
      title: 'Лого',
      key: 'logo',
      width: 72,
      render: (_, row) => <StudioLogoPreview url={row.logo_url} />,
    },
    { title: 'Slug (URL)', dataIndex: 'slug', key: 'slug', width: 160, render: (slug) => `/studios/${slug}/` },
    { title: 'Название', dataIndex: 'title', key: 'title' },
    { title: 'Порядок', dataIndex: 'sort_order', width: 80 },
    {
      title: 'SEO',
      key: 'seo',
      width: 80,
      render: (_, row) => (row.meta_title?.trim() || row.seo_html?.trim() ? <Tag color="blue">Есть</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: '',
      key: 'pin',
      width: 90,
      render: (_, r) => (r.is_pinned ? <Tag color="orange">Закреплена</Tag> : null),
    },
    {
      title: 'Статус',
      key: 'status',
      width: 150,
      render: (_, row) => (
        <>
          {row.is_active ? <Tag color="green">Активна</Tag> : <Tag>Выключена</Tag>}
          {row.is_hidden ? <Tag color="orange">Скрыта</Tag> : null}
          {row.noindex ? <Tag>noindex</Tag> : null}
        </>
      ),
    },
    {
      title: '',
      key: 'edit',
      width: 90,
      render: (_, row) => (
        <Button
          size="small"
          onClick={(e) => {
            e.stopPropagation()
            openEdit(row)
          }}
        >
          Изменить
        </Button>
      ),
    },
  ]
  const itemColumns: ColumnsType<StudioSeriesItem> = [
    { title: '#', dataIndex: 'rank_order', key: 'rank_order', width: 60 },
    { title: 'KP ID', key: 'kp_id', width: 100, render: (_, r) => r.series?.kp_id ?? '—' },
    { title: 'Название', key: 'title', render: (_, r) => r.series?.title ?? '—' },
    { title: 'Год', key: 'year', width: 80, render: (_, r) => r.series?.year ?? '—' },
    {
      title: '',
      key: 'actions',
      width: 100,
      render: (_, r) =>
        r.series?.kp_id ? (
          <Popconfirm title="Убрать из студии?" onConfirm={() => removeItem(r.series!.kp_id)}>
            <Button size="small" danger type="link">Убрать</Button>
          </Popconfirm>
        ) : null,
    },
  ]

  const logoSlug = editing?.slug || form.getFieldValue('slug')

  return (
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={10}>
        <div className="admin-page-card">
          <div className="admin-toolbar">
            <span>Студии — URL: /studios/{'{slug}'}/</span>
            <Button type="primary" onClick={openCreate}>Создать</Button>
          </div>
          <Table
            rowKey="id"
            columns={studioColumns}
            dataSource={studios}
            pagination={false}
            size="small"
            onRow={(row) => ({
              onClick: () => setActiveSlug(row.slug),
              style: { cursor: 'pointer', background: row.slug === activeSlug ? '#e6f4ff' : undefined },
            })}
          />
        </div>
      </Col>

      <Col xs={24} xl={14}>
        <div className="admin-page-card">
          {!activeSlug ? (
            <Empty description="Выберите студию слева" />
          ) : (
            <>
              <div className="admin-toolbar">
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                  {activeStudio?.logo_url ? (
                    <img
                      src={resolveMediaUrl(activeStudio.logo_url)}
                      alt=""
                      style={{
                        width: 48,
                        height: 48,
                        objectFit: 'contain',
                        borderRadius: 6,
                        background: 'rgba(0,0,0,0.04)',
                        padding: 4,
                      }}
                    />
                  ) : null}
                  <div>
                    <strong>{activeStudio?.title}</strong>
                    <div className="admin-empty-hint">/studios/{activeSlug}/</div>
                  </div>
                </div>
                <Button type="primary" onClick={() => setAddModalOpen(true)} disabled={!series.length}>
                  Добавить сериалы
                </Button>
              </div>
              <Table rowKey="id" loading={loading} columns={itemColumns} dataSource={items} pagination={false} size="small" />
            </>
          )}
        </div>
      </Col>

      <Modal
        title={editing ? 'Редактировать студию' : 'Новая студия'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Form form={form} layout="vertical" onFinish={saveStudio}>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={editing ? 'Slug нельзя изменить после создания' : 'Если пусто — будет создан автоматически из названия'}
          >
            <Input placeholder="hbo" disabled={!!editing} />
          </Form.Item>
          <Form.Item label="Название" name="title" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — «{название} — студия»">
            <Input />
          </Form.Item>
          <Form.Item label="Описание" name="description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item label="Meta description" name="meta_description" extra="Если пусто — из описания или шаблона">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="SEO-блок (HTML)" name="seo_html" extra="Выводится внизу страницы студии">
            <TemplateCodeEditor filePath="studio-seo.html" isDark={isDark} height="220px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            HTML-текст для SEO внизу страницы студии.
          </Typography.Paragraph>
          <Form.Item label="Логотип (URL)" name="logo_url">
            <Input placeholder="/storage/posters/... или https://..." />
          </Form.Item>
          {logoUrl ? (
            <img
              src={resolveMediaUrl(logoUrl)}
              alt="Превью логотипа"
              style={{
                maxWidth: 220,
                maxHeight: 80,
                objectFit: 'contain',
                borderRadius: 6,
                marginBottom: 12,
                display: 'block',
                background: 'rgba(0,0,0,0.04)',
                padding: 8,
              }}
            />
          ) : null}
          <Upload
            beforeUpload={async (file) => {
              const slug = logoSlug
              if (!slug) {
                message.warning('Сначала укажите slug или сохраните студию')
                return false
              }
              const fd = new FormData()
              fd.append('logo', file)
              try {
                const res = await apiUpload<{ logo_url: string; item?: StudioItem }>(`/api/admin/studios/${slug}/logo`, fd)
                form.setFieldValue('logo_url', res.logo_url)
                if (res.item) {
                  setEditing(res.item)
                }
                message.success('Логотип загружен')
                await loadStudios()
              } catch (e) {
                message.error(String((e as Error).message))
              }
              return false
            }}
            showUploadList={false}
            accept="image/*"
          >
            <Button style={{ marginBottom: 12 }}>Загрузить логотип</Button>
          </Upload>
          <Row gutter={16}>
            <Col span={6}>
              <Form.Item label="Порядок" name="sort_order"><InputNumber style={{ width: '100%' }} /></Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Закрепить" name="is_pinned" valuePropName="checked"><Switch /></Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Активна" name="is_active" valuePropName="checked"><Switch /></Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item label="Скрыть" name="is_hidden" valuePropName="checked"><Switch /></Form.Item>
            </Col>
          </Row>
          <Form.Item label="Запретить индексацию" name="noindex" valuePropName="checked" extra="meta robots noindex">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Добавить сериалы в студию"
        open={addModalOpen}
        onCancel={() => setAddModalOpen(false)}
        onOk={() => addForm.submit()}
        okText="Добавить"
        cancelText="Отмена"
        destroyOnHidden
      >
        <Form form={addForm} layout="vertical" onFinish={addSeries}>
          <Form.Item label="Сериалы" name="kp_ids" rules={[{ required: true, message: 'Выберите сериалы' }]}>
            <Select mode="multiple" options={seriesOptions} optionFilterProp="label" placeholder="Выберите один или несколько" />
          </Form.Item>
        </Form>
      </Modal>
    </Row>
  )
}
