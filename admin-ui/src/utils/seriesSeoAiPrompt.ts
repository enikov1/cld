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
- ВАЖНО ПО РАЗМЕТКЕ: итоговый HTML в поле seo_html должен быть одним самостоятельным блоком:
  - корневой контейнер: <div class="series-seo-article"> ... </div>
  - ввод: <p class="series-seo-article__lead">...</p>
  - каждый раздел оборачивай в <section class="series-seo-article__section"> ... </section>
  - заголовки разделов: <h2> или <h3> внутри соответствующего <section>
- Сюжет по сезонам — подробно, с подзаголовками <h2>/<h3>. Сюжетные спойлеры оборачивай в [spoiler]текст[/spoiler].
- Перед блоком со спойлерами вставь подсказку строго так: <p class="comments-compose__hint"><strong>Осторожно, спойлеры!</strong></p>
- Раздел «Когда смотреть новые серии» — если в данных есть график выхода (названия серий, даты): используй <ul> или <ol> и <li>.
- Раздел «Главные герои» — используй <ul> с <li>.
- Дополнительные разделы по ситуации: атмосфера, производство, создатели, интересные факты.
- Завершение: короткий нейтральный финал про сериал (без агрессивного CTA и без домена).

ТОН:
- Живой грамотный русский, для людей, не для поисковых роботов
- SEO-фразы («смотреть онлайн», «бесплатно», «HD», «в хорошем качестве») — только когда это уместно и без повторов. Ориентир: в тексте 2–4 раза суммарно, без «простыней» и без вставок в каждый абзац.
- Без канцелярита: «данный сериал», «на данной странице», «в данном разделе»

ЗАПРЕЩЕНО:
- Markdown, inline-стили, скрипты, внешние ссылки
- Выдуманные факты, даты, имена, рейтинги
- Списки «удобная навигация», «без регистрации», «как пользоваться сайтом»
- Оценочные штампы без источника: «лучший», «самый», «звёздный состав», «талантливый», «мастерски», «высокое качество», «миллионы зрителей», «сердца зрителей», «идеальный выбор», «невероятный», «потрясающий» и т.п.
- Агрессивные CTA и рекламные фразы: «вам самое время», «вас ждут», «прямо сейчас», «приятного просмотра», «не откладывайте», «выберите любую серию» и т.п.
- Повтор названия сайта/бренда, домена или «где смотреть» в каждом разделе. Упоминай просмотр только в вводе 1 раз и в финале 1 раз (если не указано иначе).
- Не уводи статью в политическую, ЛГБТ- или расовую повестку. Не акцентируй это как тему статьи и не добавляй оценочных рассуждений на эти темы. Упоминать можно только если это прямо важно для сюжета, персонажа или исходных данных, и тогда — коротко, нейтрально, без морализаторства и без активистской риторики.

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
