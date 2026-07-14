<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Models\Series;
use App\Models\Studio;
use App\Models\Year;
use App\Services\AnticipationService;
use App\Services\EpisodeProgressService;
use App\Services\ReactionWidgetService;
use App\Services\SeriesCardMapper;
use App\Services\SeriesRelatedService;
use App\Services\SeriesViewService;
use App\Services\UserLibraryService;
use App\Support\CommentTree;
use App\Support\CommentView;
use App\Support\PlayerUrlHelper;
use App\Support\SeasonEpisodeLabels;
use App\Support\SiteConfig;
use App\Support\SeriesUrl;
use App\Support\Speedbar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SeriesController extends TplController
{
    private const PEOPLE_DISPLAY_LIMIT = 15;

    public function show(string $seriesPath): RedirectResponse|Response
    {
        $seriesId = SeriesUrl::parseId($seriesPath);
        if (!$seriesId) {
            abort(404);
        }

        $series = Series::query()
            ->with([
                'genres',
                'countries',
                'actors',
                'directors',
                'studio',
                'studios' => fn ($q) => $q->where('is_active', true)->where('is_hidden', false),
                'collections' => fn ($q) => $q->where('is_active', true)->where('is_hidden', false),
                'playerSources',
            ])
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->findOrFail($seriesId);

        if (!SeriesUrl::isCanonicalPath($series, $seriesPath)) {
            return redirect(SeriesUrl::path($series), 301);
        }

        SeriesViewService::record($series, request());
        UserLibraryService::recordWatchHistory($series->id, Auth::user(), request());

        $players = PlayerUrlHelper::activePlayersForSeries($series);
        $activeSource = $players[0]['url'] ?? ($players[0]['html'] ?? '');

        $seriesUrl = url(SeriesUrl::path($series));

        $schedule = EpisodeProgressService::scheduleForSeries($series);
        $hasSchedule = count($schedule) > 0;
        $reactions = ReactionWidgetService::payloadForSeries($series, request());
        $hasReactions = (bool)($reactions['enabled'] ?? false);
        $anticipation = AnticipationService::payloadForSeries($series, request());
        $hasComingSoon = (bool)$series->is_coming_soon && AnticipationService::isEnabled();
        $statusBadge = $series->statusBadge();
        $telegramUrl = trim(SiteConfig::str('telegram_url'));

        $displayYear = (int)($series->year ?: $series->start_year ?: 0);
        $yearUrl = '';
        if ($displayYear >= 1900 && $displayYear <= 2100) {
            $yearTax = Year::query()
                ->where('slug', (string)$displayYear)
                ->where('is_active', true)
                ->where('is_hidden', false)
                ->first();
            if ($yearTax) {
                $yearUrl = '/year/' . $yearTax->slug . '/';
            }
        }

        $premiereLabel = $series->premiereDateLabel();
        $premiereIsYearOnly = $series->premiereIsYearOnly();

        $studios = collect();
        if ($series->studio && $series->studio->is_active && !$series->studio->is_hidden) {
            $studios->put($series->studio->id, $series->studio);
        }
        foreach ($series->studios as $studio) {
            $studios->put($studio->id, $studio);
        }

        $displayActors = $series->actors->take(self::PEOPLE_DISPLAY_LIMIT);
        $displayDirectors = $series->directors->take(self::PEOPLE_DISPLAY_LIMIT);

        $seriesData = [
            'id' => $series->id,
            'kp_id' => $series->kp_id,
            'slug' => $series->slug,
            'url' => SeriesUrl::path($series),
            'title' => $series->title,
            'title_en' => $series->title_en,
            'title_original' => $series->title_original,
            'year' => $series->year,
            'start_year' => $series->start_year,
            'end_year' => $series->end_year,
            'duration_minutes' => $series->duration_minutes,
            'description' => $series->description,
            'short_description' => $series->short_description,
            'slogan' => $series->slogan,
            'poster_url' => $series->poster_url,
            'kp_rating' => $series->kp_rating,
            'imdb_rating' => $series->imdb_rating,
            'imdb_id' => $series->imdb_id,
            'content_type' => $series->content_type,
            'broadcast_status' => $series->broadcast_status,
            'broadcast_status_label' => $series->broadcastStatusLabel(),
            'status_badge_class' => $statusBadge['class'] ?? '',
            'status_badge_label' => $statusBadge['label'] ?? '',
            'season_number' => $series->season_number,
            'last_episode_number' => $series->last_episode_number,
            'episode_progress_label' => $series->episodeProgressLabel(),
            'premiere_date_label' => $premiereLabel,
            'premiere_day_month_label' => $series->premiereDayMonthLabel(),
            'translation' => $series->translation,
            'channel_name' => $series->channel_name,
            'channel_url' => $series->channel_url,
            'channel_logo_url' => $series->channel_logo_url,
            'countries_text' => $series->countries->pluck('name')->implode(', '),
            'genres_text' => $series->genres->pluck('name')->implode(', '),
            'actors_text' => $displayActors->pluck('name')->implode(', '),
            'directors_text' => $displayDirectors->pluck('name')->implode(', '),
            'collections_text' => $series->collections->pluck('title')->implode(', '),
            'studios_text' => $studios->pluck('title')->implode(', '),
            'year_label' => $displayYear > 0 ? (string)$displayYear : '',
            'year_url' => $yearUrl,
            'premiere_is_year_only' => $premiereIsYearOnly,
            'collections' => $this->mapLinkItems($series->collections, fn ($c) => [
                'slug' => $c->slug,
                'title' => $c->title,
                'name' => $c->title,
                'url' => '/collections/' . $c->slug . '/',
            ]),
            'studios' => $this->mapLinkItems($studios->values(), fn (Studio $s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'name' => $s->title,
                'url' => '/studios/' . $s->slug . '/',
            ]),
            'countries' => $this->mapLinkItems($series->countries, fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'url' => '/country/' . $c->slug . '/',
            ]),
            'genres' => $this->mapLinkItems($series->genres, fn ($g) => [
                'slug' => $g->slug,
                'name' => $g->name,
                'url' => '/genre/' . $g->slug . '/',
            ]),
            'actors' => $this->mapLinkItems($displayActors, fn ($p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'url' => '/person/' . $p->slug . '/',
                'photo_url' => $p->photo_url,
            ]),
            'directors' => $this->mapLinkItems($displayDirectors, fn ($p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'url' => '/person/' . $p->slug . '/',
            ]),
            'age_limit' => $series->age_limit,
            'age_limit_label' => $series->ageLimitLabel(),
            'age_limit_tooltip' => $series->ageLimitTooltip(),
            'kp_web_url' => $series->kp_web_url,
            'likes' => $series->likesCount(),
            'dislikes' => $series->dislikesCount(),
            'user_rating' => $series->userRatingLabel(),
        ];

        $labelTags = SeasonEpisodeLabels::forSeries(
            $series->season_number,
            $series->last_episode_number
        );

        $notificationSubscribed = false;
        if (SiteConfig::bool('notifications_enabled') && Auth::check()) {
            $notificationSubscribed = NotificationSetting::query()
                ->where('user_id', Auth::id())
                ->where('series_id', $series->id)
                ->where('is_enabled', true)
                ->exists();
        }

        $commentsEnabled = SiteConfig::bool('comments_enabled');
        $commentsSort = CommentTree::normalizeSort(request()->query('sort'));
        $commentItems = $commentsEnabled
            ? CommentTree::forSeries($series->id, request(), $commentsSort)
            : [];
        $commentsCount = CommentView::countComments($commentItems);

        $relatedSeries = SeriesRelatedService::forSeries($series);
        $relatedMapped = SeriesCardMapper::mapSeries($relatedSeries);
        $hasRelated = $relatedMapped !== [];

        $vars = array_merge($labelTags, [
            'series' => $seriesData,
            'notify_voices' => EpisodeProgressService::voicesForSeries($series),
            'schedule' => $schedule,
            'has_schedule' => $hasSchedule,
            'has_telegram' => $telegramUrl !== '',
            'telegram_url' => $telegramUrl,
            'telegram_label' => SiteConfig::str('telegram_label'),
            'episodes_modal' => $hasSchedule
                ? $this->renderPartial('partials/episodes_modal.tpl', [
                    'series' => $seriesData,
                    'schedule' => $schedule,
                    'has_schedule' => true,
                ])
                : '',
            'active_player_url' => $activeSource,
            'has_player' => count($players) > 0,
            'players' => $players,
            'has_players' => count($players) > 0,
            'has_reactions' => $hasReactions,
            'has_comments' => $commentsEnabled,
            'has_comments_vote' => SiteConfig::bool('comments_vote_enabled'),
            'comments_sort' => $commentsSort,
            'comments_sort_date_active' => $commentsSort === 'date',
            'comments_sort_rating_active' => $commentsSort === 'rating',
            'comments_count' => $commentsCount,
            'comments_count_label' => CommentView::countLabel($commentsCount),
            'comments_empty' => $commentsCount === 0,
            'comments_list_html' => $commentsEnabled
                ? $this->renderCommentsList($commentItems)
                : '',
            'has_series_vote' => SiteConfig::bool('series_vote_enabled'),
            'has_notifications' => SiteConfig::bool('notifications_enabled'),
            'notification_subscribed' => $notificationSubscribed ? '1' : '',
            'has_watchlists' => SiteConfig::bool('watchlists_enabled'),
            'has_favourites' => SiteConfig::bool('favourites_enabled'),
            'has_coming_soon' => $hasComingSoon,
            'anticipation' => $anticipation,
            'anticipation_widget' => $hasComingSoon
                ? $this->renderPartial('partials/anticipation_widget.tpl', [
                    'series' => ['id' => $series->id, 'slug' => $series->slug],
                    'anticipation' => $anticipation,
                ])
                : '',
            'reactions' => $reactions,
            'reactions_widget' => $hasReactions
                ? $this->renderPartial('partials/reactions_widget.tpl', [
                    'series' => ['id' => $series->id, 'slug' => $series->slug],
                    'reactions' => $reactions,
                ])
                : '',
            'has_related' => $hasRelated,
            'related_cards_html' => $hasRelated
                ? $this->renderPartial('partials/series_cards.tpl', [
                    'series_list' => $relatedMapped,
                ])
                : '',
        ]);

        $this->applySpeedbar(Speedbar::forSeries($series), $vars, [
            [
                '@type' => 'TVSeries',
                'name' => $series->title,
                'description' => $series->description,
                'image' => $series->poster_url,
                'url' => $seriesUrl,
                'datePublished' => $series->year ? (string)$series->year : null,
                'aggregateRating' => $series->userRatingLabel() ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => $series->userRatingLabel(),
                    'bestRating' => '10',
                    'ratingCount' => $series->votesCount(),
                ] : ($series->kp_rating ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (string)$series->kp_rating,
                    'bestRating' => '10',
                    'ratingCount' => max(1, (int)$series->kp_votes_count),
                ] : null),
            ],
        ]);

        $vars['page'] = [
            'heading' => $series->title,
        ];

        $meta = [
            'image' => (string)($series->poster_url ?? ''),
            'canonical' => url(SeriesUrl::path($series)),
            'robots' => $series->noindex ? 'noindex,follow' : '',
        ];

        $metaTitle = trim((string)($series->meta_title ?? ''));
        if ($metaTitle !== '') {
            $meta['title'] = trim($this->renderer()->interpolate($metaTitle, $vars));
        }

        $metaDescription = trim((string)($series->meta_description ?? ''));
        if ($metaDescription !== '') {
            $meta['description'] = trim($this->renderer()->interpolate($metaDescription, $vars));
        }

        $vars['_cache_series_id'] = $series->id;

        return $this->renderTplPage('series/show.tpl', $vars, $meta);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function renderCommentsList(array $items, int $depth = 0): string
    {
        if ($items === []) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            $childrenHtml = '';
            $children = $item['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $childrenHtml = '<div class="comment-replies">'
                    . $this->renderCommentsList($children, $depth + 1)
                    . '</div>';
            }

            $html .= $this->renderPartial('partials/comment_item.tpl', [
                'item' => CommentView::mapForTpl($item, $depth, $childrenHtml),
                'has_comments_vote' => SiteConfig::bool('comments_vote_enabled'),
            ]);
        }

        return $html;
    }

    /**
     * @param iterable<int, mixed> $items
     * @param callable(mixed): array<string, mixed> $mapper
     * @return list<array<string, mixed>>
     */
    private function mapLinkItems(iterable $items, callable $mapper): array
    {
        $mapped = [];
        foreach ($items as $item) {
            $mapped[] = $mapper($item);
        }

        $count = count($mapped);
        foreach ($mapped as $i => &$row) {
            $row['is_last'] = $i === $count - 1;
        }
        unset($row);

        return $mapped;
    }
}
