export type TplHint = {
  insert: string
  label: string
  kind: 'variable' | 'block' | 'loop' | 'meta' | 'snippet'
  detail?: string
  sample?: string
  contexts?: string[]
}

export type TplSyntaxExample = {
  code: string
  note?: string
}

export type TplSyntaxSection = {
  id: string
  title: string
  description: string
  examples: TplSyntaxExample[]
}

export type TplVariable = {
  name: string
  description: string
  sample?: string
}

export type TplFlag = {
  name: string
  syntax: string
}

export type TplLoop = {
  syntax: string
  description: string
}

export type TplContext = {
  title: string
  description: string
  variables: TplVariable[]
  flags: TplFlag[]
  not_flags: TplFlag[]
  loops: TplLoop[]
  hint_contexts?: string[]
}

export type TplDocsPayload = {
  syntax: TplSyntaxSection[]
  contexts: Record<string, TplContext>
  hints: TplHint[]
  active_contexts?: string[]
  samples?: Record<string, string>
  sample_source?: string
}

export function contextsForPath(path: string | null): string[] {
  if (!path) return ['global']
  const p = path.replace(/\\/g, '/')
  const map: Record<string, string[]> = {
    'layout.tpl': ['layout', 'global'],
    'home.tpl': ['home', 'global'],
    'catalog.tpl': ['catalog', 'global'],
    'search.tpl': ['search', 'global'],
    'series/show.tpl': ['series', 'global'],
    'collections/index.tpl': ['collections', 'global'],
    'collections/show.tpl': ['collections', 'global'],
    'studios/index.tpl': ['studios', 'global'],
    'studios/show.tpl': ['studios', 'global'],
    'profile/show.tpl': ['profile', 'global'],
    'partials/reactions_widget.tpl': ['reactions', 'series', 'global'],
  }
  if (map[p]) return map[p]
  if (p.startsWith('errors/')) return ['errors', 'global']
  if (p.startsWith('partials/')) return ['partials', 'global']
  if (p.startsWith('series/')) return ['series', 'global']
  if (p.startsWith('collections/')) return ['collections', 'global']
  if (p.startsWith('studios/')) return ['studios', 'global']
  return ['global']
}

export function filterHints(hints: TplHint[], contexts: string[], query: string, kind?: string): TplHint[] {
  const q = query.toLowerCase()
  return hints.filter((hint) => {
    if (kind && hint.kind !== kind) return false
    const ctx = hint.contexts ?? []
    if (ctx.length > 0 && !ctx.some((c) => contexts.includes(c))) return false
    if (!q) return true
    return hint.label.toLowerCase().includes(q) || (hint.detail ?? '').toLowerCase().includes(q) || (hint.sample ?? '').toLowerCase().includes(q)
  })
}
