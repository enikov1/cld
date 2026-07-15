<?php

namespace App\Services;

use App\Models\Series;
use App\Support\SeriesUrl;

class SeriesPreviewService
{
    private const PEOPLE_LIMIT = 12;

    private const DESCRIPTION_LIMIT = 320;

    /**
     * @return array<string, mixed>
     */
    public static function payloadForSeries(Series $series): array
    {
        $series->loadMissing(['genres', 'actors', 'directors']);

        $displayYear = (int)($series->year ?: $series->start_year ?: 0);
        $description = trim((string)($series->short_description ?: $series->description ?: ''));
        $description = strip_tags($description);
        if (mb_strlen($description) > self::DESCRIPTION_LIMIT) {
            $description = mb_substr($description, 0, self::DESCRIPTION_LIMIT) . '…';
        }

        $badges = [];
        if ($displayYear > 0) {
            $badges[] = ['text' => (string)$displayYear, 'mod' => ''];
        }
        $episodeProgress = $series->episodeProgressLabel();
        if ($episodeProgress !== '') {
            $badges[] = ['text' => $episodeProgress, 'mod' => ''];
        }
        if ($series->kp_rating) {
            $badges[] = ['text' => 'КП ' . $series->kp_rating, 'mod' => 'kp'];
        }
        if ($series->imdb_rating) {
            $badges[] = ['text' => 'IMDb ' . $series->imdb_rating, 'mod' => 'imdb'];
        }

        $genresText = $series->genres->pluck('name')->implode(', ');
        $directorsText = $series->directors->take(self::PEOPLE_LIMIT)->pluck('name')->implode(', ');
        $actorsText = $series->actors->take(self::PEOPLE_LIMIT)->pluck('name')->implode(', ');
        $ageLimitLabel = $series->ageLimitLabel() ?? '';

        return [
            'id' => $series->id,
            'title' => $series->title,
            'url' => SeriesUrl::path($series),
            'badges' => $badges,
            'description' => $description,
            'has_meta' => $genresText !== '' || $directorsText !== '' || $actorsText !== '' || $ageLimitLabel !== '',
            'genres_text' => $genresText,
            'directors_text' => $directorsText,
            'actors_text' => $actorsText,
            'age_limit_label' => $ageLimitLabel,
        ];
    }
}
