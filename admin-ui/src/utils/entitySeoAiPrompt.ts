export type EntitySeoAiPromptVars = {
  name: string
  slug: string
  url: string
}

export type EntitySeoAiResult = {
  meta_title: string
  description: string
  meta_description: string
  seo_html: string
}

export const STUDIO_SEO_AI_PROMPT_KEY = 'studio_seo_ai_prompt'
export const COLLECTION_SEO_AI_PROMPT_KEY = 'collection_seo_ai_prompt'

export const DEFAULT_STUDIO_SEO_AI_PROMPT = `Ты — SEO-редактор каталога зарубежных сериалов.

Задача: для страницы студии «{name}» подготовь meta_title, description, meta_description и SEO-блок (HTML) для сайта зарубежных сериалов.

Контекст страницы:
- Студия: {name}
- URL: {url}

Цель: тексты должны быть полезными для людей и дружелюбными к Яндексу/Google — естественные формулировки, коммерческий интент «смотреть онлайн», без переспама и воды.

ПРАВИЛА meta_title (до ~70 символов):
- Включи название студии и акцент на зарубежные сериалы
- Естественно встрой 1–2 фразы: смотреть онлайн, в HD качестве, бесплатно
- Пример: «Сериалы USA Network смотреть онлайн в HD качестве бесплатно»

ПРАВИЛА description (2–4 предложения, обычный текст без HTML):
- Живое описание студии и её зарубежных сериалов
- Без обязательных SEO-штампов в каждом предложении
- Без markdown и кавычек-ёлочек

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: что пользователь найдёт на странице студии
- Упомяни зарубежные сериалы и смотреть онлайн / бесплатно / HD без набивки ключей
- Без клише вроде «лучший сайт»

ПРАВИЛА seo_html:
- 2–4 абзаца валидного HTML: только <p>, при необходимости <h2> и <ul><li>
- Полезный текст про зарубежные сериалы студии «{name}»
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») используй естественно 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
{
  "meta_title": "Сериалы USA Network смотреть онлайн в HD качестве бесплатно",
  "description": "USA Network — студия с узнаваемыми зарубежными сериалами: динамичные сюжеты, сильные герои и сезоны, которые удобно смотреть подряд.",
  "meta_description": "Зарубежные сериалы USA Network — смотреть онлайн бесплатно в хорошем HD качестве. Новые серии и популярные тайтлы студии.",
  "seo_html": "<p>На странице собраны зарубежные сериалы студии USA Network — смотреть онлайн бесплатно в HD.</p><p>Выбирайте тайтлы студии и следите за новыми сериями.</p>"
}`

export const DEFAULT_COLLECTION_SEO_AI_PROMPT = `Ты — SEO-редактор каталога зарубежных сериалов.

Задача: для страницы подборки «{name}» подготовь meta_title, description, meta_description и SEO-блок (HTML) для сайта зарубежных сериалов.

Контекст страницы:
- Подборка: {name}
- URL: {url}

Цель: тексты должны быть полезными для людей и дружелюбными к Яндексу/Google — естественные формулировки, коммерческий интент «смотреть онлайн», без переспама и воды.

ПРАВИЛА meta_title (до ~70 символов):
- Включи тему подборки и акцент на зарубежные сериалы
- Естественно встрой 1–2 фразы: смотреть онлайн, в HD качестве, бесплатно
- Пример: «Сериалы про вампиров — смотреть онлайн в HD качестве бесплатно»

ПРАВИЛА description (2–4 предложения, обычный текст без HTML):
- Живое тематическое описание подборки зарубежных сериалов
- Без обязательных SEO-штампов в каждом предложении
- Без markdown и кавычек-ёлочек

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: что пользователь найдёт в подборке
- Упомяни зарубежные сериалы и смотреть онлайн / бесплатно / HD без набивки ключей
- Без клише вроде «лучший сайт»

ПРАВИЛА seo_html:
- 2–4 абзаца валидного HTML: только <p>, при необходимости <h2> и <ul><li>
- Полезный текст про зарубежные сериалы по теме «{name}»
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») используй естественно 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
{
  "meta_title": "Сериалы про вампиров — смотреть онлайн в HD качестве бесплатно",
  "description": "Подборка зарубежных сериалов про вампиров: мрачная атмосфера, сильные характеры и истории, в которых магия соседствует с повседневностью.",
  "meta_description": "Подборка зарубежных сериалов про вампиров — смотреть онлайн бесплатно в хорошем HD качестве. Атмосферные истории и новые серии.",
  "seo_html": "<p>В подборке собраны зарубежные сериалы про вампиров — смотреть онлайн бесплатно в HD.</p><p>Выбирайте тайтлы по настроению и следите за новыми сериями.</p>"
}`

export function fillEntitySeoAiPrompt(template: string, vars: EntitySeoAiPromptVars): string {
  return template
    .replaceAll('{name}', vars.name)
    .replaceAll('{slug}', vars.slug)
    .replaceAll('{url}', vars.url)
    .trim()
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
export function parseEntitySeoAiResult(raw: string): EntitySeoAiResult | null {
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
      description: pickString(parsed.description),
      meta_description: pickString(parsed.meta_description),
      seo_html: pickString(parsed.seo_html),
    }

    if (result.meta_title || result.description || result.meta_description || result.seo_html) {
      return result
    }
  }

  return null
}

type SettingRow = { key: string; value: string }

export async function loadSeoPromptTemplate(
  apiGet: <T>(url: string) => Promise<T>,
  settingKey: string,
  fallback: string,
): Promise<string> {
  const data = await apiGet<{ items: SettingRow[] }>('/api/admin/settings')
  const value = data.items.find((row) => row.key === settingKey)?.value?.trim()
  return value || fallback
}
