<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\NotificationSetting;
use App\Models\Review;
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
use App\Support\CommentView;
use App\Support\ReviewView;
use App\Support\PlayerUrlHelper;
use App\Support\ContentTypes;
use App\Support\SeasonEpisodeLabels;
use App\Support\SiteConfig;
use App\Support\SeriesUrl;
use App\Support\Speedbar;
use App\Support\TaxonomyRegistry;
use App\Support\Utf8;
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

        if ($cached = $this->tryCachedTplPage('series/show.tpl', $series->id)) {
            return $cached;
        }

        $players = PlayerUrlHelper::activePlayersForSeries($series);
        $activeSource = $players[0]['url'] ?? ($players[0]['html'] ?? '');

        $seriesUrl = url(SeriesUrl::path($series));

        $schedule = EpisodeProgressService::scheduleForSeries($series);
        $hasSchedule = count($schedule) > 0;
        $progress = EpisodeProgressService::resolvedProgress($series);
        $nextReminder = EpisodeProgressService::nextUpcomingReminder($series);
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

        $galleryUrls = collect(is_array($series->gallery_urls) ? $series->gallery_urls : [])
            ->map(static fn ($url) => trim((string)$url))
            ->filter()
            ->values()
            ->all();

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
            'brand_url' => $series->brand_url,
            'gallery_urls' => $galleryUrls,
            'gallery' => array_map(static fn (string $url) => ['url' => $url], $galleryUrls),
            'has_gallery' => $galleryUrls !== [],
            'kp_rating' => $series->kp_rating,
            'imdb_rating' => $series->imdb_rating,
            'imdb_id' => $series->imdb_id,
            'content_type' => $series->content_type,
            'content_type_label' => ContentTypes::label($series->content_type),
            'broadcast_status' => $series->broadcast_status,
            'broadcast_status_label' => $series->broadcastStatusLabel(),
            'status_badge_class' => $statusBadge['class'] ?? '',
            'status_badge_label' => $statusBadge['label'] ?? '',
            'season_number' => $progress['season_number'],
            'last_episode_number' => $progress['last_episode_number'],
            'episode_progress_label' => $progress['label'],
            'next_episode_reminder' => $nextReminder['label'] ?? '',
            'next_episode_season' => isset($nextReminder['season_number']) ? (string)$nextReminder['season_number'] : '',
            'next_episode_number' => isset($nextReminder['episode_number']) ? (string)$nextReminder['episode_number'] : '',
            'next_episode_days_until' => isset($nextReminder['days_until']) ? (string)$nextReminder['days_until'] : '',
            'premiere_date_label' => $premiereLabel,
            'premiere_day_month_label' => $series->premiereDayMonthLabel(),
            'premiere_countdown_label' => $series->premiereCountdownLabel() ?? '',
            'translation' => $series->translation,
            'channel_name' => $series->channel_name,
            'channel_url' => $series->channel_url,
            'channel_logo_url' => $series->channel_logo_url,
            'countries_text' => $series->countries->map(fn ($c) => TaxonomyRegistry::displayName($c))->implode(', '),
            'genres_text' => $series->genres->map(fn ($g) => TaxonomyRegistry::displayName($g))->implode(', '),
            'actors_text' => $displayActors->map(fn ($p) => Utf8::ucfirst($p->name))->implode(', '),
            'directors_text' => $displayDirectors->map(fn ($p) => Utf8::ucfirst($p->name))->implode(', '),
            'collections_text' => $series->collections->map(fn ($c) => TaxonomyRegistry::displayName($c))->implode(', '),
            'studios_text' => $studios->map(fn ($s) => TaxonomyRegistry::displayName($s))->implode(', '),
            'year_label' => $displayYear > 0 ? (string)$displayYear : '',
            'year_url' => $yearUrl,
            'premiere_is_year_only' => $premiereIsYearOnly,
            'collections' => $this->mapLinkItems($series->collections, fn ($c) => [
                'slug' => $c->slug,
                'title' => TaxonomyRegistry::displayName($c),
                'name' => TaxonomyRegistry::displayName($c),
                'url' => '/collections/' . $c->slug . '/',
            ]),
            'studios' => $this->mapLinkItems($studios->values(), fn (Studio $s) => [
                'slug' => $s->slug,
                'title' => TaxonomyRegistry::displayName($s),
                'name' => TaxonomyRegistry::displayName($s),
                'logo_url' => $s->logo_url ?? '',
                'url' => '/studios/' . $s->slug . '/',
            ]),
            'countries' => $this->mapLinkItems($series->countries, fn ($c) => [
                'slug' => $c->slug,
                'name' => TaxonomyRegistry::displayName($c),
                'url' => '/country/' . $c->slug . '/',
            ]),
            'genres' => $this->mapLinkItems($series->genres, fn ($g) => [
                'slug' => $g->slug,
                'name' => TaxonomyRegistry::displayName($g),
                'url' => '/genre/' . $g->slug . '/',
            ]),
            'actors' => $this->mapLinkItems($displayActors, fn ($p) => [
                'slug' => $p->slug,
                'name' => Utf8::ucfirst($p->name),
                'url' => '/person/' . $p->slug . '/',
                'photo_url' => $p->photo_url,
            ]),
            'directors' => $this->mapLinkItems($displayDirectors, fn ($p) => [
                'slug' => $p->slug,
                'name' => Utf8::ucfirst($p->name),
                'url' => '/person/' . $p->slug . '/',
                'photo_url' => $p->photo_url,
            ]),
            'age_limit' => $series->age_limit,
            'age_limit_label' => $series->ageLimitLabel(),
            'age_limit_tooltip' => $series->ageLimitTooltip(),
            'kp_web_url' => $series->kp_web_url,
            'likes' => $series->likesCount(),
            'dislikes' => $series->dislikesCount(),
            'user_rating' => $series->userRatingLabel(),
        ];

        $labelTags = array_merge(
            SeasonEpisodeLabels::forSeries(
                $progress['season_number'],
                $progress['last_episode_number']
            ),
            ContentTypes::forTpl($series->content_type),
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
        // Lists load via API (site.js); keep cheap counts for badges / empty flags.
        $commentsSort = 'date';
        $commentsCount = $commentsEnabled
            ? (int) Comment::query()->where('series_id', $series->id)->where('status', 'approved')->count()
            : 0;

        $reviewsEnabled = SiteConfig::bool('reviews_enabled');
        $reviewsSort = 'date';
        $reviewsCount = $reviewsEnabled
            ? (int) Review::query()->where('series_id', $series->id)->where('status', 'approved')->count()
            : 0;

        $ownReview = null;
        if ($reviewsEnabled && Auth::check()) {
            $ownReview = Review::query()
                ->where('series_id', $series->id)
                ->where('user_id', Auth::id())
                ->first();
        }
        $hasOwnReview = $ownReview && in_array($ownReview->status, ['pending', 'approved'], true);
        $ownReviewPending = $ownReview && $ownReview->status === 'pending';

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
            'comments_empty' => false,
            'comments_list_html' => '',
            'has_reviews' => $reviewsEnabled,
            'has_engagement_tabs' => $commentsEnabled && $reviewsEnabled,
            'has_own_review' => (bool) $hasOwnReview,
            'own_review_pending' => (bool) $ownReviewPending,
            'own_review_message' => $ownReviewPending
                ? SiteConfig::str('reviews_msg_pending')
                : ($hasOwnReview ? SiteConfig::str('reviews_msg_already_exists') : ''),
            'reviews_sort' => $reviewsSort,
            'reviews_sort_date_active' => $reviewsSort === 'date',
            'reviews_sort_rating_active' => $reviewsSort === 'rating',
            'reviews_count' => $reviewsCount,
            'reviews_count_label' => ReviewView::countLabel($reviewsCount),
            'reviews_empty' => false,
            'reviews_list_html' => '',
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
                ? $this->renderPartial(
                    $this->themePartialExists('partials/related_cards.tpl')
                        ? 'partials/related_cards.tpl'
                        : 'partials/series_cards.tpl',
                    ['series_list' => $relatedMapped]
                )
                : '',
        ]);

        $tvSeriesJsonLd = [
            '@type' => 'TVSeries',
            'name' => $series->title,
            'description' => $series->description,
            'image' => $series->poster_url,
            'url' => $seriesUrl,
            'datePublished' => $series->year ? (string) $series->year : null,
        ];

        $aggregateRating = $reviewsEnabled
            ? ReviewView::aggregateRatingJsonLd($series->id)
            : null;
        if ($aggregateRating === null && $series->userRatingLabel()) {
            $aggregateRating = [
                '@type' => 'AggregateRating',
                'ratingValue' => $series->userRatingLabel(),
                'bestRating' => '10',
                'ratingCount' => $series->votesCount(),
            ];
        }
        if ($aggregateRating === null && $series->kp_rating) {
            $aggregateRating = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $series->kp_rating,
                'bestRating' => '10',
                'ratingCount' => max(1, (int) $series->kp_votes_count),
            ];
        }
        if ($aggregateRating !== null) {
            $tvSeriesJsonLd['aggregateRating'] = $aggregateRating;
        }

        $tvSeriesJsonLd = array_filter(
            $tvSeriesJsonLd,
            static fn ($value) => $value !== null && $value !== '' && $value !== []
        );

        $this->applySpeedbar(Speedbar::forSeries($series), $vars, [$tvSeriesJsonLd]);

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

        $brandUrl = trim((string)($series->brand_url ?? ''));
        if ($brandUrl !== '') {
            $vars['_site_background_override'] = $brandUrl;
        }

        return $this->renderTplPage('series/show.tpl', $vars, $meta);
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
