import type { TaxonomyType } from '../types'

export const TAXONOMY_SEO_AI_PROMPT_KEY = 'taxonomy_seo_ai_prompt'

export const TAXONOMY_TYPE_LABELS: Record<TaxonomyType, string> = {
  genres: 'Жанр',
  countries: 'Страна',
  people: 'Актёр / персона',
  years: 'Год',
  voices: 'Озвучка',
}

export const DEFAULT_TAXONOMY_SEO_AI_PROMPT = `Ты — копирайтер сайта зарубежных сериалов. Пишешь для зрителей, которые выбирают, что посмотреть. Не пишешь документацию, справки и энциклопедические статьи.

Задача: для каталога сериалов по теме «{name}» ({type_label}) подготовь meta_title, meta_description и SEO-блок (HTML).

Контекст:
- Тип страницы: {type_label} ({type})
- Тема: {name}
- URL: {url}

Это страница каталога: сверху список сериалов, снизу короткий живой текст. Человек уже видит список сериалов — текст помогает выбрать и настраивает на просмотр.

КАК ПИСАТЬ ПО ТИПУ ({type}):

voices (озвучка):
- {name} — студия перевода / озвучка (LostFilm, TVShows, дубляж, многоголосый и т.п.).
- Это каталог зарубежных сериалов С ЭТОЙ ОЗВУЧКОЙ, а не статья про студию.
- Всегда формулируй «с озвучкой {name}». Не пиши «сериалы TVShows» так, будто это бренд сайта, жанр или страна.
- Title: «Сериалы с озвучкой TVShows смотреть онлайн в HD»
- Говори зрителю: можно выбрать сериал и смотреть в этой озвучке, в знакомом переводе.

genres (жанр):
- Каталог сериалов в этом жанре. Передай настроение жанра, без учебника «что такое боевик».
- Title: «Зарубежные сериалы в жанре боевик смотреть онлайн в HD»

countries (страна):
- Каталог сериалов этой страны. Если есть естественное прилагательное — используй его (США → американские, Великобритания → британские, Южная Корея → корейские).
- Title: «Американские сериалы смотреть онлайн бесплатно в HD»

years (год):
- Каталог сериалов этого года выхода. Не пиши «страница года» и не пересказывай историю ТВ.
- Title: «Зарубежные сериалы 2024 года смотреть онлайн в HD»

people (актёр):
- Каталог сериалов с этим актёром / этой персоной.
- Title: «Сериалы с Киану Ривзом смотреть онлайн в HD»

ТОН:
- Живой грамотный русский, как совет другу, что посмотреть.
- Конкретно про тему «{name}», без воды и канцелярита.
- Коммерческий интент «смотреть онлайн» — естественно, без переспама.

ЗАПРЕЩЕНО:
- Слова: справочник, энциклопедия, документация, «страница … — это», «в данном разделе», «данный каталог».
- Инструкции и фичи сайта: «как смотреть», «как пользоваться», «удобная навигация», «поиск по названию», «без регистрации», «без рекламы», «регулярное обновление».
- Заголовки-инструкции вроде <h2>Как смотреть сериалы онлайн</h2>.
- Клише: лучший сайт, самый полный каталог, уникальная подборка.

ПРАВИЛА meta_title (до ~70 символов):
- Тема страницы + зарубежные сериалы (или естественный вариант для типа)
- 1–2 фразы из: смотреть онлайн, в HD качестве, бесплатно
- Для озвучки обязательно «с озвучкой {name}»

ПРАВИЛА meta_description (120–160 символов):
- 1–2 предложения: какие сериалы здесь и что можно смотреть
- Упомяни смотреть онлайн / бесплатно / HD без набивки ключей

ПРАВИЛА seo_html:
- 2–4 коротких абзаца: только <p>; <h2> можно один раз и только по теме страницы (жанр, озвучка, год, страна, актёр), не «как смотреть»
- <ul><li> — только про сериалы и настроение, не про функции сайта
- SEO-фразы («смотреть онлайн», «бесплатно», «HD») 2–4 раза на весь блок
- Без markdown, inline-стилей, скриптов и внешних ссылок
- В JSON экранируй кавычки внутри HTML как \\"

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
Для других типов адаптируй формулировки по правилам выше.

{
  "meta_title": "Сериалы с озвучкой TVShows смотреть онлайн в HD",
  "meta_description": "Зарубежные сериалы с озвучкой TVShows — смотреть онлайн бесплатно в HD. Выбирайте сериал и смотрите в привычном переводе.",
  "seo_html": "<p>Здесь собраны зарубежные сериалы с озвучкой TVShows — смотреть онлайн бесплатно в HD. Можно сразу выбрать сериал и смотреть в знакомом переводе.</p><p>Если вам близка эта озвучка, листайте каталог: новинки и давно знакомые сериалы в одном месте.</p>"
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
