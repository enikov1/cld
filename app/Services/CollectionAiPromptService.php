<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Genre;
use App\Models\Series;

class CollectionAiPromptService
{
    private const SUGGESTED_COLLECTIONS_MIN = 5;

    private const SUGGESTED_COLLECTIONS_MAX = 15;

    private const DESCRIPTION_LIMIT = 400;

    /**
     * @return array{prompt: string, series_count: int, collections_count: int, char_count: int}
     */
    public function build(): array
    {
        $collections = Collection::query()
            ->withCount('items')
            ->orderBy('title')
            ->get();

        $collectionSeriesMap = $this->collectionSeriesMap($collections->pluck('id')->all());

        $series = Series::query()
            ->published()
            ->with(['genres:id,name'])
            ->orderBy('title')
            ->get(['id', 'title', 'title_original', 'year', 'description', 'short_description']);

        $parts = [
            $this->instructionsBlock(),
            '',
            $this->existingCollectionsBlock($collections, $collectionSeriesMap),
            '',
            $this->forbiddenTopicsBlock(),
            '',
            $this->seoBlock(),
            '',
            $this->schemaBlock(),
            '',
            'КАТАЛОГ СЕРИАЛОВ',
            '================',
            '',
        ];

        foreach ($series as $item) {
            $parts[] = $this->formatSeriesEntry($item);
            $parts[] = '---';
            $parts[] = '';
        }

        $prompt = rtrim(implode("\n", $parts));

        return [
            'prompt' => $prompt,
            'series_count' => $series->count(),
            'collections_count' => $collections->count(),
            'char_count' => mb_strlen($prompt),
        ];
    }

    private function instructionsBlock(): string
    {
        return implode("\n", [
            'Ты — редактор каталога сериалов. На основе каталога ниже предложи тематические подборки для сайта.',
            '',
            'ПРАВИЛА:',
            '- Предложи от ' . self::SUGGESTED_COLLECTIONS_MIN . ' до ' . self::SUGGESTED_COLLECTIONS_MAX . ' **новых** подборок.',
            '- Сначала изучи блок СУЩЕСТВУЮЩИЕ ПОДБОРКИ — не создавай дубликаты по теме, названию или slug.',
            '- Запрещено предлагать подборки с похожей темой: «Про вампиров» ≈ «Сериалы про вампиров» ≈ «Вампиры».',
            '- Если тема уже есть — пропусти её и предложи другую.',
            '- Каждый сериал включай максимум в 2–3 подборки.',
            '- Используй только series_ids из каталога (поле [ID:...]).',
            '- Подборки должны быть тематическими и логичными для пользователя сайта.',
            '- ЗАПРЕЩЕНО делать подборки по жанрам — на сайте уже есть отдельные страницы жанров (см. блок ЗАПРЕЩЁННЫЕ ТЕМЫ).',
            '- Не создавай подборки вида «Триллеры», «Детективы», «Комедии», «Триллеры и детективы», «Лучшие драмы».',
            '- Хорошие темы: сюжет, эпоха, профессия, место, мифология, явление («Про вампиров», «Сериалы про 1980-е», «Про врачей»).',
            '- slug — только латиница, цифры и дефис (например: pro-vampirov).',
            '- auto_keywords — слова для автодобавления сериалов по описанию (2–5 слов).',
            '- description — текст для страницы подборки: живой, тематический, без SEO-штампов.',
            '- meta_title и meta_description — ОБЯЗАТЕЛЬНЫ, с SEO-ключами (см. блок SEO).',
            '- Ответ верни СТРОГО в формате JSON без markdown, без пояснений до или после JSON.',
        ]);
    }

    private function forbiddenTopicsBlock(): string
    {
        $genres = Genre::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $lines = [
            'ЗАПРЕЩЁННЫЕ ТЕМЫ (это уже есть как жанры на сайте)',
            '=================================================',
            '',
            'Не создавай подборки, которые по сути дублируют жанровый каталог:',
            '- один жанр: «Триллеры», «Детективы», «Мелодрамы»',
            '- несколько жанров: «Триллеры и детективы», «Комедии и мелодрамы»',
            '- «Лучшие …» / «Топ …» только по жанру',
            '',
            'ПЛОХО: «Триллеры и детективы», «Сериалы в жанре фантастика», «Драмы»',
            'ХОРОШО: «Про расследования в маленьком городе», «Сериалы про 1980-е», «Про вампиров»',
        ];

        if ($genres !== []) {
            $lines[] = '';
            $lines[] = 'Жанры на сайте (не используй как название/тему подборки):';
            $lines[] = implode(', ', $genres);
        }

        return implode("\n", $lines);
    }

    private function seoBlock(): string
    {
        return implode("\n", [
            'SEO ДЛЯ meta_title И meta_description',
            '===================================',
            '',
            'Обязательные SEO-фразы (используй естественно, 2–4 из списка в каждом поле):',
            '- смотреть онлайн',
            '- бесплатно',
            '- в хорошем качестве',
            '- в HD качестве',
            '- HD качество',
            '',
            'meta_title (до ~70 символов):',
            '- Формат: «{тема подборки} — смотреть онлайн в HD качестве бесплатно»',
            '- Пример: «Сериалы про 1980-е годы — смотреть онлайн в HD качестве бесплатно»',
            '',
            'meta_description (до ~160 символов):',
            '- Начни с темы подборки, затем SEO-фразы',
            '- Пример: «Подборка сериалов про 1980-е годы — смотреть онлайн бесплатно в хорошем HD качестве. Загадочные события, атмосфера эпохи перемен.»',
            '',
            'description (контент страницы):',
            '- Без обязательных SEO-фраз «смотреть онлайн», «бесплатно», «HD»',
            '- Пример: «Действие этих сериалов разворачивается в 1980-х годах — эпохе больших перемен, когда строились небоскребы, а в маленьких городках происходили загадочные события.»',
        ]);
    }

    private function schemaBlock(): string
    {
        return implode("\n", [
            'ФОРМАТ ОТВЕТА (JSON):',
            '{',
            '  "collections": [',
            '    {',
            '      "title": "Сериалы про 1980-е годы",',
            '      "slug": "serialy-pro-1980-e",',
            '      "description": "Действие этих сериалов разворачивается в 1980-х годах — эпохе больших перемен, когда строились небоскребы, а в маленьких городках происходили загадочные события.",',
            '      "meta_title": "Сериалы про 1980-е годы — смотреть онлайн в HD качестве бесплатно",',
            '      "meta_description": "Подборка сериалов про 1980-е годы — смотреть онлайн бесплатно в хорошем HD качестве. Загадочные события и атмосфера эпохи перемен.",',
            '      "auto_keywords": ["ключевое", "слово"],',
            '      "series_ids": [1, 2, 3]',
            '    }',
            '  ]',
            '}',
        ]);
    }

    /**
     * @param  list<int>  $collectionIds
     * @return array<int, list<int>>
     */
    private function collectionSeriesMap(array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $map = [];
        $rows = CollectionItem::query()
            ->whereIn('collection_id', $collectionIds)
            ->orderBy('rank_order')
            ->get(['collection_id', 'series_id']);

        foreach ($rows as $row) {
            $map[(int) $row->collection_id][] = (int) $row->series_id;
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Collection>  $collections
     * @param  array<int, list<int>>  $collectionSeriesMap
     */
    private function existingCollectionsBlock($collections, array $collectionSeriesMap): string
    {
        $lines = [
            'СУЩЕСТВУЮЩИЕ ПОДБОРКИ (НЕ ДУБЛИРОВАТЬ!)',
            '========================================',
            '',
            'Эти подборки уже есть на сайте. Не создавай повторы и близкие по смыслу варианты.',
        ];

        if ($collections->isEmpty()) {
            $lines[] = '';
            $lines[] = '(пока нет — можно предложить любые новые темы)';

            return implode("\n", $lines);
        }

        $lines[] = '';
        $lines[] = 'Список для справки (title, slug, описание, keywords, сериалы):';
        $lines[] = '';

        foreach ($collections as $collection) {
            $seriesIds = $collectionSeriesMap[(int) $collection->id] ?? [];
            $keywords = is_array($collection->auto_keywords)
                ? implode(', ', $collection->auto_keywords)
                : '';

            $description = trim((string) ($collection->description ?? ''));
            if (mb_strlen($description) > 200) {
                $description = mb_substr($description, 0, 200) . '…';
            }

            $status = [];
            if (!$collection->is_active) {
                $status[] = 'неактивна';
            }
            if ($collection->is_hidden) {
                $status[] = 'скрыта';
            }
            $statusPart = $status !== [] ? ' [' . implode(', ', $status) . ']' : '';

            $lines[] = '• ' . $collection->title . $statusPart;
            $lines[] = '  slug: ' . $collection->slug;
            if ($description !== '') {
                $lines[] = '  описание: ' . $description;
            }
            if ($keywords !== '') {
                $lines[] = '  auto_keywords: ' . $keywords;
            }
            $lines[] = '  сериалов: ' . count($seriesIds) . ($seriesIds !== [] ? ' | series_ids: [' . implode(', ', $seriesIds) . ']' : '');
            $lines[] = '';
        }

        $jsonItems = $collections->map(function (Collection $collection) use ($collectionSeriesMap) {
            return [
                'title' => $collection->title,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'auto_keywords' => $collection->auto_keywords,
                'series_ids' => $collectionSeriesMap[(int) $collection->id] ?? [],
            ];
        })->values()->all();

        $lines[] = 'JSON существующих подборок (для точной проверки дубликатов):';
        $lines[] = json_encode(['existing_collections' => $jsonItems], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return implode("\n", $lines);
    }

    private function formatSeriesEntry(Series $series): string
    {
        $year = $series->year ?: $series->start_year;
        $yearPart = $year ? ' (' . $year . ')' : '';
        $genres = $series->genres->pluck('name')->filter()->implode(', ');
        $genrePart = $genres !== '' ? ' | жанры: ' . $genres : '';

        $description = trim((string) ($series->short_description ?: $series->description ?: ''));
        if (mb_strlen($description) > self::DESCRIPTION_LIMIT) {
            $description = mb_substr($description, 0, self::DESCRIPTION_LIMIT) . '…';
        }

        $titleOriginal = trim((string) ($series->title_original ?? ''));
        $originalPart = $titleOriginal !== '' && $titleOriginal !== $series->title
            ? ' / ' . $titleOriginal
            : '';

        $lines = [
            '[ID:' . $series->id . '] ' . $series->title . $originalPart . $yearPart . $genrePart,
        ];

        if ($description !== '') {
            $lines[] = 'Описание: ' . $description;
        }

        return implode("\n", $lines);
    }
}
