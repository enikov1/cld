import type { TplHint } from '../types/tplDocs'
import { filterHints } from '../types/tplDocs'

export type TplEditorHandle = {
  insertAtCursor: (text: string) => void
}

export type TplToken = {
  from: number
  to: number
  insert: string
  kind: 'variable' | 'tag'
}

const tplVarTokenRe = /\{[A-Za-z0-9_.]+(?:\|raw)?\}/g
const tplTagTokenRe = /\[(?:meta-[a-z-]+|not-[A-Za-z0-9_.]+|loop\s+[A-Za-z0-9_.]+|\/[A-Za-z0-9_.-]+|[A-Za-z0-9_.]+)\]/g

export function findTplTokenAt(doc: { lineAt: (pos: number) => { from: number; text: string } }, pos: number): TplToken | null {
  const line = doc.lineAt(pos)
  const linePos = pos - line.from

  tplVarTokenRe.lastIndex = 0
  let match = tplVarTokenRe.exec(line.text)
  while (match) {
    const start = match.index
    const end = start + match[0].length
    if (linePos >= start && linePos <= end) {
      return {
        from: line.from + start,
        to: line.from + end,
        insert: match[0],
        kind: 'variable',
      }
    }
    match = tplVarTokenRe.exec(line.text)
  }

  tplTagTokenRe.lastIndex = 0
  match = tplTagTokenRe.exec(line.text)
  while (match) {
    const start = match.index
    const end = start + match[0].length
    if (linePos >= start && linePos <= end) {
      return {
        from: line.from + start,
        to: line.from + end,
        insert: match[0],
        kind: 'tag',
      }
    }
    match = tplTagTokenRe.exec(line.text)
  }

  return null
}

export function buildHintIndex(hints: TplHint[]): Map<string, TplHint> {
  const index = new Map<string, TplHint>()

  for (const hint of hints) {
    index.set(hint.insert, hint)

    if (hint.kind === 'variable' && hint.insert.endsWith('}') && !hint.insert.includes('|raw')) {
      index.set(hint.insert.replace('}', '|raw}'), hint)
    }

    if (hint.kind === 'variable' && hint.insert.includes('|raw')) {
      index.set(hint.insert.replace('|raw', ''), hint)
    }
  }

  return index
}

export function lookupHint(index: Map<string, TplHint>, insert: string): TplHint | undefined {
  return index.get(insert) ?? index.get(insert.replace('|raw}', '}')) ?? index.get(insert.replace('}', '|raw}'))
}

export function buildInsert(hint: TplHint): string {
  if (hint.kind === 'variable' || hint.kind === 'snippet') {
    return hint.insert
  }

  if (hint.kind === 'loop') {
    const list = hint.insert.replace(/^\[loop\s+/, '').replace(/\]$/, '')
    return `[loop ${list}]\n  \n[/loop]`
  }

  if (hint.kind === 'meta') {
    const tag = hint.insert.replace(/^\[/, '').replace(/\]$/, '')
    return `[${tag}]\n  \n[/${tag}]`
  }

  if (hint.insert.startsWith('[not-')) {
    const name = hint.insert.slice(5, -1)
    return `[not-${name}]\n  \n[/not-${name}]`
  }

  const name = hint.insert.replace(/^\[/, '').replace(/\]$/, '')
  return `[${name}]\n  \n[/${name}]`
}

export function completionOptions(
  hints: TplHint[],
  contexts: string[],
  query: string,
  mode: 'variable' | 'tag',
  prefiltered = false,
) {
  const pool =
    mode === 'variable' ? hints.filter((h) => h.kind === 'variable') : hints.filter((h) => h.kind !== 'variable')

  const filtered = prefiltered
    ? pool.filter((hint) => {
        if (!query) return true
        const q = query.toLowerCase()
        return (
          hint.label.toLowerCase().includes(q) ||
          (hint.detail ?? '').toLowerCase().includes(q) ||
          (hint.sample ?? '').toLowerCase().includes(q)
        )
      })
    : filterHints(pool, contexts, query)

  return filtered.slice(0, 20)
}

export function editorLanguage(path: string | null): 'tpl' | 'css' | 'javascript' | 'text' {
  if (!path) return 'text'
  const lower = path.toLowerCase()
  if (lower.endsWith('.css')) return 'css'
  if (lower.endsWith('.js')) return 'javascript'
  if (lower.endsWith('.tpl') || lower.endsWith('.svg')) return 'tpl'
  return 'text'
}
