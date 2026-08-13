<?php

namespace App\Services;

use App\Models\NotificationEvent;
use App\Models\Series;
use App\Support\SeriesUrl;
use App\Support\SiteConfig;

class SeriesCardMapper
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function mapSeries(iterable $items): array
    {
        $seriesList = [];
        foreach ($items as $item) {
            if ($item instanceof Series) {
                $seriesList[] = $item;
            }
        }

        if ($seriesList === []) {
            return [];
        }

        $ids = array_map(static fn (Series $s) => (int)$s->id, $seriesList);
        $topEmojis = ReactionWidgetService::topEmojisForSeriesIds($ids);
        $newEpisodeIds = self::newEpisodeSeriesIds($ids);

        $newEpisodeLabel = SiteConfig::str('card_badge_new_episode_label') ?: 'Новая серия';
        $popularLabel = SiteConfig::str('card_badge_popular_label') ?: 'Популярно';
        $comingSoonLabel = SiteConfig::str('card_badge_coming_soon_label') ?: 'Скоро';

        // Card grids use denormalized series fields (kept in sync by EpisodeProgressService::syncSeries).
        // Avoid scanning all released episodes per section on home/catalog/related.

        $out = [];
        foreach ($seriesList as $s) {
            $id = (int)$s->id;
            $season = $s->season_number ? (int)$s->season_number : null;
            $episode = $s->last_episode_number ? (int)$s->last_episode_number : null;
            $progressLabel = EpisodeProgressService::formatProgressLabel($season, $episode);
            $isComingSoon = (bool)$s->is_coming_soon;
            $premiereCountdown = $isComingSoon ? ($s->premiereCountdownLabel() ?? '') : '';

            $shortDescription = trim((string)($s->short_description ?? ''));
            if ($shortDescription === '') {
                $shortDescription = trim((string)($s->description ?? ''));
            }
            if (mb_strlen($shortDescription) > 280) {
                $shortDescription = rtrim(mb_substr($shortDescription, 0, 277)) . '…';
            }

            $out[] = [
                'id' => $id,
                'slug' => $s->slug,
                'url' => SeriesUrl::path($s),
                'poster_url' => $s->poster_url ?? '',
                'title' => $s->title,
                'short_description' => $shortDescription,
                'year' => $s->year,
                'kp_rating' => $s->kp_rating,
                'imdb_rating' => $s->imdb_rating,
                'broadcast_status' => $s->broadcast_status,
                'season_number' => $season,
                'last_episode_number' => $episode,
                'episode_progress_label' => $progressLabel,
                'season_badge' => $season ? 'S' . $season : '',
                'episode_badge' => $episode ? 'E' . $episode : '',
                'top_reaction_emoji' => $topEmojis[$id] ?? '',
                'badge_new_episode' => isset($newEpisodeIds[$id]),
                'badge_new_episode_label' => $newEpisodeLabel,
                'badge_popular' => (bool)$s->popular_badge_active,
                'badge_popular_label' => $popularLabel,
                'badge_coming_soon' => $isComingSoon,
                'badge_coming_soon_label' => $comingSoonLabel,
                'premiere_countdown_label' => $premiereCountdown,
                'is_pinned' => $s->is_pinned,
            ];
        }

        return $out;
    }

    /**
     * @param array<int> $seriesIds
     * @return array<int, true>
     */
    public static function newEpisodeSeriesIds(array $seriesIds): array
    {
        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));
        if ($seriesIds === []) {
            return [];
        }

        $days = SiteConfig::int('card_badge_new_episode_days');
        $since = now()->subDays(max(1, $days));

        $fromEvents = NotificationEvent::query()
            ->selectRaw('series_id, MAX(created_at) as last_at')
            ->whereIn('series_id', $seriesIds)
            ->where('event_type', 'new_episode')
            ->where('created_at', '>=', $since)
            ->groupBy('series_id')
            ->pluck('last_at', 'series_id');

        $fromSeries = Series::query()
            ->whereIn('id', $seriesIds)
            ->whereNotNull('last_episode_changed_at')
            ->where('last_episode_changed_at', '>=', $since)
            ->pluck('last_episode_changed_at', 'id');

        $out = [];
        foreach ($seriesIds as $seriesId) {
            $eventAt = $fromEvents->get($seriesId);
            $changedAt = $fromSeries->get($seriesId);

            if ($eventAt !== null || $changedAt !== null) {
                $out[$seriesId] = true;
            }
        }

        return $out;
    }
}
