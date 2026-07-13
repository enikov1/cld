type DescriptionAiPromptInput = {
  title: string
  year?: number | null
  contentType?: 'film' | 'series' | null
  genreNames: string[]
  description: string
}

const CONTENT_TYPE_LABELS: Record<'film' | 'series', string> = {
  film: 'фильма',
  series: 'сериала',
}

const GENRE_HINT_PATTERNS: Array<{ match: RegExp; hint: string }> = [
  {
    match: /мелодрам/i,
    hint: 'Для мелодрамы: эмоциональная связь героев, переживания, романтическая или семейная линия.',
  },
  {
    match: /драм/i,
    hint: 'Для драмы: психологическая глубина, внутренние конфликты, трансформация персонажей.',
  },
  {
    match: /комед/i,
    hint: 'Для комедии: лёгкость, юмор, живые диалоги и неожиданные ситуации.',
  },
  {
    match: /боевик|экшен/i,
    hint: 'Для боевика: динамика, напряжение, яркие события и ставки для героев.',
  },
  {
    match: /триллер/i,
    hint: 'Для триллера: интрига, нарастающее напряжение, ощущение опасности.',
  },
  {
    match: /ужас|хоррор/i,
    hint: 'Для ужасов: тревожная атмосфера, страх, ощущение угрозы.',
  },
  {
    match: /фантаст/i,
    hint: 'Для фантастики: необычный мир, идеи и последствия выбора героев.',
  },
  {
    match: /фэнтез/i,
    hint: 'Для фэнтези: магия, приключения, путь героя и испытания.',
  },
  {
    match: /детектив/i,
    hint: 'Для детектива: загадка, расследование, постепенное раскрытие правды.',
  },
  {
    match: /криминал/i,
    hint: 'Для криминала: моральный выбор, опасный мир, последствия решений.',
  },
  {
    match: /приключен/i,
    hint: 'Для приключений: путь, открытия, препятствия и рост героев.',
  },
]

const PROMPT_RULES = `КРИТИЧЕСКИ ВАЖНЫЕ ПРАВИЛА:
1. Объём: 160 слов (700 символов), ЕДИНЫЙ ТЕКСТ БЕЗ АБЗАЦЕВ И РАЗРЫВОВ
2. ЗАПРЕЩЕНО: markdown (*текст*, **текст**), кавычки («»), тире (—), звездочки, подчеркивания
3. ЗАПРЕЩЕНО упоминать название в тексте - читатель уже знает, о чем речь
4. ЗАПРЕЩЕНО использовать слова: 'фильм', 'сериал', 'картина', 'лента', 'произведение', 'кинолента', 'таким образом', 'в этой истории', 'история'
5. Начинай СРАЗУ С СЮЖЕТА, без вводных конструкций
6. Пиши от третьего лица, как живой человек рассказывает друзьям
7. Используй разговорный стиль, но грамотный
8. Естественные переходы между мыслями без списков и перечислений
9. Фокус: атмосфера, эмоции, суть истории
10. Избегай клише и штампов типа 'захватывающий', 'невероятный', 'потрясающий'
11. Сделай так чтобы начало текста не было похоже на оригинальный.`

const PROMPT_EXAMPLES = `ПРИМЕРЫ НАЧАЛА:

ПЛОХО: 'Вас ждет погружение в завораживающий мир, где...'
ХОРОШО: 'Главный герой работает простым клерком, пока однажды не находит странное письмо...'

ПЛОХО: 'Фильм раскрывает перед зрителем...'
ХОРОШО: 'События начинаются с того, что...'

ПЛОХО: 'Это история о поиске смысла жизни...'
ХОРОШО: 'Человек теряет все и оказывается...'`

function resolveGenreHint(genreNames: string[]): string | null {
  for (const genreName of genreNames) {
    const pattern = GENRE_HINT_PATTERNS.find(({ match }) => match.test(genreName))
    if (pattern) {
      return pattern.hint
    }
  }
  return null
}

export function buildDescriptionAiPrompt(input: DescriptionAiPromptInput): string {
  const contentLabel = CONTENT_TYPE_LABELS[input.contentType ?? 'series']
  const yearPart = input.year ? ` (${input.year} года)` : ''
  const genrePart = input.genreNames.length
    ? ` в жанре ${input.genreNames.join(', ')}`
    : ''

  const intro = `Перепиши и улучши описание для ${contentLabel} '${input.title}'${yearPart}${genrePart}.`
  const source = `Исходное описание для улучшения:\n${input.description.trim()}`
  const genreHint = resolveGenreHint(input.genreNames)
  const parts = [intro, '', source, '', PROMPT_RULES]

  if (genreHint) {
    parts.push('', genreHint)
  }

  parts.push('', PROMPT_EXAMPLES)

  return parts.join('\n').trim()
}
