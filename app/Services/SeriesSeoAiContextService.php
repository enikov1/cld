<?php

namespace App\Services;

use App\Models\Series;
use App\Support\ContentTypes;
use App\Support\TaxonomyRegistry;
use Carbon\Carbon;

class SeriesSeoAiContextService
{
    private const MAX_EPISODE_OVERVIEWS = 24;

    public function __construct(
        private readonly TmdbClient $tmdbClient,
    ) {
    }

    /**
     * @return array{context: string, warnings: list<string>}
     */
    public function build(Series $series, string $publicUrl): array
    {
        $series->loadMissing([
            'genres',
            'countries',
            'actors',
            'directors',
            'voices',
            'studio',
            'studios',
            'collections',
        ]);

        $warnings = [];
        $lines = [];

        $lines[] = '=== ОСНОВНОЕ ===';
        $lines[] = 'Название: ' . $series->title;
        if (trim((string) $series->title_original) !== '') {
            $lines[] = 'Оригинальное название: ' . $series->title_original;
        }
        if (trim((string) $series->title_en) !== '') {
            $lines[] = 'Название (EN): ' . $series->title_en;
        }
        $lines[] = 'URL страницы: ' . $publicUrl;
        $lines[] = 'Slug: ' . $series->slug;
        $lines[] = 'Тип контента: ' . ContentTypes::label($series->content_type);

        if ($series->broadcastStatusLabel()) {
            $lines[] = 'Статус эфира: ' . $series->broadcastStatusLabel();
        }

        $yearParts = array_filter([
            $series->start_year ?: $series->year,
            $series->end_year && $series->end_year !== ($series->start_year ?: $series->year)
                ? $series->end_year
                : null,
        ]);
        if ($yearParts !== []) {
            $lines[] = 'Годы: ' . implode('–', $yearParts);
        }

        if (trim((string) $series->slogan) !== '') {
            $lines[] = 'Слоган: ' . $series->slogan;
        }
        if (trim((string) $series->short_description) !== '') {
            $lines[] = 'Краткое описание: ' . $this->oneLine($series->short_description);
        }
        if (trim((string) $series->description) !== '') {
            $lines[] = 'Описание: ' . $this->oneLine($series->description);
        }

        $ratings = [];
        if ($series->kp_rating) {
            $ratings[] = 'КиноПоиск ' . $series->kp_rating
                . ($series->kp_votes_count ? ' (' . $series->kp_votes_count . ' оценок)' : '');
        }
        if ($series->imdb_rating) {
            $ratings[] = 'IMDb ' . $series->imdb_rating
                . ($series->imdb_votes_count ? ' (' . $series->imdb_votes_count . ' оценок)' : '');
        }
        if ($ratings !== []) {
            $lines[] = 'Рейтинги: ' . implode(', ', $ratings);
        }

        if (trim((string) $series->age_limit) !== '') {
            $lines[] = 'Возрастной рейтинг: ' . $series->age_limit;
        }
        if ($series->duration_minutes) {
            $lines[] = 'Длительность серии: ' . $series->duration_minutes . ' мин';
        }
        if (trim((string) $series->channel_name) !== '') {
            $lines[] = 'Канал: ' . $series->channel_name;
        }
        if ($series->premiere_date) {
            $lines[] = 'Премьера: ' . $series->premiere_date->format('d.m.Y');
        }

        $progress = EpisodeProgressService::resolvedProgress($series);
        if ($progress['label'] !== '') {
            $lines[] = 'Текущий прогресс: ' . $progress['label'];
        }

        $nextEpisode = EpisodeProgressService::nextUpcomingReminder($series);
        if ($nextEpisode) {
            $lines[] = 'Ближайшая серия: ' . $nextEpisode['label'];
        }

        $studios = collect();
        if ($series->studio) {
            $studios->put($series->studio->id, $series->studio->title);
        }
        foreach ($series->studios as $studio) {
            $studios->put($studio->id, $studio->title);
        }
        if ($studios->isNotEmpty()) {
            $lines[] = 'Студии / платформы: ' . $studios->values()->implode(', ');
        }

        $this->appendListSection($lines, 'Жанры', $series->genres->map(fn ($g) => TaxonomyRegistry::displayName($g))->all());
        $this->appendListSection($lines, 'Страны', $series->countries->map(fn ($c) => TaxonomyRegistry::displayName($c))->all());
        $this->appendListSection($lines, 'Озвучки (студии перевода)', $series->voices->map(fn ($v) => TaxonomyRegistry::displayName($v))->all());
        $this->appendListSection($lines, 'Актёры', $series->actors->pluck('name')->all());
        $this->appendListSection($lines, 'Режиссёры', $series->directors->pluck('name')->all());

        $collections = $series->collections->pluck('title')->filter()->values()->all();
        if ($collections !== []) {
            $this->appendListSection($lines, 'Подборки', $collections);
        }

        $schedule = EpisodeProgressService::scheduleForSeries($series);
        if ($schedule !== []) {
            $lines[] = '';
            $lines[] = '=== ГРАФИК ВЫХОДА СЕРИЙ ===';
            foreach ($schedule as $season) {
                $seasonNumber = (int) ($season['season_number'] ?? 0);
                $seasonTitle = trim((string) ($season['title'] ?? ''));
                $header = 'Сезон ' . $seasonNumber;
                if ($seasonTitle !== '') {
                    $header .= ' («' . $seasonTitle . '»)';
                }
                $lines[] = $header . ':';

                foreach ($season['episodes'] ?? [] as $episode) {
                    $epNumber = (int) ($episode['episode_number'] ?? 0);
                    $epTitle = trim((string) ($episode['title'] ?? ''));
                    $releaseAt = trim((string) ($episode['release_at'] ?? ''));
                    $status = ($episode['status'] ?? '') === 'scheduled' ? 'запланирована' : 'вышла';
                    $voice = trim((string) ($episode['voice'] ?? ''));

                    $item = '  - Серия ' . $epNumber;
                    if ($epTitle !== '') {
                        $item .= ' «' . $epTitle . '»';
                    }
                    if ($releaseAt !== '') {
                        $item .= ' — ' . $releaseAt;
                    }
                    $item .= ' (' . $status . ')';
                    if ($voice !== '') {
                        $item .= ', озвучка: ' . $voice;
                    }
                    $lines[] = $item;
                }
            }
        }

        $tmdbId = trim((string) $series->tmdb_id);
        if ($tmdbId !== '' && $this->tmdbClient->isConfigured()) {
            $tmdbBlock = $this->buildTmdbBlock($tmdbId, $schedule, $warnings);
            if ($tmdbBlock !== '') {
                $lines[] = '';
                $lines[] = $tmdbBlock;
            }
        } elseif ($tmdbId !== '') {
            $warnings[] = 'API-ключ TMDB не настроен — описания серий из TMDB не добавлены.';
        }

        return [
            'context' => implode("\n", $lines),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $schedule
     * @param  list<string>  $warnings
     */
    private function buildTmdbBlock(string $tmdbId, array $schedule, array &$warnings): string
    {
        $details = $this->tmdbClient->getTvDetails($tmdbId, ['credits', 'keywords']);
        if ($details === [] || !isset($details['id'])) {
            $warnings[] = 'Не удалось получить данные сериала из TMDB.';

            return '';
        }

        $lines = ['=== TMDB (дополнительно) ==='];

        if (trim((string) ($details['tagline'] ?? '')) !== '') {
            $lines[] = 'Слоган TMDB: ' . $this->oneLine((string) $details['tagline']);
        }
        if (trim((string) ($details['overview'] ?? '')) !== '') {
            $lines[] = 'Описание TMDB: ' . $this->oneLine((string) $details['overview']);
        }
        if (isset($details['vote_average']) && (float) $details['vote_average'] > 0) {
            $lines[] = 'Рейтинг TMDB: ' . round((float) $details['vote_average'], 1)
                . ' (' . (int) ($details['vote_count'] ?? 0) . ' голосов)';
        }
        if (trim((string) ($details['status'] ?? '')) !== '') {
            $lines[] = 'Статус TMDB: ' . $details['status'];
        }
        if (trim((string) ($details['first_air_date'] ?? '')) !== '') {
            $lines[] = 'Первая дата эфира: ' . $details['first_air_date'];
        }
        if (trim((string) ($details['last_air_date'] ?? '')) !== '') {
            $lines[] = 'Последняя дата эфира: ' . $details['last_air_date'];
        }
        if (isset($details['number_of_seasons'])) {
            $lines[] = 'Сезонов: ' . (int) $details['number_of_seasons'];
        }
        if (isset($details['number_of_episodes'])) {
            $lines[] = 'Серий: ' . (int) $details['number_of_episodes'];
        }

        $showrunners = [];
        foreach ($details['created_by'] ?? [] as $person) {
            if (!is_array($person)) {
                continue;
            }
            $name = trim((string) ($person['name'] ?? ''));
            if ($name !== '') {
                $showrunners[] = $name;
            }
        }
        if ($showrunners !== []) {
            $lines[] = 'Шоураннеры / создатели: ' . implode(', ', $showrunners);
        }

        $networks = [];
        foreach ($details['networks'] ?? [] as $network) {
            if (!is_array($network)) {
                continue;
            }
            $name = trim((string) ($network['name'] ?? ''));
            if ($name !== '') {
                $networks[] = $name;
            }
        }
        if ($networks !== []) {
            $lines[] = 'Телеканалы / платформы TMDB: ' . implode(', ', $networks);
        }

        $keywords = [];
        $keywordResults = $details['keywords']['results'] ?? $details['keywords']['keywords'] ?? [];
        if (is_array($keywordResults)) {
            foreach ($keywordResults as $keyword) {
                if (!is_array($keyword)) {
                    continue;
                }
                $name = trim((string) ($keyword['name'] ?? ''));
                if ($name !== '') {
                    $keywords[] = $name;
                }
            }
        }
        if ($keywords !== []) {
            $lines[] = 'Ключевые слова TMDB: ' . implode(', ', array_slice($keywords, 0, 20));
        }

        $credits = is_array($details['credits'] ?? null) ? $details['credits'] : [];
        $cast = [];
        foreach (array_slice($credits['cast'] ?? [], 0, 12) as $person) {
            if (!is_array($person)) {
                continue;
            }
            $name = trim((string) ($person['name'] ?? ''));
            $character = trim((string) ($person['character'] ?? ''));
            if ($name === '') {
                continue;
            }
            $cast[] = $character !== '' ? $name . ' (' . $character . ')' : $name;
        }
        if ($cast !== []) {
            $lines[] = 'Актёры TMDB (роли): ' . implode('; ', $cast);
        }

        $episodeOverviews = $this->fetchEpisodeOverviews($tmdbId, $schedule, $details);
        if ($episodeOverviews !== []) {
            $lines[] = '';
            $lines[] = '=== ОПИСАНИЯ СЕРИЙ (TMDB) ===';
            foreach ($episodeOverviews as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $schedule
     * @return list<string>
     */
    private function fetchEpisodeOverviews(string $tmdbId, array $schedule, array $details): array
    {
        $seasonNumbers = $this->seasonNumbersForOverviews($schedule, $details);
        if ($seasonNumbers === []) {
            return [];
        }

        $lines = [];
        $count = 0;

        foreach ($seasonNumbers as $seasonNumber) {
            if ($count >= self::MAX_EPISODE_OVERVIEWS) {
                break;
            }

            $payload = $this->tmdbClient->getTvSeasonDetails($tmdbId, $seasonNumber);
            if ($payload === []) {
                continue;
            }

            foreach ($payload['episodes'] ?? [] as $episode) {
                if ($count >= self::MAX_EPISODE_OVERVIEWS) {
                    break;
                }
                if (!is_array($episode)) {
                    continue;
                }

                $overview = trim((string) ($episode['overview'] ?? ''));
                if ($overview === '') {
                    continue;
                }

                $epNumber = (int) ($episode['episode_number'] ?? 0);
                $epTitle = trim((string) ($episode['name'] ?? ''));
                $airDate = trim((string) ($episode['air_date'] ?? ''));

                $line = 'Сезон ' . $seasonNumber . ', серия ' . $epNumber;
                if ($epTitle !== '') {
                    $line .= ' «' . $epTitle . '»';
                }
                if ($airDate !== '') {
                    $line .= ' (' . $this->formatIsoDate($airDate) . ')';
                }
                $line .= ': ' . $this->oneLine($overview);
                $lines[] = $line;
                $count++;
            }
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $schedule
     * @return list<int>
     */
    private function seasonNumbersForOverviews(array $schedule, array $details): array
    {
        $numbers = [];

        foreach ($schedule as $season) {
            $num = (int) ($season['season_number'] ?? 0);
            if ($num < 1) {
                continue;
            }

            $hasUpcoming = false;
            foreach ($season['episodes'] ?? [] as $episode) {
                if (($episode['status'] ?? '') === 'scheduled') {
                    $hasUpcoming = true;
                    break;
                }
            }

            if ($hasUpcoming) {
                $numbers[$num] = $num;
            }
        }

        $allSeasons = [];
        foreach ($details['seasons'] ?? [] as $season) {
            if (!is_array($season)) {
                continue;
            }
            $num = (int) ($season['season_number'] ?? 0);
            if ($num >= 1) {
                $allSeasons[] = $num;
            }
        }
        rsort($allSeasons);

        foreach (array_slice($allSeasons, 0, 2) as $num) {
            $numbers[$num] = $num;
        }

        if ($numbers === [] && $allSeasons !== []) {
            $numbers[$allSeasons[0]] = $allSeasons[0];
        }

        $result = array_values($numbers);
        sort($result);

        return $result;
    }

    /**
     * @param  list<string>  $items
     * @param  list<string>  $lines
     */
    private function appendListSection(array &$lines, string $title, array $items): void
    {
        $items = array_values(array_filter(array_map('trim', $items)));
        if ($items === []) {
            return;
        }

        $lines[] = '';
        $lines[] = '=== ' . mb_strtoupper($title) . ' ===';
        foreach ($items as $item) {
            $lines[] = '- ' . $item;
        }
    }

    private function oneLine(?string $text): string
    {
        $text = trim((string) $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    private function formatIsoDate(string $iso): string
    {
        try {
            return Carbon::parse($iso)->format('d.m.Y');
        } catch (\Throwable) {
            return $iso;
        }
    }
}
