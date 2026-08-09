import type { TaxonomyType } from '../types'

export const TAXONOMY_SEO_AI_PROMPT_KEY = 'taxonomy_seo_ai_prompt'

export const TAXONOMY_TYPE_LABELS: Record<TaxonomyType, string> = {
  genres: 'Жанр',
  countries: 'Страна',
  people: 'Актёр / персона',
  years: 'Год',
}

export const DEFAULT_TAXONOMY_SEO_AI_PROMPT = `Ты — SEO-редактор каталога зарубежных сериалов.

Задача: для страницы справочника «{name}» ({type_label}) подготовь meta_title, meta_description и SEO-блок (HTML) для сайта зарубежных сериалов.

Контекст страницы:
- Тип справочника: {type_label} ({type})
- Название: {name}
- URL: {url}

Цель: тексты должны быть полезными для людей и дружелюбными к Яндексу/Google — естественные формулировки, коммерческий интент «смотреть онлайн», без переспама и воды.

ПРАВИЛА meta_title (до ~70 символов):
- Включи название справочника и акцент на зарубежные сериалы
- Естественно встрой 1–2 фразы: смотреть онлайн, в HD качестве, бесплатно
- Пример для жанра «Боевик»: «Зарубежные боевики сериалы смотреть онлайн в HD качестве»

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: что пользователь найдёт на странице
- Упомяни зарубежные сериалы и смотреть онлайн / бесплатно / HD без набивки ключей
- Без клише вроде «лучший сайт» и «самый полный каталог»

ПРАВИЛА seo_html:
- 2–4 абзаца валидного HTML: только <p>, при необходимости <h2> и <ul><li>
- Живой полезный текст про зарубежные сериалы по теме «{name}»: о чём страница, кому подойдёт, как смотреть
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») используй естественно 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
{
  "meta_title": "Зарубежные боевики сериалы смотреть онлайн в HD качестве",
  "meta_description": "Зарубежные сериалы в жанре боевик — смотреть онлайн бесплатно в хорошем HD качестве. Динамичные сюжеты и яркие герои.",
  "seo_html": "<p>На странице собраны зарубежные сериалы в жанре боевик — смотреть онлайн бесплатно в HD.</p><p>Выбирайте тайтлы с динамичным сюжетом и следите за новыми сериями.</p>"
}`

export type TaxonomySeoAiPromptVars = {
  name: string
  type: TaxonomyType
  typeLabel: string
  slug: string
  url: string
}

export function fillTaxonomySeoAiPrompt(template: string, vars: TaxonomySeoAiPromptVars): string {
  return template
    .replaceAll('{name}', vars.name)
    .replaceAll('{type_label}', vars.typeLabel)
    .replaceAll('{type}', vars.type)
    .replaceAll('{slug}', vars.slug)
    .replaceAll('{url}', vars.url)
    .trim()
}

export type TaxonomySeoAiResult = {
  meta_title: string
  meta_description: string
  seo_html: string
}

function pickString(value: unknown): string {
  return typeof value === 'string' ? value.trim() : ''
}

function tryParseJsonObject(raw: string): Record<string, unknown> | null {
  try {
    const parsed = JSON.parse(raw) as unknown
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
      ? parsed as Record<string, unknown>
      : null
  } catch {
    return null
  }
}

/** Extract JSON object from AI response (tolerates markdown fences and surrounding text). */
export function parseTaxonomySeoAiResult(raw: string): TaxonomySeoAiResult | null {
  const text = raw.trim()
  if (!text) return null

  const candidates: string[] = []

  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/i)
  if (fenced?.[1]) {
    candidates.push(fenced[1].trim())
  }

  candidates.push(text)

  const firstBrace = text.indexOf('{')
  const lastBrace = text.lastIndexOf('}')
  if (firstBrace !== -1 && lastBrace > firstBrace) {
    candidates.push(text.slice(firstBrace, lastBrace + 1))
  }

  for (const candidate of candidates) {
    const parsed = tryParseJsonObject(candidate)
    if (!parsed) continue

    const result = {
      meta_title: pickString(parsed.meta_title),
      meta_description: pickString(parsed.meta_description),
      seo_html: pickString(parsed.seo_html),
    }

    if (result.meta_title || result.meta_description || result.seo_html) {
      return result
    }
  }

  return null
}
