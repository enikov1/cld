export type AiCollectionProposal = {
  index: number
  title: string
  slug: string
  description?: string
  meta_title?: string
  meta_description?: string
  auto_keywords?: string[]
  series_ids: number[]
  series_count: number
  status: string
  warnings?: string[]
  id?: number
}

export type AiImportPreview = {
  ok: boolean
  dry_run: boolean
  items: AiCollectionProposal[]
  skipped: Array<{ index: number; title: string; slug?: string; reason: string }>
  errors: string[]
  created: number
}

export type AiPromptResponse = {
  ok: boolean
  prompt: string
  series_count: number
  collections_count: number
  char_count: number
}

export function downloadTextFile(filename: string, content: string): void {
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export function formatCharCount(count: number): string {
  if (count >= 1_000_000) {
    return `${(count / 1_000_000).toFixed(1)} млн`
  }
  if (count >= 1000) {
    return `${Math.round(count / 1000)} тыс.`
  }
  return String(count)
}

export const LARGE_PROMPT_THRESHOLD = 500_000
