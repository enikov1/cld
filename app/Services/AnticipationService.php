<?php

namespace App\Services;

use App\Models\Series;
use App\Models\SeriesAnticipationVote;
use App\Support\SeriesUrl;
use App\Support\PluralRu;
use App\Support\SiteConfig;
use App\Support\TplCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnticipationService
{
    public const SORT_MOST = 'most';
    public const SORT_LEAST = 'least';
    public const SORT_RELEASE = 'release';

    public static function isEnabled(): bool
    {
        return SiteConfig::bool('anticipation_enabled');
    }

    public static function guestEnabled(): bool
    {
        return SiteConfig::bool('anticipation_guest_enabled');
    }

    public static function normalizeSort(?string $sort): string
    {
        return match ($sort) {
            self::SORT_LEAST => self::SORT_LEAST,
            self::SORT_RELEASE => self::SORT_RELEASE,
            default => self::SORT_MOST,
        };
    }

    public static function comingSoonQuery(): Builder
    {
        return Series::query()
            ->with(['genres'])
            ->published()
            ->where('is_coming_soon', true);
    }

    public static function applySort(Builder $query, string $sort): Builder
    {
        return match (self::normalizeSort($sort)) {
            self::SORT_LEAST => $query
                ->orderByRaw('(anticipation_yes_count + anticipation_no_count) = 0')
                ->orderByRaw('CASE WHEN (anticipation_yes_count + anticipation_no_count) > 0 THEN anticipation_yes_count / (anticipation_yes_count + anticipation_no_count) ELSE 0 END ASC')
                ->orderBy('anticipation_yes_count')
                ->orderBy('premiere_date')
                ->orderBy('title'),
            self::SORT_RELEASE => $query
                ->orderByRaw('premiere_date IS NULL')
                ->orderBy('premiere_date')
                ->orderByDesc('anticipation_yes_count')
                ->orderBy('title'),
            default => $query
                ->orderByRaw('(anticipation_yes_count + anticipation_no_count) = 0')
                ->orderByRaw('CASE WHEN (anticipation_yes_count + anticipation_no_count) > 0 THEN anticipation_yes_count / (anticipation_yes_count + anticipation_no_count) ELSE 0 END DESC')
                ->orderByDesc('anticipation_yes_count')
                ->orderBy('premiere_date')
                ->orderBy('title'),
        };
    }

    public static function percent(Series $series): int
    {
        $yes = (int)$series->anticipation_yes_count;
        $no = (int)$series->anticipation_no_count;
        $total = $yes + $no;

        return $total > 0 ? (int)round(($yes / $total) * 100) : 0;
    }

    public static function votesLabel(int $count): string
    {
        return $count . ' ' . PluralRu::votes($count);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForSeries(Series $series, ?Request $request = null): array
    {
        $yes = (int)$series->anticipation_yes_count;
        $no = (int)$series->anticipation_no_count;
        $total = $yes + $no;
        $percent = self::percent($series);
        $userVote = $request ? self::userVoteValue($series, $request) : null;

        return [
            'enabled' => self::isEnabled(),
            'yes' => $yes,
            'no' => $no,
            'total' => $total,
            'percent' => $percent,
            'votes_label' => self::votesLabel($total),
            'user_vote' => $userVote,
            'wait_active' => $userVote === 1,
            'nowait_active' => $userVote === -1,
            'watch_active' => $userVote === 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function vote(Series $series, Request $request, int $value): array
    {
        if (!in_array($value, [1, -1], true)) {
            abort(422);
        }

        DB::transaction(function () use ($series, $request, $value): void {
            Series::query()->whereKey($series->id)->lockForUpdate()->first();

            if (Auth::check()) {
                self::voteAsUser($series, Auth::id(), $value);
            } else {
                if (!self::guestEnabled()) {
                    abort(401, SiteConfig::str('auth_msg_auth_required'));
                }
                self::voteAsGuest($series, $request, $value);
            }

            self::refreshCounters($series);
        });

        TplCache::forgetSeries($series->id);

        return self::payloadForSeries($series->fresh(), $request);
    }

    public static function userVoteValue(Series $series, Request $request): ?int
    {
        if (Auth::check()) {
            $vote = SeriesAnticipationVote::query()
                ->where('series_id', $series->id)
                ->where('user_id', Auth::id())
                ->value('value');

            return $vote !== null ? (int)$vote : null;
        }

        $vote = SeriesAnticipationVote::query()
            ->where('series_id', $series->id)
            ->where('voter_key', self::voterKey($request))
            ->value('value');

        return $vote !== null ? (int)$vote : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapCard(Series $series, int $rank, ?Request $request = null): array
    {
        $payload = self::payloadForSeries($series, $request);
        $genres = $series->genres->pluck('name')->all();

        return [
            'rank' => $rank,
            'id' => $series->id,
            'slug' => $series->slug,
            'url' => SeriesUrl::path($series),
            'title' => $series->title,
            'title_en' => $series->title_en ?: $series->title_original ?: '',
            'poster_url' => $series->poster_url ?? '',
            'premiere_date_label' => $series->premiereDateLabel() ?? '',
            'genres' => array_map(static fn (string $name) => ['name' => $name], $genres),
            'genres_text' => implode(', ', $genres),
            'percent' => $payload['percent'],
            'votes_label' => $payload['votes_label'],
            'total_votes' => $payload['total'],
            'watch_active' => $payload['watch_active'],
        ];
    }

    private static function voteAsUser(Series $series, int $userId, int $value): void
    {
        $existing = SeriesAnticipationVote::query()
            ->where('series_id', $series->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && (int)$existing->value === $value) {
            $existing->delete();

            return;
        }

        SeriesAnticipationVote::query()->updateOrCreate(
            ['series_id' => $series->id, 'user_id' => $userId],
            ['value' => $value, 'voter_key' => null]
        );
    }

    private static function voteAsGuest(Series $series, Request $request, int $value): void
    {
        $key = self::voterKey($request);

        $existing = SeriesAnticipationVote::query()
            ->where('series_id', $series->id)
            ->where('voter_key', $key)
            ->first();

        if ($existing && (int)$existing->value === $value) {
            $existing->delete();

            return;
        }

        SeriesAnticipationVote::query()->updateOrCreate(
            ['series_id' => $series->id, 'voter_key' => $key],
            ['value' => $value, 'user_id' => null]
        );
    }

    private static function refreshCounters(Series $series): void
    {
        $counts = SeriesAnticipationVote::query()
            ->where('series_id', $series->id)
            ->selectRaw('value, COUNT(*) as total')
            ->groupBy('value')
            ->pluck('total', 'value');

        $series->update([
            'anticipation_yes_count' => (int)($counts[1] ?? 0),
            'anticipation_no_count' => (int)($counts[-1] ?? 0),
        ]);
    }

    private static function voterKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
