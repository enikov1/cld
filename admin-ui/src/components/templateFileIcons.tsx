import {
  BgColorsOutlined,
  CodeOutlined,
  FileImageOutlined,
  FileOutlined,
  FolderOutlined,
  FontSizeOutlined,
  Html5Outlined,
  PictureOutlined,
} from '@ant-design/icons'
import type { ReactNode } from 'react'

export type TemplateFileKind = 'folder' | 'tpl' | 'css' | 'js' | 'svg' | 'image' | 'font' | 'other'

const IMAGE_EXT = new Set(['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'ico'])
const FONT_EXT = new Set(['woff', 'woff2', 'ttf', 'otf', 'eot'])

export function fileExt(path: string | null | undefined): string {
  if (!path) return ''
  const name = path.split('/').pop() ?? path
  const dot = name.lastIndexOf('.')
  if (dot < 0) return ''
  return name.slice(dot + 1).toLowerCase()
}

export function templateFileKind(path: string | null | undefined, isFolder = false): TemplateFileKind {
  if (isFolder) return 'folder'
  const ext = fileExt(path)
  if (ext === 'tpl' || ext === 'html' || ext === 'htm') return 'tpl'
  if (ext === 'css') return 'css'
  if (ext === 'js' || ext === 'mjs' || ext === 'cjs') return 'js'
  if (ext === 'svg') return 'svg'
  if (IMAGE_EXT.has(ext)) return 'image'
  if (FONT_EXT.has(ext)) return 'font'
  return 'other'
}

export function isImageFile(path: string | null | undefined): boolean {
  const kind = templateFileKind(path)
  return kind === 'image' || kind === 'svg'
}

export function isSvgFile(path: string | null | undefined): boolean {
  return templateFileKind(path) === 'svg'
}

export function templateFileIcon(path: string | null | undefined, isFolder = false): ReactNode {
  const kind = templateFileKind(path, isFolder)
  const className = `template-file-icon template-file-icon--${kind}`

  switch (kind) {
    case 'folder':
      return <FolderOutlined className={className} />
    case 'tpl':
      return <Html5Outlined className={className} />
    case 'css':
      return <BgColorsOutlined className={className} />
    case 'js':
      return <CodeOutlined className={className} />
    case 'svg':
      return <PictureOutlined className={className} />
    case 'image':
      return <FileImageOutlined className={className} />
    case 'font':
      return <FontSizeOutlined className={className} />
    default:
      return <FileOutlined className={className} />
  }
}
