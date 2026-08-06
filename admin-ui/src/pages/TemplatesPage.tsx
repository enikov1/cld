import {
  BookOutlined,
  DeleteOutlined,
  EditOutlined,
  FileAddOutlined,
  FolderAddOutlined,
  MoreOutlined,
  ReloadOutlined,
  SaveOutlined,
  UploadOutlined,
} from '@ant-design/icons'
import {
  Alert,
  Button,
  Card,
  Dropdown,
  Empty,
  Form,
  Input,
  Modal,
  Select,
  Space,
  Spin,
  Tabs,
  Tag,
  Tree,
  Typography,
  message,
} from 'antd'
import type { DataNode } from 'antd/es/tree'
import type { MenuProps } from 'antd'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { api, apiUpload } from '../api/client'
import TplDocsPanel from '../components/TplDocsPanel'
import TemplateCodeEditor from '../components/TemplateCodeEditor'
import {
  isImageFile,
  isSvgFile,
  templateFileIcon,
  templateFileKind,
} from '../components/templateFileIcons'
import type { TplEditorHandle } from '../components/tplEditorUtils'
import type { ThemeItem } from '../types'
import type { TplDocsPayload } from '../types/tplDocs'
import { contextsForPath } from '../types/tplDocs'
import { useAdminTheme } from '../theme/useAdminTheme'

type TemplateTreeNode = {
  key: string
  title: string
  path?: string
  isLeaf?: boolean
  children?: TemplateTreeNode[]
}

type TemplateFileResponse = {
  path: string
  content: string
  binary?: boolean
  preview_url?: string
  size: number
  modified_at: string
}

const ALLOWED_UPLOAD_EXT = ['tpl', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'woff', 'woff2']

function formatBytes(size: number): string {
  if (size < 1024) return `${size} B`
  return `${(size / 1024).toFixed(1)} KB`
}

function joinThemePath(parent: string, name: string): string {
  const cleanParent = parent.replace(/^\/+|\/+$/g, '')
  const cleanName = name.replace(/^\/+|\/+$/g, '')
  if (!cleanParent) return cleanName
  if (!cleanName) return cleanParent
  return `${cleanParent}/${cleanName}`
}

function parentThemePath(path: string): string {
  if (!path.includes('/')) return ''
  return path.slice(0, path.lastIndexOf('/'))
}

function baseThemeName(path: string): string {
  return path.includes('/') ? path.slice(path.lastIndexOf('/') + 1) : path
}

function remapThemePath(path: string, from: string, to: string): string {
  if (path === from) return to
  if (path.startsWith(`${from}/`)) return `${to}${path.slice(from.length)}`
  return path
}

function isAllowedUploadName(name: string): boolean {
  const ext = name.split('.').pop()?.toLowerCase() ?? ''
  return ALLOWED_UPLOAD_EXT.includes(ext)
}

type DropTreeNode = DataNode & { isLeaf?: boolean; pos?: string; expanded?: boolean }

function resolveDropTargetPath(
  dragKey: string,
  dropNode: DropTreeNode,
  dropPosition: number,
  dropToGap?: boolean,
): string | null {
  if (!dragKey || dragKey === 'layout.tpl') return null

  const dropKey = String(dropNode.key)
  const dropPos = (dropNode.pos ?? '0').split('-')
  const relativePos = dropPosition - Number(dropPos[dropPos.length - 1])

  let targetDir: string
  if (!dropToGap) {
    targetDir = dropNode.isLeaf ? parentThemePath(dropKey) : dropKey
  } else if (!dropNode.isLeaf && dropNode.expanded && relativePos === 1) {
    // Ant Design: нижний gap у раскрытой папки = внутрь папки
    targetDir = dropKey
  } else {
    targetDir = parentThemePath(dropKey)
  }

  if (targetDir === dragKey || targetDir.startsWith(`${dragKey}/`)) {
    return null
  }

  const to = joinThemePath(targetDir, baseThemeName(dragKey))
  if (to === dragKey || to === 'layout.tpl') return null
  return to
}

function toTreeData(nodes: TemplateTreeNode[]): DataNode[] {
  return nodes.map((node) => ({
    key: node.key,
    title: node.title,
    isLeaf: node.isLeaf,
    icon: templateFileIcon(node.path ?? node.key, !node.isLeaf),
    children: node.children ? toTreeData(node.children) : undefined,
  }))
}

function useLiveSvgPreview(content: string, enabled: boolean): string | null {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (!enabled) {
      setUrl(null)
      return
    }
    const blob = new Blob([content || '<svg xmlns="http://www.w3.org/2000/svg"/>'], {
      type: 'image/svg+xml;charset=utf-8',
    })
    const objectUrl = URL.createObjectURL(blob)
    setUrl(objectUrl)
    return () => URL.revokeObjectURL(objectUrl)
  }, [content, enabled])

  return url
}

function findTreeNode(nodes: TemplateTreeNode[], key: string): TemplateTreeNode | null {
  for (const node of nodes) {
    if (node.key === key) return node
    if (node.children) {
      const found = findTreeNode(node.children, key)
      if (found) return found
    }
  }
  return null
}

export default function TemplatesPage() {
  const [activeTab, setActiveTab] = useState('editor')
  const [themes, setThemes] = useState<ThemeItem[]>([])
  const [theme, setTheme] = useState('')
  const [tree, setTree] = useState<TemplateTreeNode[]>([])
  const [treeLoading, setTreeLoading] = useState(false)
  const [fileLoading, setFileLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [selectedPath, setSelectedPath] = useState<string | null>(null)
  const [activeTreeKey, setActiveTreeKey] = useState<string | null>(null)
  const [content, setContent] = useState('')
  const [savedContent, setSavedContent] = useState('')
  const [fileMeta, setFileMeta] = useState<{ size: number; modified_at: string } | null>(null)
  const [binaryPreviewUrl, setBinaryPreviewUrl] = useState<string | null>(null)
  const [createOpen, setCreateOpen] = useState(false)
  const [createFolderOpen, setCreateFolderOpen] = useState(false)
  const [renameOpen, setRenameOpen] = useState(false)
  const [createForm] = Form.useForm<{ path: string }>()
  const [createFolderForm] = Form.useForm<{ path: string }>()
  const [renameForm] = Form.useForm<{ to: string }>()
  const [renameFrom, setRenameFrom] = useState('')
  const [docs, setDocs] = useState<TplDocsPayload | null>(null)
  const [cssClasses, setCssClasses] = useState<string[]>([])
  const [expandedKeys, setExpandedKeys] = useState<string[]>([])
  const editorRef = useRef<TplEditorHandle | null>(null)
  const uploadInputRef = useRef<HTMLInputElement | null>(null)
  const uploadTargetDirRef = useRef('')
  const { isDark } = useAdminTheme()

  const isDirty = selectedPath !== null && content !== savedContent
  const isTplFile = selectedPath?.endsWith('.tpl') ?? false
  const isSvg = isSvgFile(selectedPath)
  const isRasterImage = templateFileKind(selectedPath) === 'image'
  const isFontFile = templateFileKind(selectedPath) === 'font'
  const liveSvgPreview = useLiveSvgPreview(content, isSvg && !binaryPreviewUrl)
  const imagePreviewUrl = isSvg
    ? liveSvgPreview
    : binaryPreviewUrl && isRasterImage
      ? binaryPreviewUrl
      : null
  const activeTreeNode = activeTreeKey ? findTreeNode(tree, activeTreeKey) : null
  const activeIsFolder = activeTreeNode ? !activeTreeNode.isLeaf : false
  const defaultTargetDir = activeIsFolder ? activeTreeKey ?? '' : (activeTreeKey?.includes('/') ? activeTreeKey.slice(0, activeTreeKey.lastIndexOf('/')) : '')

  const hintContexts = useMemo(() => {
    if (docs?.active_contexts?.length) return docs.active_contexts
    return contextsForPath(selectedPath)
  }, [docs?.active_contexts, selectedPath])

  const loadDocs = useCallback(async (path?: string | null) => {
    const query = path ? `?path=${encodeURIComponent(path)}` : ''
    const data = await api<TplDocsPayload>(`/api/admin/templates/docs${query}`)
    setDocs(data)
  }, [])

  const loadCssClasses = useCallback(async (themeName: string) => {
    if (!themeName) {
      setCssClasses([])
      return
    }
    try {
      const data = await api<{ classes: string[] }>(
        `/api/admin/templates/css-classes?theme=${encodeURIComponent(themeName)}`,
      )
      setCssClasses(data.classes ?? [])
    } catch {
      setCssClasses([])
    }
  }, [])

  const loadThemes = useCallback(async () => {
    const data = await api<{ items: ThemeItem[]; active: string }>('/api/admin/templates/themes')
    setThemes(data.items)
    setTheme((current) => current || data.active)
  }, [])

  const loadTree = useCallback(async (themeName: string) => {
    if (!themeName) return
    setTreeLoading(true)
    try {
      const data = await api<{ items: TemplateTreeNode[] }>(
        `/api/admin/templates/tree?theme=${encodeURIComponent(themeName)}`,
      )
      setTree(data.items)
    } finally {
      setTreeLoading(false)
    }
  }, [])

  const loadFile = useCallback(
    async (themeName: string, path: string) => {
      setFileLoading(true)
      try {
        const data = await api<TemplateFileResponse>(
          `/api/admin/templates/file?theme=${encodeURIComponent(themeName)}&path=${encodeURIComponent(path)}`,
        )
        setSelectedPath(data.path)
        setActiveTreeKey(data.path)
        setContent(data.content)
        setSavedContent(data.content)
        setBinaryPreviewUrl(data.binary ? (data.preview_url ?? null) : null)
        setFileMeta({ size: data.size, modified_at: data.modified_at })
        if (path.endsWith('.tpl') && !data.binary) {
          await loadDocs(path)
        }
      } finally {
        setFileLoading(false)
      }
    },
    [loadDocs],
  )

  useEffect(() => {
    loadThemes().catch((err: Error) => message.error(err.message))
    loadDocs().catch((err: Error) => message.error(err.message))
  }, [loadDocs, loadThemes])

  useEffect(() => {
    if (!theme) return
    loadTree(theme).catch((err: Error) => message.error(err.message))
    void loadCssClasses(theme)
  }, [theme, loadTree, loadCssClasses])

  const treeData = useMemo(() => toTreeData(tree), [tree])

  const openFile = useCallback(
    (path: string) => {
      const parentPath = path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : ''
      if (parentPath) {
        setExpandedKeys((prev) => (prev.includes(parentPath) ? prev : [...prev, parentPath]))
      }

      if (isDirty) {
        Modal.confirm({
          title: 'Несохранённые изменения',
          content: 'Открыть другой файл без сохранения?',
          okText: 'Открыть',
          cancelText: 'Отмена',
          onOk: () => loadFile(theme, path),
        })
        return
      }
      loadFile(theme, path).catch((err: Error) => message.error(err.message))
    },
    [isDirty, loadFile, theme],
  )

  const saveFile = useCallback(async () => {
    if (!selectedPath || !theme || binaryPreviewUrl) return
    setSaving(true)
    try {
      const res = await api<{ ok: boolean; size: number; modified_at: string }>('/api/admin/templates/file', {
        method: 'POST',
        body: JSON.stringify({ theme, path: selectedPath, content }),
      })
      setSavedContent(content)
      setFileMeta({ size: res.size, modified_at: res.modified_at })
      message.success('Файл сохранён')
    } catch (err) {
      message.error(err instanceof Error ? err.message : 'Ошибка сохранения')
    } finally {
      setSaving(false)
    }
  }, [binaryPreviewUrl, content, selectedPath, theme])

  const deleteEntry = useCallback(
    async (path: string) => {
      if (!theme) return
      const node = findTreeNode(tree, path)
      const isFolder = node ? !node.isLeaf : false

      Modal.confirm({
        title: isFolder ? 'Удалить папку?' : 'Удалить файл?',
        content: isFolder ? `${path} и всё содержимое будут удалены.` : path,
        okText: 'Удалить',
        okButtonProps: { danger: true },
        cancelText: 'Отмена',
        onOk: async () => {
          await api(
            `/api/admin/templates/file?theme=${encodeURIComponent(theme)}&path=${encodeURIComponent(path)}`,
            { method: 'DELETE' },
          )
          message.success(isFolder ? 'Папка удалена' : 'Файл удалён')
          if (selectedPath === path || (isFolder && selectedPath?.startsWith(`${path}/`))) {
            setSelectedPath(null)
            setContent('')
            setSavedContent('')
            setFileMeta(null)
            setBinaryPreviewUrl(null)
          }
          if (activeTreeKey === path || (isFolder && activeTreeKey?.startsWith(`${path}/`))) {
            setActiveTreeKey(null)
          }
          await loadTree(theme)
        },
      })
    },
    [activeTreeKey, loadTree, selectedPath, theme, tree],
  )

  const createFile = useCallback(async () => {
    const values = await createForm.validateFields()
    const path = values.path.trim()
    await api('/api/admin/templates/file/create', {
      method: 'POST',
      body: JSON.stringify({ theme, path, content: '' }),
    })
    message.success('Файл создан')
    setCreateOpen(false)
    createForm.resetFields()
    await loadTree(theme)
    await loadFile(theme, path)
  }, [createForm, loadFile, loadTree, theme])

  const createFolder = useCallback(async () => {
    const values = await createFolderForm.validateFields()
    const path = values.path.trim()
    await api('/api/admin/templates/directory/create', {
      method: 'POST',
      body: JSON.stringify({ theme, path }),
    })
    message.success('Папка создана')
    setCreateFolderOpen(false)
    createFolderForm.resetFields()
    await loadTree(theme)
    setActiveTreeKey(path)
  }, [createFolderForm, loadTree, theme])

  const renameEntry = useCallback(async () => {
    const values = await renameForm.validateFields()
    const to = values.to.trim()
    await api('/api/admin/templates/rename', {
      method: 'POST',
      body: JSON.stringify({ theme, from: renameFrom, to }),
    })
    message.success('Переименовано')
    setRenameOpen(false)
    renameForm.resetFields()
    await loadTree(theme)
    if (selectedPath === renameFrom) {
      await loadFile(theme, to)
    } else {
      setActiveTreeKey(to)
    }
  }, [loadFile, loadTree, renameForm, renameFrom, selectedPath, theme])

  const moveEntry = useCallback(
    async (from: string, to: string) => {
      if (!theme || from === to) return
      try {
        await api('/api/admin/templates/rename', {
          method: 'POST',
          body: JSON.stringify({ theme, from, to }),
        })
        message.success(`Перемещено → ${to}`)
        await loadTree(theme)

        setExpandedKeys((prev) => {
          const next = prev.map((key) => remapThemePath(key, from, to))
          const parent = parentThemePath(to)
          if (parent && !next.includes(parent)) next.push(parent)
          return [...new Set(next)]
        })

        if (selectedPath === from || selectedPath?.startsWith(`${from}/`)) {
          const nextPath = remapThemePath(selectedPath, from, to)
          if (binaryPreviewUrl) {
            await loadFile(theme, nextPath)
          } else {
            setSelectedPath(nextPath)
            setActiveTreeKey(nextPath)
          }
        } else if (activeTreeKey === from || activeTreeKey?.startsWith(`${from}/`)) {
          setActiveTreeKey(remapThemePath(activeTreeKey, from, to))
        }
      } catch (err) {
        message.error(err instanceof Error ? err.message : 'Не удалось переместить')
      }
    },
    [activeTreeKey, binaryPreviewUrl, loadFile, loadTree, selectedPath, theme],
  )

  const onTreeDrop = useCallback(
    (info: {
      node: DropTreeNode
      dragNode: DropTreeNode
      dropPosition: number
      dropToGap?: boolean
    }) => {
      const from = String(info.dragNode.key)
      const to = resolveDropTargetPath(from, info.node, info.dropPosition, info.dropToGap)
      if (!to) return
      void moveEntry(from, to)
    },
    [moveEntry],
  )

  const uploadFile = useCallback(
    async (file: File, targetDir = '') => {
      if (!theme) return
      if (!isAllowedUploadName(file.name)) {
        message.error(`Разрешены: ${ALLOWED_UPLOAD_EXT.join(', ')}`)
        return
      }
      const path = joinThemePath(targetDir, file.name)
      const formData = new FormData()
      formData.append('theme', theme)
      formData.append('path', path)
      formData.append('file', file)
      try {
        const res = await apiUpload<{ ok: boolean; path: string }>('/api/admin/templates/file/upload', formData)
        message.success(`Файл загружен: ${res.path}`)
        await loadTree(theme)
        if (
          res.path.endsWith('.tpl') ||
          res.path.endsWith('.css') ||
          res.path.endsWith('.js') ||
          isImageFile(res.path)
        ) {
          await loadFile(theme, res.path)
        } else {
          setActiveTreeKey(res.path)
        }
      } catch (err) {
        message.error(err instanceof Error ? err.message : 'Ошибка загрузки')
      }
    },
    [loadFile, loadTree, theme],
  )

  const openCreateFile = useCallback(
    (targetDir = defaultTargetDir) => {
      createForm.setFieldsValue({ path: targetDir ? `${targetDir}/` : '' })
      setCreateOpen(true)
    },
    [createForm, defaultTargetDir],
  )

  const openCreateFolder = useCallback(
    (targetDir = defaultTargetDir) => {
      createFolderForm.setFieldsValue({ path: targetDir ? `${targetDir}/` : '' })
      setCreateFolderOpen(true)
    },
    [createFolderForm, defaultTargetDir],
  )

  const openRename = useCallback(
    (path: string) => {
      setRenameFrom(path)
      renameForm.setFieldsValue({ to: path })
      setRenameOpen(true)
    },
    [renameForm],
  )

  const triggerUpload = useCallback((targetDir = defaultTargetDir) => {
    uploadTargetDirRef.current = targetDir
    uploadInputRef.current?.click()
  }, [defaultTargetDir])

  const nodeMenu = useCallback(
    (path: string, isLeaf: boolean): MenuProps['items'] => {
      const protectedFile = path === 'layout.tpl'
      const items: MenuProps['items'] = [
        {
          key: 'rename',
          icon: <EditOutlined />,
          label: 'Переименовать',
          disabled: protectedFile,
        },
        {
          key: 'delete',
          icon: <DeleteOutlined />,
          label: 'Удалить',
          danger: true,
          disabled: protectedFile,
        },
      ]

      if (!isLeaf) {
        items.unshift(
          { key: 'upload', icon: <UploadOutlined />, label: 'Загрузить файл' },
          { key: 'new-file', icon: <FileAddOutlined />, label: 'Новый файл' },
          { key: 'new-folder', icon: <FolderAddOutlined />, label: 'Новая папка' },
          { type: 'divider' },
        )
      }

      return items
    },
    [],
  )

  const onNodeMenuClick = useCallback(
    (path: string, isLeaf: boolean, key: string) => {
      if (key === 'rename') openRename(path)
      if (key === 'delete') deleteEntry(path)
      if (key === 'new-file') openCreateFile(path)
      if (key === 'new-folder') openCreateFolder(path)
      if (key === 'upload') triggerUpload(path)
      if (isLeaf && key === 'rename') return
    },
    [deleteEntry, openCreateFile, openCreateFolder, openRename, triggerUpload],
  )

  const insertTag = useCallback((text: string) => {
    editorRef.current?.insertAtCursor(text)
  }, [])

  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault()
        if (selectedPath && isDirty) saveFile()
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [isDirty, saveFile, selectedPath])

  const editorView = (
    <div className="template-editor">
      <Card className="template-editor__toolbar" size="small">
        <Space wrap>
          <Typography.Text strong>Тема:</Typography.Text>
          <Select
            style={{ minWidth: 180 }}
            value={theme || undefined}
            options={themes.map((item) => ({ value: item.name, label: item.label }))}
            onChange={(value) => {
              if (isDirty) {
                Modal.confirm({
                  title: 'Несохранённые изменения',
                  content: 'Переключить тему без сохранения?',
                  onOk: () => {
                    setSelectedPath(null)
                    setActiveTreeKey(null)
                    setContent('')
                    setSavedContent('')
                    setFileMeta(null)
                    setBinaryPreviewUrl(null)
                    setTheme(value)
                  },
                })
                return
              }
              setSelectedPath(null)
              setActiveTreeKey(null)
              setContent('')
              setSavedContent('')
              setFileMeta(null)
              setBinaryPreviewUrl(null)
              setTheme(value)
            }}
          />
          <Button icon={<ReloadOutlined />} onClick={() => loadTree(theme)} loading={treeLoading}>
            Обновить
          </Button>
          <Button icon={<FileAddOutlined />} onClick={() => openCreateFile()}>
            Новый файл
          </Button>
          <Button icon={<FolderAddOutlined />} onClick={() => openCreateFolder()}>
            Новая папка
          </Button>
          <Button icon={<UploadOutlined />} onClick={() => triggerUpload()}>
            Загрузить
          </Button>
          <input
            ref={uploadInputRef}
            type="file"
            hidden
            accept={ALLOWED_UPLOAD_EXT.map((ext) => `.${ext}`).join(',')}
            onChange={(e) => {
              const file = e.target.files?.[0]
              e.target.value = ''
              if (file) uploadFile(file, uploadTargetDirRef.current)
            }}
          />
        </Space>
      </Card>

      <div className="template-editor__body">
        <Card
          className="template-editor__tree"
          title="Файлы темы"
          size="small"
          extra={
            activeTreeKey ? (
              <Dropdown
                menu={{
                  items: nodeMenu(activeTreeKey, !activeIsFolder),
                  onClick: ({ key }) => onNodeMenuClick(activeTreeKey, !activeIsFolder, key),
                }}
                trigger={['click']}
              >
                <Button size="small" icon={<MoreOutlined />} />
              </Dropdown>
            ) : null
          }
        >
          <Spin spinning={treeLoading}>
            {treeData.length ? (
              <Tree
                showIcon
                blockNode
                draggable={{ icon: false, nodeDraggable: (node) => String(node.key) !== 'layout.tpl' }}
                allowDrop={({ dropNode, dropPosition }) => {
                  if (dropPosition === 0) return !dropNode.isLeaf
                  return true
                }}
                treeData={treeData}
                expandedKeys={expandedKeys}
                selectedKeys={activeTreeKey ? [activeTreeKey] : []}
                titleRender={(node) => {
                  const path = String(node.key)
                  const isLeaf = Boolean(node.isLeaf)
                  return (
                    <span className="template-tree-node">
                      <span className="template-tree-node__label">{node.title as string}</span>
                      <Dropdown
                        menu={{
                          items: nodeMenu(path, isLeaf),
                          onClick: ({ key, domEvent }) => {
                            domEvent.stopPropagation()
                            onNodeMenuClick(path, isLeaf, key)
                          },
                        }}
                        trigger={['click']}
                      >
                        <Button
                          type="text"
                          size="small"
                          className="template-tree-node__menu"
                          icon={<MoreOutlined />}
                          onClick={(e) => e.stopPropagation()}
                          onMouseDown={(e) => e.stopPropagation()}
                        />
                      </Dropdown>
                    </span>
                  )
                }}
                onExpand={(keys) => setExpandedKeys(keys as string[])}
                onDrop={onTreeDrop}
                onSelect={(_, info) => {
                  const node = info.node as DataNode & { isLeaf?: boolean }
                  const path = String(node.key)
                  setActiveTreeKey(path)
                  if (node.isLeaf) {
                    openFile(path)
                    return
                  }
                  setExpandedKeys((prev) =>
                    prev.includes(path) ? prev.filter((key) => key !== path) : [...prev, path],
                  )
                }}
              />
            ) : (
              <Empty description="Нет файлов" image={Empty.PRESENTED_IMAGE_SIMPLE} />
            )}
          </Spin>
        </Card>

        <div className="template-editor__main">
          <Card
            className="template-editor__panel"
            size="small"
            title={
              selectedPath ? (
                <Space wrap>
                  <span className="template-editor__path">
                    {templateFileIcon(selectedPath)}
                    <Typography.Text code>{selectedPath}</Typography.Text>
                  </span>
                  {isDirty ? <Tag color="orange">Не сохранено</Tag> : <Tag color="green">Сохранено</Tag>}
                </Space>
              ) : (
                'Редактор'
              )
            }
            extra={
              selectedPath ? (
                <Space>
                  {!binaryPreviewUrl ? (
                    <Button type="primary" icon={<SaveOutlined />} onClick={saveFile} loading={saving} disabled={!isDirty}>
                      Сохранить
                    </Button>
                  ) : null}
                  <Button danger icon={<DeleteOutlined />} onClick={() => deleteEntry(selectedPath)} disabled={selectedPath === 'layout.tpl'}>
                    Удалить
                  </Button>
                </Space>
              ) : null
            }
          >
            {!selectedPath ? (
              <Empty description="Выберите файл слева" image={Empty.PRESENTED_IMAGE_SIMPLE} />
            ) : (
              <Spin spinning={fileLoading}>
                {isTplFile ? (
                  <Alert
                    type="info"
                    showIcon
                    className="template-editor__hint"
                    message="Подсказки: { и [ — TPL-теги, < — HTML-теги. В CSS — свойства. Ctrl+Space — показать."
                  />
                ) : isSvg ? (
                  <Alert
                    type="info"
                    showIcon
                    className="template-editor__hint"
                    message="SVG: слева предпросмотр (обновляется при правках), справа исходный код."
                  />
                ) : isRasterImage || isFontFile ? (
                  <Alert
                    type="info"
                    showIcon
                    className="template-editor__hint"
                    message="Бинарный файл. Чтобы заменить — удалите текущий и загрузите новый с тем же именем."
                  />
                ) : (
                  <Alert type="info" showIcon className="template-editor__hint" message="Редактирование CSS, JS, SVG или изображений." />
                )}
                {fileMeta ? (
                  <Typography.Text type="secondary" className="template-editor__meta">
                    {formatBytes(fileMeta.size)} · изменён {new Date(fileMeta.modified_at).toLocaleString('ru-RU')}
                  </Typography.Text>
                ) : null}
                <div className={`template-editor__editor${isSvg ? ' template-editor__editor--svg' : ''}`}>
                  {imagePreviewUrl ? (
                    <div className="template-editor__preview">
                      <div className="template-editor__preview-frame">
                        <img src={imagePreviewUrl} alt={selectedPath ?? ''} />
                      </div>
                    </div>
                  ) : null}
                  {isFontFile && binaryPreviewUrl ? (
                    <div className="template-editor__preview template-editor__preview--binary">
                      <Typography.Paragraph type="secondary">
                        Шрифт нельзя отредактировать в браузере. Замените файл через «Загрузить».
                      </Typography.Paragraph>
                    </div>
                  ) : null}
                  {!binaryPreviewUrl ? (
                    <TemplateCodeEditor
                      ref={editorRef}
                      value={content}
                      onChange={setContent}
                      filePath={selectedPath}
                      hints={isTplFile && docs ? docs.hints : []}
                      contexts={hintContexts}
                      hintsPrefiltered={Boolean(docs?.active_contexts?.length)}
                      cssClasses={cssClasses}
                      isDark={isDark}
                      height={isSvg ? '360px' : '420px'}
                    />
                  ) : null}
                </div>
              </Spin>
            )}
          </Card>

          {isTplFile && docs ? (
            <Card className="template-editor__side-docs" title="Подсказки для файла" size="small">
              <TplDocsPanel docs={docs} filePath={selectedPath} compact onInsert={insertTag} />
            </Card>
          ) : null}
        </div>
      </div>

      <Modal
        title="Новый файл"
        open={createOpen}
        onCancel={() => setCreateOpen(false)}
        onOk={() => createFile().catch((err: Error) => message.error(err.message))}
        okText="Создать"
        cancelText="Отмена"
      >
        <Form form={createForm} layout="vertical">
          <Form.Item
            name="path"
            label="Путь относительно темы"
            rules={[{ required: true, message: 'Укажите путь' }]}
            extra="Например: partials/widget.tpl или assets/custom.css"
          >
            <Input placeholder="partials/my-block.tpl" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Новая папка"
        open={createFolderOpen}
        onCancel={() => setCreateFolderOpen(false)}
        onOk={() => createFolder().catch((err: Error) => message.error(err.message))}
        okText="Создать"
        cancelText="Отмена"
      >
        <Form form={createFolderForm} layout="vertical">
          <Form.Item
            name="path"
            label="Путь папки относительно темы"
            rules={[{ required: true, message: 'Укажите путь' }]}
            extra="Например: partials/blocks или assets/icons"
          >
            <Input placeholder="partials/blocks" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Переименовать"
        open={renameOpen}
        onCancel={() => setRenameOpen(false)}
        onOk={() => renameEntry().catch((err: Error) => message.error(err.message))}
        okText="Сохранить"
        cancelText="Отмена"
      >
        <Form form={renameForm} layout="vertical">
          <Form.Item label="Текущий путь">
            <Input value={renameFrom} disabled />
          </Form.Item>
          <Form.Item
            name="to"
            label="Новый путь"
            rules={[{ required: true, message: 'Укажите новый путь' }]}
          >
            <Input placeholder="partials/new-name.tpl" />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  )

  return (
    <Tabs
      activeKey={activeTab}
      onChange={setActiveTab}
      items={[
        { key: 'editor', label: 'Редактор', children: editorView },
        {
          key: 'docs',
          label: (
            <span>
              <BookOutlined /> Справка TPL
            </span>
          ),
          children: <TplDocsPanel docs={docs} filePath={selectedPath} onInsert={insertTag} />,
        },
      ]}
    />
  )
}
