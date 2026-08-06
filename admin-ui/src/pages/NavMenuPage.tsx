import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  PlusOutlined,
  SettingOutlined,
} from '@ant-design/icons'
import {
  Button,
  Drawer,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tabs,
  Tag,
  message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import type { TaxonomyOption, TaxonomyType } from '../types'

type NavLinkType = 'home' | 'taxonomy' | 'collections' | 'studios' | 'catalog' | 'coming_soon' | 'custom'
type NavSectionType = 'genres' | 'countries' | 'collections' | 'studios' | 'years' | 'custom'

type NavMegaLink = {
  id: number
  nav_mega_section_id: number
  label: string
  url: string
  sort_order: number
  is_active: boolean
}

type NavMegaSection = {
  id: number
  nav_item_id: number
  title: string
  source_type: NavSectionType
  item_limit: number
  css_class: string
  sort_order: number
  is_active: boolean
  links: NavMegaLink[]
}

type NavMegaButton = {
  id: number
  nav_item_id: number
  title: string
  subtitle?: string | null
  link_type: NavLinkType
  taxonomy_type?: TaxonomyType | null
  taxonomy_id?: number | null
  custom_url?: string | null
  sort_order: number
  is_active: boolean
}

type NavItem = {
  id: number
  title: string
  link_type: NavLinkType
  taxonomy_type?: TaxonomyType | null
  taxonomy_id?: number | null
  custom_url?: string | null
  url: string
  sort_order: number
  is_active: boolean
  show_desktop: boolean
  show_mobile: boolean
  has_mega: boolean
  mega_buttons: NavMegaButton[]
  mega_sections: NavMegaSection[]
}

const TAXONOMY_TYPE_OPTIONS: { value: TaxonomyType; label: string }[] = [
  { value: 'genres', label: 'Жанры' },
  { value: 'countries', label: 'Страны' },
  { value: 'people', label: 'Актёры' },
  { value: 'years', label: 'Годы' },
]

const LINK_TYPES: { value: NavLinkType; label: string }[] = [
  { value: 'home', label: 'Главная' },
  { value: 'taxonomy', label: 'Справочник' },
  { value: 'collections', label: 'Подборки' },
  { value: 'studios', label: 'Студии' },
  { value: 'catalog', label: 'Каталог' },
  { value: 'coming_soon', label: 'Скоро' },
  { value: 'custom', label: 'Своя ссылка' },
]

const SECTION_TYPES: { value: NavSectionType; label: string }[] = [
  { value: 'genres', label: 'Жанры (авто)' },
  { value: 'countries', label: 'Страны (авто)' },
  { value: 'collections', label: 'Подборки (авто)' },
  { value: 'studios', label: 'Студии (авто)' },
  { value: 'years', label: 'Годы (авто)' },
  { value: 'custom', label: 'Свои ссылки' },
]

export default function NavMenuPage() {
  const [items, setItems] = useState<NavItem[]>([])
  const [taxonomyOptions, setTaxonomyOptions] = useState<Record<TaxonomyType, TaxonomyOption[]>>({
    genres: [],
    countries: [],
    people: [],
    years: [],
  })
  const [loading, setLoading] = useState(false)
  const [itemModalOpen, setItemModalOpen] = useState(false)
  const [editingItem, setEditingItem] = useState<NavItem | null>(null)
  const [megaDrawerOpen, setMegaDrawerOpen] = useState(false)
  const [megaItem, setMegaItem] = useState<NavItem | null>(null)
  const [buttonModalOpen, setButtonModalOpen] = useState(false)
  const [editingButton, setEditingButton] = useState<NavMegaButton | null>(null)
  const [sectionModalOpen, setSectionModalOpen] = useState(false)
  const [editingSection, setEditingSection] = useState<NavMegaSection | null>(null)
  const [linkModalOpen, setLinkModalOpen] = useState(false)
  const [editingLink, setEditingLink] = useState<NavMegaLink | null>(null)
  const [linkSectionId, setLinkSectionId] = useState<number | null>(null)
  const [itemForm] = Form.useForm()
  const [buttonForm] = Form.useForm()
  const [sectionForm] = Form.useForm()
  const [linkForm] = Form.useForm()

  const taxonomyItemOptions = useCallback(
    (type?: TaxonomyType | null) => {
      if (!type) return []
      return (taxonomyOptions[type] ?? []).map((item) => ({ value: item.id, label: item.name }))
    },
    [taxonomyOptions],
  )

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [navData, taxonomiesData] = await Promise.all([
        api<{ items: NavItem[] }>('/api/admin/nav'),
        api<Record<TaxonomyType, TaxonomyOption[]>>('/api/admin/taxonomies/options'),
      ])
      setItems(navData.items)
      setTaxonomyOptions(taxonomiesData)
    } catch (e) {
      message.error(String((e as Error).message))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  function openCreateItem() {
    setEditingItem(null)
    itemForm.resetFields()
    itemForm.setFieldsValue({
      is_active: true,
      show_desktop: true,
      show_mobile: true,
      has_mega: false,
      link_type: 'taxonomy',
      taxonomy_type: 'genres',
      sort_order: (items.length + 1) * 10,
    })
    setItemModalOpen(true)
  }

  function openEditItem(row: NavItem) {
    setEditingItem(row)
    itemForm.setFieldsValue(row)
    setItemModalOpen(true)
  }

  async function saveItem(values: Record<string, unknown>) {
    try {
      const payload = editingItem ? { ...values, id: editingItem.id } : values
      await api('/api/admin/nav/items/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editingItem ? 'Пункт обновлён' : 'Пункт добавлен')
      setItemModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeItem(id: number) {
    try {
      await api(`/api/admin/nav/items/${id}`, { method: 'DELETE' })
      message.success('Пункт удалён')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function moveItem(id: number, direction: -1 | 1) {
    const index = items.findIndex((i) => i.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= items.length) return

    const next = [...items]
    const tmp = next[index]
    next[index] = next[target]
    next[target] = tmp

    try {
      await api('/api/admin/nav/items/reorder', {
        method: 'POST',
        body: JSON.stringify({ ids: next.map((i) => i.id) }),
      })
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openMegaDrawer(row: NavItem) {
    setMegaItem(row)
    setMegaDrawerOpen(true)
  }

  function openCreateButton() {
    if (!megaItem) return
    setEditingButton(null)
    buttonForm.resetFields()
    buttonForm.setFieldsValue({
      nav_item_id: megaItem.id,
      is_active: true,
      link_type: 'taxonomy',
      taxonomy_type: 'genres',
      sort_order: (megaItem.mega_buttons.length + 1) * 10,
    })
    setButtonModalOpen(true)
  }

  function openEditButton(row: NavMegaButton) {
    setEditingButton(row)
    buttonForm.setFieldsValue(row)
    setButtonModalOpen(true)
  }

  async function saveButton(values: Record<string, unknown>) {
    try {
      const payload = editingButton ? { ...values, id: editingButton.id } : values
      await api('/api/admin/nav/mega-buttons/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editingButton ? 'Кнопка обновлена' : 'Кнопка добавлена')
      setButtonModalOpen(false)
      await load()
      setMegaItem((prev) => items.find((i) => i.id === prev?.id) ?? prev)
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeButton(id: number) {
    try {
      await api(`/api/admin/nav/mega-buttons/${id}`, { method: 'DELETE' })
      message.success('Кнопка удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function moveButton(id: number, direction: -1 | 1) {
    if (!megaItem) return
    const buttons = [...megaItem.mega_buttons]
    const index = buttons.findIndex((b) => b.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= buttons.length) return
    ;[buttons[index], buttons[target]] = [buttons[target], buttons[index]]
    try {
      await api('/api/admin/nav/mega-buttons/reorder', {
        method: 'POST',
        body: JSON.stringify({ ids: buttons.map((b) => b.id) }),
      })
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openCreateSection() {
    if (!megaItem) return
    setEditingSection(null)
    sectionForm.resetFields()
    sectionForm.setFieldsValue({
      nav_item_id: megaItem.id,
      is_active: true,
      source_type: 'custom',
      item_limit: 14,
      css_class: 'wide',
      sort_order: (megaItem.mega_sections.length + 1) * 10,
    })
    setSectionModalOpen(true)
  }

  function openEditSection(row: NavMegaSection) {
    setEditingSection(row)
    sectionForm.setFieldsValue(row)
    setSectionModalOpen(true)
  }

  async function saveSection(values: Record<string, unknown>) {
    try {
      const payload = editingSection ? { ...values, id: editingSection.id } : values
      await api('/api/admin/nav/mega-sections/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editingSection ? 'Колонка обновлена' : 'Колонка добавлена')
      setSectionModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeSection(id: number) {
    try {
      await api(`/api/admin/nav/mega-sections/${id}`, { method: 'DELETE' })
      message.success('Колонка удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function moveSection(id: number, direction: -1 | 1) {
    if (!megaItem) return
    const sections = [...megaItem.mega_sections]
    const index = sections.findIndex((s) => s.id === id)
    const target = index + direction
    if (index < 0 || target < 0 || target >= sections.length) return
    ;[sections[index], sections[target]] = [sections[target], sections[index]]
    try {
      await api('/api/admin/nav/mega-sections/reorder', {
        method: 'POST',
        body: JSON.stringify({ ids: sections.map((s) => s.id) }),
      })
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  function openCreateLink(sectionId: number) {
    const section = megaItem?.mega_sections.find((s) => s.id === sectionId)
    setEditingLink(null)
    setLinkSectionId(sectionId)
    linkForm.resetFields()
    linkForm.setFieldsValue({
      nav_mega_section_id: sectionId,
      is_active: true,
      sort_order: ((section?.links.length ?? 0) + 1) * 10,
    })
    setLinkModalOpen(true)
  }

  function openEditLink(row: NavMegaLink) {
    setEditingLink(row)
    setLinkSectionId(row.nav_mega_section_id)
    linkForm.setFieldsValue(row)
    setLinkModalOpen(true)
  }

  async function saveLink(values: Record<string, unknown>) {
    try {
      const payload = editingLink ? { ...values, id: editingLink.id } : values
      await api('/api/admin/nav/mega-links/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
      message.success(editingLink ? 'Ссылка обновлена' : 'Ссылка добавлена')
      setLinkModalOpen(false)
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  async function removeLink(id: number) {
    try {
      await api(`/api/admin/nav/mega-links/${id}`, { method: 'DELETE' })
      message.success('Ссылка удалена')
      await load()
    } catch (e) {
      message.error(String((e as Error).message))
    }
  }

  const itemLinkType = Form.useWatch('link_type', itemForm)
  const itemTaxonomyType = Form.useWatch('taxonomy_type', itemForm) as TaxonomyType | undefined
  const buttonLinkType = Form.useWatch('link_type', buttonForm)
  const buttonTaxonomyType = Form.useWatch('taxonomy_type', buttonForm) as TaxonomyType | undefined
  const sectionSourceType = Form.useWatch('source_type', sectionForm)

  useEffect(() => {
    if (megaDrawerOpen && megaItem) {
      setMegaItem(items.find((i) => i.id === megaItem.id) ?? megaItem)
    }
  }, [items, megaDrawerOpen, megaItem])

  const itemColumns: ColumnsType<NavItem> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    {
      title: 'Пункт',
      dataIndex: 'title',
      render: (title, row) => (
        <Space direction="vertical" size={0}>
          <span>{title}</span>
          <span className="admin-empty-hint">{row.url}</span>
        </Space>
      ),
    },
    {
      title: 'Тип',
      dataIndex: 'link_type',
      width: 120,
      render: (v) => LINK_TYPES.find((o) => o.value === v)?.label ?? v,
    },
    {
      title: 'Экран',
      key: 'screens',
      width: 120,
      render: (_, row) => (
        <Space size={4}>
          {row.show_desktop ? <Tag color="blue">Desktop</Tag> : null}
          {row.show_mobile ? <Tag color="purple">Mobile</Tag> : null}
        </Space>
      ),
    },
    {
      title: 'Mega',
      dataIndex: 'has_mega',
      width: 80,
      render: (v) => (v ? <Tag color="green">Да</Tag> : <Tag>Нет</Tag>),
    },
    {
      title: 'Статус',
      dataIndex: 'is_active',
      width: 90,
      render: (v) => (v ? <Tag color="green">Показ</Tag> : <Tag>Скрыт</Tag>),
    },
    {
      title: 'Действия',
      key: 'actions',
      width: 320,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => moveItem(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => moveItem(row.id, 1)} />
          <Button size="small" onClick={() => openEditItem(row)}>Изменить</Button>
          {row.has_mega ? (
            <Button size="small" icon={<SettingOutlined />} onClick={() => openMegaDrawer(row)}>
              Mega
            </Button>
          ) : null}
          <Popconfirm title="Удалить пункт?" onConfirm={() => removeItem(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  const buttonColumns: ColumnsType<NavMegaButton> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    {
      title: 'Кнопка',
      render: (_, row) => (
        <Space direction="vertical" size={0}>
          <span>{row.title}</span>
          {row.subtitle ? <span className="admin-empty-hint">{row.subtitle}</span> : null}
        </Space>
      ),
    },
    {
      title: 'Ссылка',
      key: 'link',
      render: (_, row) => LINK_TYPES.find((o) => o.value === row.link_type)?.label ?? row.link_type,
    },
    {
      title: '',
      key: 'actions',
      width: 260,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => moveButton(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => moveButton(row.id, 1)} />
          <Button size="small" onClick={() => openEditButton(row)}>Изменить</Button>
          <Popconfirm title="Удалить?" onConfirm={() => removeButton(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  const sectionColumns: ColumnsType<NavMegaSection> = [
    { title: '#', dataIndex: 'sort_order', width: 60 },
    { title: 'Заголовок', dataIndex: 'title' },
    {
      title: 'Источник',
      dataIndex: 'source_type',
      render: (v) => SECTION_TYPES.find((o) => o.value === v)?.label ?? v,
    },
    {
      title: 'Лимит',
      dataIndex: 'item_limit',
      width: 70,
      render: (v, row) => (row.source_type === 'custom' ? '—' : v),
    },
    {
      title: '',
      key: 'actions',
      width: 280,
      render: (_, row) => (
        <Space wrap size="small">
          <Button size="small" icon={<ArrowUpOutlined />} onClick={() => moveSection(row.id, -1)} />
          <Button size="small" icon={<ArrowDownOutlined />} onClick={() => moveSection(row.id, 1)} />
          <Button size="small" onClick={() => openEditSection(row)}>Изменить</Button>
          {row.source_type === 'custom' ? (
            <Button size="small" onClick={() => openCreateLink(row.id)}>+ ссылка</Button>
          ) : null}
          <Popconfirm title="Удалить?" onConfirm={() => removeSection(row.id)}>
            <Button size="small" danger>Удалить</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ]

  return (
    <div className="admin-page-card">
      <div className="admin-toolbar">
        <p className="admin-empty-hint">
          Пункты шапки сайта: порядок, видимость на desktop/mobile, mega-menu с кнопками и колонками.
        </p>
        <Button type="primary" icon={<PlusOutlined />} onClick={openCreateItem}>
          Добавить пункт
        </Button>
      </div>

      <Table rowKey="id" loading={loading} columns={itemColumns} dataSource={items} pagination={false} />

      <Modal
        title={editingItem ? 'Редактирование пункта меню' : 'Новый пункт меню'}
        open={itemModalOpen}
        onCancel={() => setItemModalOpen(false)}
        onOk={() => itemForm.submit()}
        okText="Сохранить"
        width={600}
      >
        <Form form={itemForm} layout="vertical" onFinish={saveItem}>
          <Form.Item label="Название" name="title" rules={[{ required: true }]}>
            <Input placeholder="Зарубежные сериалы" />
          </Form.Item>
          <Form.Item label="Тип ссылки" name="link_type" rules={[{ required: true }]}>
            <Select options={LINK_TYPES} />
          </Form.Item>
          {itemLinkType === 'taxonomy' ? (
            <>
              <Form.Item label="Тип справочника" name="taxonomy_type" rules={[{ required: true }]}>
                <Select
                  options={TAXONOMY_TYPE_OPTIONS}
                  onChange={() => itemForm.setFieldValue('taxonomy_id', undefined)}
                />
              </Form.Item>
              <Form.Item label="Элемент справочника" name="taxonomy_id" rules={[{ required: true }]}>
                <Select
                  showSearch
                  optionFilterProp="label"
                  options={taxonomyItemOptions(itemTaxonomyType)}
                  onChange={(id) => {
                    const list = itemTaxonomyType ? taxonomyOptions[itemTaxonomyType] ?? [] : []
                    const item = list.find((x) => x.id === id)
                    if (item && !itemForm.getFieldValue('title')) {
                      itemForm.setFieldValue('title', item.name)
                    }
                  }}
                />
              </Form.Item>
            </>
          ) : null}
          {itemLinkType === 'custom' ? (
            <Form.Item label="URL" name="custom_url" rules={[{ required: true }]}>
              <Input placeholder="/page/" />
            </Form.Item>
          ) : null}
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Показывать в desktop-меню" name="show_desktop" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Показывать в mobile-меню" name="show_mobile" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Mega-menu (выпадающее)" name="has_mega" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item label="Активен" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Drawer
        title={megaItem ? `Mega-menu: ${megaItem.title}` : 'Mega-menu'}
        width={720}
        open={megaDrawerOpen}
        onClose={() => setMegaDrawerOpen(false)}
      >
        {megaItem ? (
          <Tabs
            items={[
              {
                key: 'buttons',
                label: 'Кнопки',
                children: (
                  <>
                    <div className="admin-toolbar" style={{ marginBottom: 12 }}>
                      <Button type="primary" size="small" icon={<PlusOutlined />} onClick={openCreateButton}>
                        Добавить кнопку
                      </Button>
                    </div>
                    <Table
                      rowKey="id"
                      size="small"
                      columns={buttonColumns}
                      dataSource={megaItem.mega_buttons}
                      pagination={false}
                    />
                  </>
                ),
              },
              {
                key: 'sections',
                label: 'Колонки',
                children: (
                  <>
                    <div className="admin-toolbar" style={{ marginBottom: 12 }}>
                      <Button type="primary" size="small" icon={<PlusOutlined />} onClick={openCreateSection}>
                        Добавить колонку
                      </Button>
                    </div>
                    <Table
                      rowKey="id"
                      size="small"
                      columns={sectionColumns}
                      dataSource={megaItem.mega_sections}
                      pagination={false}
                      expandable={{
                        rowExpandable: (row) => row.source_type === 'custom',
                        expandedRowRender: (row) =>
                          row.source_type === 'custom' ? (
                            <Table
                              rowKey="id"
                              size="small"
                              pagination={false}
                              dataSource={row.links}
                              columns={[
                                { title: 'Текст', dataIndex: 'label' },
                                { title: 'URL', dataIndex: 'url' },
                                {
                                  title: '',
                                  key: 'actions',
                                  render: (_, link) => (
                                    <Space size="small">
                                      <Button size="small" onClick={() => openEditLink(link)}>Изменить</Button>
                                      <Popconfirm title="Удалить?" onConfirm={() => removeLink(link.id)}>
                                        <Button size="small" danger>Удалить</Button>
                                      </Popconfirm>
                                    </Space>
                                  ),
                                },
                              ]}
                            />
                          ) : null,
                      }}
                    />
                  </>
                ),
              },
            ]}
          />
        ) : null}
      </Drawer>

      <Modal
        title={editingButton ? 'Кнопка mega-menu' : 'Новая кнопка'}
        open={buttonModalOpen}
        onCancel={() => setButtonModalOpen(false)}
        onOk={() => buttonForm.submit()}
        okText="Сохранить"
      >
        <Form form={buttonForm} layout="vertical" onFinish={saveButton}>
          <Form.Item name="nav_item_id" hidden><Input /></Form.Item>
          <Form.Item label="Заголовок" name="title" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="Подзаголовок" name="subtitle">
            <Input />
          </Form.Item>
          <Form.Item label="Тип ссылки" name="link_type" rules={[{ required: true }]}>
            <Select options={LINK_TYPES.filter((o) => o.value !== 'home')} />
          </Form.Item>
          {buttonLinkType === 'taxonomy' ? (
            <>
              <Form.Item label="Тип справочника" name="taxonomy_type" rules={[{ required: true }]}>
                <Select
                  options={TAXONOMY_TYPE_OPTIONS}
                  onChange={() => buttonForm.setFieldValue('taxonomy_id', undefined)}
                />
              </Form.Item>
              <Form.Item label="Элемент справочника" name="taxonomy_id" rules={[{ required: true }]}>
                <Select
                  showSearch
                  optionFilterProp="label"
                  options={taxonomyItemOptions(buttonTaxonomyType)}
                />
              </Form.Item>
            </>
          ) : null}
          {buttonLinkType === 'custom' ? (
            <Form.Item label="URL" name="custom_url" rules={[{ required: true }]}>
              <Input />
            </Form.Item>
          ) : null}
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Активна" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={editingSection ? 'Колонка mega-menu' : 'Новая колонка'}
        open={sectionModalOpen}
        onCancel={() => setSectionModalOpen(false)}
        onOk={() => sectionForm.submit()}
        okText="Сохранить"
      >
        <Form form={sectionForm} layout="vertical" onFinish={saveSection}>
          <Form.Item name="nav_item_id" hidden><Input /></Form.Item>
          <Form.Item label="Заголовок колонки" name="title" rules={[{ required: true }]}>
            <Input placeholder="Жанры" />
          </Form.Item>
          <Form.Item label="Источник ссылок" name="source_type" rules={[{ required: true }]}>
            <Select options={SECTION_TYPES} />
          </Form.Item>
          {sectionSourceType !== 'custom' ? (
            <Form.Item label="Количество ссылок" name="item_limit">
              <InputNumber min={1} max={60} style={{ width: '100%' }} />
            </Form.Item>
          ) : null}
          <Form.Item label="CSS-класс секции" name="css_class" tooltip="Например: wide">
            <Input />
          </Form.Item>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Активна" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={editingLink ? 'Ссылка в колонке' : 'Новая ссылка'}
        open={linkModalOpen}
        onCancel={() => setLinkModalOpen(false)}
        onOk={() => linkForm.submit()}
        okText="Сохранить"
      >
        <Form form={linkForm} layout="vertical" onFinish={saveLink}>
          <Form.Item name="nav_mega_section_id" hidden initialValue={linkSectionId}>
            <Input />
          </Form.Item>
          <Form.Item label="Текст" name="label" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item label="URL" name="url" rules={[{ required: true }]}>
            <Input placeholder="/zarubezhnye-serialy/?genre=drama" />
          </Form.Item>
          <Form.Item label="Порядок" name="sort_order">
            <InputNumber style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="Активна" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )
}
