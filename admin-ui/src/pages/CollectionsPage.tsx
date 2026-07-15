import { Button, Col, Empty, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Switch, Table, Tag, Typography, Upload, message } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { apiUpload } from '../api/upload'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import { useAdminTheme } from '../theme/useAdminTheme'
import type { CollectionItem, CollectionSeriesItem, SeriesItem, StudioItem } from '../types'

export default function CollectionsPage() {
  const { isDark } = useAdminTheme()
  const [collections, setCollections] = useState<CollectionItem[]>([])
  const [studios, setStudios] = useState<StudioItem[]>([])
  const [series, setSeries] = useState<SeriesItem[]>([])
  const [activeSlug, setActiveSlug] = useState<string>('')
  const [items, setItems] = useState<CollectionSeriesItem[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CollectionItem | null>(null)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [form] = Form.useForm()
  const [addForm] = Form.useForm()

  const seriesOptions = useMemo(
    () => series.map((s) => ({ value: s.kp_id, label: `${s.title} (${s.kp_id})` })),
    [series],
  )

  const activeCollection = useMemo(
    () => collections.find((c) => c.slug === activeSlug) ?? null,
    [collections, activeSlug],
  )

  const loadStudios = useCallback(async () => {
    const data = await api<{ items: StudioItem[] }>('/api/admin/studios')
    setStudios(data.items)
  }, [])

  const studioOptions = useMemo(
    () => studios.map((s) => ({ value: s.id, label: s.title })),
    [studios],
  )

  const loadCollections = useCallback(async () => {
    const data = await api<{ items: CollectionItem[] }>('/api/admin/collections')
    setCollections(data.items)
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
      const data = await api<{ items: CollectionSeriesItem[] }>(`/api/admin/collections/${slug}/items`)
      setItems(data.items)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    Promise.all([loadCollections(), loadSeries(), loadStudios()]).catch((e) => message.error(String((e as Error).message)))
  }, [loadCollections, loadSeries, loadStudios])

  useEffect(() => {
    loadItems(activeSlug)
  }, [activeSlug, loadItems])

  function openCreate() {
    setEditing(null)
    form.resetFields()
    form.setFieldsValue({ is_active: true, is_pinned: false, is_hidden: false, noindex: false, sort_order: 0, seo_html: '' })
    setModalOpen(true)
  }

  function openEdit(row: CollectionItem) {
    setEditing(row)
    form.setFieldsValue({
      ...row,
      seo_html: row.seo_html ?? '',
    })
    setModalOpen(true)
  }

  async function saveCollection(values: Record<string, unknown>) {
    try {
      const payload = editing ? { ...values, id: editing.id } : values
      const res = await api<{ item: CollectionItem }>('/api/admin/collections/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editing ? 'Подборка обновлена' : 'Подборка создана')
      setModalOpen(false)
      setActiveSlug(res.item.slug)
      await loadCollections()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function addSeries(values: { kp_ids: string[] }) {
    if (!activeSlug) return
    try {
      await api(`/api/admin/collections/${activeSlug}/items`, {
        method: 'POST',
        body: JSON.stringify({
          items: values.kp_ids.map((kp_id, i) => ({ kp_id, rank_order: i + 1 })),
        }),
      })
      message.success('Сериалы добавлены в подборку')
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
      await api(`/api/admin/collections/${activeSlug}/items/${kpId}`, { method: 'DELETE' })
      message.success('Сериал удалён из подборки')
      await loadItems(activeSlug)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const collectionColumns: ColumnsType<CollectionItem> = [
    { title: 'Slug (URL)', dataIndex: 'slug', key: 'slug', width: 160, render: (slug) => `/collections/${slug}/` },
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

  const itemColumns: ColumnsType<CollectionSeriesItem> = [
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
          <Popconfirm title="Убрать из подборки?" onConfirm={() => removeItem(String(r.series!.kp_id))}>
            <Button size="small" danger type="link">Убрать</Button>
          </Popconfirm>
        ) : null,
    },
  ]

  const coverSlug = editing?.slug || form.getFieldValue('slug')

  return (
    <Row gutter={[16, 16]}>
      <Col xs={24} xl={10}>
        <div className="admin-page-card">
          <div className="admin-toolbar">
            <span>Подборки — URL: /collections/{'{slug}'}/</span>
            <Button type="primary" onClick={openCreate}>Создать</Button>
          </div>
          <Table
            rowKey="id"
            columns={collectionColumns}
            dataSource={collections}
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
            <Empty description="Выберите подборку слева" />
          ) : (
            <>
              <div className="admin-toolbar">
                <div>
                  <strong>{activeCollection?.title}</strong>
                  <div className="admin-empty-hint">/collections/{activeSlug}/</div>
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
        title={editing ? 'Редактировать подборку' : 'Новая подборка'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        onOk={() => form.submit()}
        okText="Сохранить"
        cancelText="Отмена"
        width={920}
        destroyOnHidden
        styles={{ body: { maxHeight: '75vh', overflowY: 'auto' } }}
      >
        <Form form={form} layout="vertical" onFinish={saveCollection}>
          <Form.Item
            label="Slug (URL)"
            name="slug"
            extra={editing ? 'Slug нельзя изменить после создания' : 'Если пусто — будет создан автоматически из названия'}
          >
            <Input placeholder="novinki-2026" disabled={!!editing} />
          </Form.Item>
          <Form.Item label="Название" name="title" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Студия" name="studio_id" extra="Опционально — привязка подборки к студии">
            <Select allowClear options={studioOptions} placeholder="Не выбрана" />
          </Form.Item>
          <Form.Item label="Meta title" name="meta_title" extra="Если пусто — «{название} — подборка сериалов»">
            <Input />
          </Form.Item>
          <Form.Item label="Описание" name="description">
            <Input.TextArea rows={3} />
          </Form.Item>
          <Form.Item label="Meta description" name="meta_description" extra="Если пусто — из описания или шаблона">
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item label="SEO-блок (HTML)" name="seo_html" extra="Выводится внизу страницы подборки">
            <TemplateCodeEditor filePath="collection-seo.html" isDark={isDark} height="220px" />
          </Form.Item>
          <Typography.Paragraph type="secondary" style={{ marginTop: -8, marginBottom: 16 }}>
            HTML-текст для SEO внизу страницы подборки.
          </Typography.Paragraph>
          <Form.Item label="Обложка (URL)" name="cover_url">
            <Input />
          </Form.Item>
          <Upload
            beforeUpload={async (file) => {
              const slug = coverSlug
              if (!slug) {
                message.warning('Сначала укажите slug или сохраните подборку')
                return false
              }
              const fd = new FormData()
              fd.append('cover', file)
              try {
                const res = await apiUpload<{ cover_url: string }>(`/api/admin/collections/${slug}/cover`, fd)
                form.setFieldValue('cover_url', res.cover_url)
                message.success('Обложка загружена')
              } catch (e) {
                message.error(String((e as Error).message))
              }
              return false
            }}
            showUploadList={false}
            accept="image/*"
          >
            <Button style={{ marginBottom: 12 }}>Загрузить обложку</Button>
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
        title="Добавить сериалы в подборку"
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
