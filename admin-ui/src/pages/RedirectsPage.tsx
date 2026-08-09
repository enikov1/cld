import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons'
import {
	Button,
	Form,
	Input,
	Modal,
	Popconfirm,
	Select,
	Space,
	Switch,
	Table,
	Tag,
	Typography,
	message,
} from 'antd'
import type { ColumnsType } from 'antd/es/table'
import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'
import SeriesSearchSelect, { type SeriesSearchOption } from '../components/SeriesSearchSelect'
import { useDocumentTitle } from '../documentMeta/AdminDocumentMeta'
import { siteOrigin } from '../utils/mediaUrl'

type RedirectItem = {
	id: number
	from_path: string
	to_type: 'url' | 'series'
	to_path?: string | null
	target_path?: string | null
	series_id?: number | null
	status_code: number
	is_active: boolean
	note?: string | null
	hits_count: number
	series?: {
		id: number
		title: string
		kp_id?: string | null
		path: string
	} | null
}

type StatusCodeOption = {
	value: number
	label: string
}

const TO_TYPE_OPTIONS = [
	{ value: 'series', label: 'На страницу сериала' },
	{ value: 'url', label: 'На произвольный URL' },
]

function targetLabel(item: RedirectItem): string {
	if (item.to_type === 'series') {
		return item.series?.title ?? `Сериал #${item.series_id ?? '?'}`
	}
	return item.target_path ?? item.to_path ?? '—'
}

export default function RedirectsPage() {
	const [items, setItems] = useState<RedirectItem[]>([])
	const [total, setTotal] = useState(0)
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(50)
	const [query, setQuery] = useState('')
	const [search, setSearch] = useState('')
	const [loading, setLoading] = useState(false)
	const [modalOpen, setModalOpen] = useState(false)
	const [editing, setEditing] = useState<RedirectItem | null>(null)
	const [statusCodeOptions, setStatusCodeOptions] = useState<StatusCodeOption[]>([
		{ value: 301, label: '301 — постоянный' },
		{ value: 302, label: '302 — временный' },
		{ value: 307, label: '307 — временный (сохраняет метод)' },
		{ value: 308, label: '308 — постоянный (сохраняет метод)' },
	])
	const [selectedSeries, setSelectedSeries] = useState<SeriesSearchOption | null>(null)
	const [form] = Form.useForm()
	const watchedToType = Form.useWatch('to_type', form) as 'url' | 'series' | undefined

	useDocumentTitle(
		modalOpen
			? editing
				? `Редактируем редирект — ${editing.from_path}`
				: 'Новый редирект'
			: null,
	)

	const load = useCallback(async (nextPage = page, nextPerPage = perPage, q = query) => {
		setLoading(true)
		try {
			const params = new URLSearchParams()
			params.set('page', String(nextPage))
			params.set('per_page', String(nextPerPage))
			if (q.trim()) params.set('q', q.trim())
			const data = await api<{
				items: RedirectItem[]
				total: number
				page: number
				per_page: number
				status_code_options?: StatusCodeOption[]
			}>(`/api/admin/redirects?${params}`)
			setItems(data.items)
			setTotal(data.total)
			setPage(data.page)
			setPerPage(data.per_page)
			if (data.status_code_options?.length) {
				setStatusCodeOptions(data.status_code_options)
			}
		} catch (e) {
			message.error(String((e as Error).message))
		} finally {
			setLoading(false)
		}
	}, [page, perPage, query])

	useEffect(() => {
		load(1, perPage, '').catch(() => {
			/* handled in load */
		})
	}, []) // eslint-disable-line react-hooks/exhaustive-deps

	const openCreate = () => {
		setEditing(null)
		setSelectedSeries(null)
		form.resetFields()
		form.setFieldsValue({
			to_type: 'series',
			status_code: 301,
			is_active: true,
		})
		setModalOpen(true)
	}

	const openEdit = (item: RedirectItem) => {
		setEditing(item)
		setSelectedSeries(item.series ? {
			id: item.series.id,
			title: item.series.title,
			kp_id: item.series.kp_id,
			path: item.series.path,
		} : null)
		form.setFieldsValue({
			from_path: item.from_path,
			to_type: item.to_type,
			to_path: item.to_path ?? item.target_path ?? '',
			series_id: item.series_id ?? undefined,
			status_code: item.status_code,
			is_active: item.is_active,
			note: item.note ?? '',
		})
		setModalOpen(true)
	}

  const save = async () => {
    const values = await form.validateFields()
    const payload: Record<string, unknown> = {
      id: editing?.id,
      from_path: values.from_path,
      to_type: values.to_type,
      status_code: values.status_code,
      is_active: values.is_active,
      note: values.note ?? '',
    }
    if (values.to_type === 'series') {
      payload.series_id = values.series_id
    } else {
      payload.to_path = values.to_path
    }
    try {
      await api('/api/admin/redirects/upsert', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
			message.success(editing ? 'Редирект обновлён' : 'Редирект создан')
			setModalOpen(false)
			await load(page, perPage, query)
		} catch (e) {
			message.error(String((e as Error).message))
		}
	}

	const toggleActive = async (item: RedirectItem, isActive: boolean) => {
		try {
      await api(`/api/admin/redirects/${item.id}/toggle`, {
        method: 'POST',
        body: JSON.stringify({ is_active: isActive }),
      })
			setItems((prev) => prev.map((row) => (row.id === item.id ? { ...row, is_active: isActive } : row)))
		} catch (e) {
			message.error(String((e as Error).message))
		}
	}

	const remove = async (id: number) => {
		try {
			await api(`/api/admin/redirects/${id}`, { method: 'DELETE' })
			message.success('Редирект удалён')
			await load(page, perPage, query)
		} catch (e) {
			message.error(String((e as Error).message))
		}
	}

	const columns: ColumnsType<RedirectItem> = [
		{
			title: 'Откуда',
			dataIndex: 'from_path',
			render: (path: string) => (
				<Typography.Text code copyable={{ text: `${siteOrigin()}${path}` }}>
					{path}
				</Typography.Text>
			),
		},
		{
			title: 'Куда',
			key: 'target',
			render: (_, item) => {
				const target = item.target_path ?? item.to_path ?? ''
				return (
					<Space direction="vertical" size={0}>
						<span>{targetLabel(item)}</span>
						{target ? (
							<Typography.Text type="secondary" style={{ fontSize: 12 }}>
								{target}
							</Typography.Text>
						) : null}
					</Space>
				)
			},
		},
		{
			title: 'Код',
			dataIndex: 'status_code',
			width: 72,
			render: (code: number) => <Tag>{code}</Tag>,
		},
		{
			title: 'Переходы',
			dataIndex: 'hits_count',
			width: 96,
		},
		{
			title: 'Активен',
			dataIndex: 'is_active',
			width: 96,
			render: (active: boolean, item) => (
				<Switch checked={active} onChange={(checked) => toggleActive(item, checked)} />
			),
		},
		{
			title: 'Заметка',
			dataIndex: 'note',
			ellipsis: true,
			render: (note?: string | null) => note || '—',
		},
		{
			title: '',
			key: 'actions',
			width: 120,
			render: (_, item) => (
				<Space>
					<Button type="text" icon={<EditOutlined />} onClick={() => openEdit(item)} aria-label="Редактировать" />
					<Popconfirm title="Удалить редирект?" onConfirm={() => remove(item.id)}>
						<Button type="text" danger icon={<DeleteOutlined />} aria-label="Удалить" />
					</Popconfirm>
				</Space>
			),
		},
	]

	return (
		<div>
			<Space wrap style={{ marginBottom: 16 }}>
				<Input.Search
					allowClear
					placeholder="Поиск по пути, сериалу или заметке"
					style={{ width: 320 }}
					value={search}
					onChange={(e) => setSearch(e.target.value)}
					onSearch={(value) => {
						setQuery(value.trim())
						load(1, perPage, value.trim())
					}}
				/>
				<Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>
					Добавить редирект
				</Button>
			</Space>

			<Table
				rowKey="id"
				loading={loading}
				columns={columns}
				dataSource={items}
				pagination={{
					current: page,
					pageSize: perPage,
					total,
					showSizeChanger: true,
					onChange: (nextPage, nextPerPage) => {
						setPage(nextPage)
						setPerPage(nextPerPage)
						load(nextPage, nextPerPage, query)
					},
				}}
			/>

			<Modal
				title={editing ? 'Редактировать редирект' : 'Новый редирект'}
				open={modalOpen}
				onCancel={() => setModalOpen(false)}
				onOk={save}
				okText="Сохранить"
				cancelText="Отмена"
				width={640}
				destroyOnClose
			>
				<Form form={form} layout="vertical" style={{ marginTop: 16 }}>
					<Form.Item
						name="from_path"
						label="Исходный путь"
						rules={[{ required: true, message: 'Укажите путь, с которого будет редирект' }]}
						extra="Например: /old-page.html или /serialy/staryj-serial.html"
					>
						<Input placeholder="/staryj-url.html" />
					</Form.Item>

					<Form.Item name="to_type" label="Куда перенаправлять" rules={[{ required: true }]}>
						<Select options={TO_TYPE_OPTIONS} />
					</Form.Item>

					{watchedToType === 'series' ? (
						<Form.Item
							name="series_id"
							label="Сериал"
							rules={[{ required: true, message: 'Выберите сериал' }]}
						>
							<SeriesSearchSelect
								initialOption={selectedSeries}
								onChange={(_id, option) => setSelectedSeries(option ?? null)}
							/>
						</Form.Item>
					) : (
						<Form.Item
							name="to_path"
							label="URL или путь назначения"
							rules={[{ required: true, message: 'Укажите URL или путь' }]}
							extra="Можно указать путь (/collections/slug/) или полный URL (https://…)"
						>
							<Input placeholder="/123-serial-2024.html" />
						</Form.Item>
					)}

					<Form.Item name="status_code" label="Тип редиректа" rules={[{ required: true }]}>
						<Select options={statusCodeOptions.map((o) => ({ value: o.value, label: o.label }))} />
					</Form.Item>

					<Form.Item name="is_active" label="Активен" valuePropName="checked">
						<Switch />
					</Form.Item>

					<Form.Item name="note" label="Заметка">
						<Input.TextArea rows={2} placeholder="Необязательно: зачем нужен этот редирект" />
					</Form.Item>
				</Form>
			</Modal>
		</div>
	)
}
