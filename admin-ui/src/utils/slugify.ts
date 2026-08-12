/** Cyrillic → Latin map close to Laravel Str::slug / ASCII slugger. */
const CYRILLIC_MAP: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z',
  и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
  с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
  ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
  А: 'A', Б: 'B', В: 'V', Г: 'G', Д: 'D', Е: 'E', Ё: 'Yo', Ж: 'Zh', З: 'Z',
  И: 'I', Й: 'Y', К: 'K', Л: 'L', М: 'M', Н: 'N', О: 'O', П: 'P', Р: 'R',
  С: 'S', Т: 'T', У: 'U', Ф: 'F', Х: 'H', Ц: 'Ts', Ч: 'Ch', Ш: 'Sh', Щ: 'Sch',
  Ъ: '', Ы: 'Y', Ь: '', Э: 'E', Ю: 'Yu', Я: 'Ya',
}

function transliterate(value: string): string {
  let out = ''
  for (const ch of value) {
    out += CYRILLIC_MAP[ch] ?? ch
  }
  return out
}

/**
 * URL-safe slug: transliterate Cyrillic, lowercase, keep [a-z0-9-].
 * Empty input → empty string (caller decides fallback).
 */
export function slugify(value: string | null | undefined): string {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  return transliterate(raw)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-')
}

/** Join non-empty slug parts with `-`. */
export function joinSlugParts(...parts: Array<string | null | undefined>): string {
  return parts
    .map((p) => slugify(p))
    .filter(Boolean)
    .join('-')
}
