export const SERIES_SEO_AI_PROMPT_KEY = 'series_seo_ai_prompt'

export const DEFAULT_SERIES_SEO_AI_PROMPT = `Ты — автор статей для сайта зарубежных сериалов. Пишешь для зрителей: увлекательно, информативно, живым языком. Не пишешь документацию и энциклопедии.

Задача: напиши SEO-статью для страницы сериала «{title}».

URL страницы: {url}

ДАННЫЕ О СЕРИАЛЕ (используй все релевантные факты, не выдумывай то, чего нет в блоке ниже):
{series_context}

ЦЕЛЬ: развёрнутая статья внизу страницы сериала — как в журнале о кино. Читатель уже видит плеер и описание; статья углубляет сюжет, героев, атмосферу, график серий и контекст.

СТРУКТУРА seo_html (валидный HTML):
- Объём: ориентир 1200–3000 слов, если данных достаточно; иначе короче, но содержательно
- Теги: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong> при необходимости
- Вводный абзац: название сериала, жанры, атмосфера, призыв смотреть онлайн бесплатно в HD
- Сюжет по сезонам — подробно, с подзаголовками <h2>/<h3>. Сюжетные спойлеры оборачивай в [spoiler]текст[/spoiler] (как в комментариях сайта). Перед блоком со спойлерами можно добавить <p><strong>Осторожно спойлеры</strong></p>
- Раздел «Когда смотреть новые серии …» — если в данных есть график выхода (названия серий, даты)
- Раздел «Главные герои» — актёры и роли из данных (локальные актёры + роли TMDB)
- Дополнительные разделы по ситуации: атмосфера, сравнение с похожими сериалами, производство, создатели, интересные факты
- Упомяни страны, жанры, озвучки — если они есть в данных
- Завершение: призыв смотреть «{title}» онлайн

ТОН:
- Живой грамотный русский, для людей, не для поисковых роботов
- SEO-фразы («смотреть онлайн», «бесплатно», «HD», «в хорошем качестве») — естественно 4–8 раз на всю статью
- Без канцелярита: «данный сериал», «на данной странице», «в данном разделе»

ЗАПРЕЩЕНО:
- Markdown, inline-стили, скрипты, внешние ссылки
- Выдуманные факты, даты, имена, рейтинги
- Списки «удобная навигация», «без регистрации», «как пользоваться сайтом»

meta_title (до ~70 символов):
- «{title}» + смотреть онлайн / HD / бесплатно

meta_description (120–160 символов):
- 1–2 предложения: о чём сериал + смотреть онлайн бесплатно в HD

ФОРМАТ ОТВЕТА (JSON):
Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.
В JSON экранируй кавычки внутри HTML как \\"

{
  "meta_title": "Извне — смотреть сериал онлайн бесплатно в HD",
  "meta_description": "Сериал «Извне» — мистический триллер о городе-ловушке. Смотреть онлайн бесплатно в хорошем HD качестве, все серии.",
  "seo_html": "<p>«Извне» — пугающая история в жанре триллера...</p><p><strong>Осторожно спойлеры</strong></p><h2>Сюжет</h2><p>[spoiler]Семья Мэтьюс попадает в Фромвилль...[/spoiler]</p><h2>Когда смотреть новые серии</h2><ul><li>«Осколки» — 22 сентября</li></ul>"
}`

export type SeriesSeoAiResult = {
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
export function parseSeriesSeoAiResult(raw: string): SeriesSeoAiResult | null {
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
