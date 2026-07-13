<?php

namespace App\Services;

use App\Models\ReactionType;
use App\Models\Series;
use App\Models\SeriesReactionVote;
use App\Models\SiteSetting;
use App\Support\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReactionWidgetService
{
    public static function isEnabled(): bool
    {
        return SiteSetting::get('reactions_enabled', '1') === '1';
    }

    public static function badgeText(): string
    {
        return SiteSetting::get('reactions_badge', 'ОЦЕНИТЕ') ?: 'ОЦЕНИТЕ';
    }

    public static function titleText(): string
    {
        return SiteSetting::get('reactions_title', 'Как вам этот сериал?') ?: 'Как вам этот сериал?';
    }

    /**
     * @return Collection<int, ReactionType>
     */
    public static function activeTypes(): Collection
    {
        return ReactionType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForSeries(Series $series, ?Request $request = null): array
    {
        $types = self::activeTypes();
        $counts = SeriesReactionVote::query()
            ->where('series_id', $series->id)
            ->whereIn('reaction_type_id', $types->pluck('id'))
            ->selectRaw('reaction_type_id, COUNT(*) as total')
            ->groupBy('reaction_type_id')
            ->pluck('total', 'reaction_type_id');

        $total = (int)$counts->sum();
        $selectedId = $request ? self::userReactionTypeId($series, $request) : null;

        $items = $types->map(function (ReactionType $type) use ($counts, $total, $selectedId) {
            $count = (int)($counts[$type->id] ?? 0);
            $percent = $total > 0 ? (int)round(($count / $total) * 100) : 0;

            return [
                'id' => $type->id,
                'emoji' => $type->emoji,
                'label' => $type->label,
                'count' => $count,
                'count_label' => self::votesLabel($count),
                'percent' => $percent,
                'is_selected' => $selectedId === $type->id,
            ];
        })->values()->all();

        return [
            'enabled' => self::isEnabled() && count($items) > 0,
            'badge' => self::badgeText(),
            'title' => self::titleText(),
            'total' => $total,
            'total_label' => self::votesLabel($total),
            'selected_id' => $selectedId,
            'items' => $items,
        ];
    }

    public static function vote(Series $series, Request $request, int $reactionTypeId): array
    {
        $type = ReactionType::query()->where('is_active', true)->findOrFail($reactionTypeId);

        if (Auth::check()) {
            self::voteAsUser($series, Auth::id(), $type->id);
        } else {
            self::voteAsGuest($series, $request, $type->id);
        }

        return self::payloadForSeries($series, $request);
    }

    private static function voteAsUser(Series $series, int $userId, int $reactionTypeId): void
    {
        $existing = SeriesReactionVote::query()
            ->where('series_id', $series->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && (int)$existing->reaction_type_id === $reactionTypeId) {
            $existing->delete();
            return;
        }

        SeriesReactionVote::query()->updateOrCreate(
            ['series_id' => $series->id, 'user_id' => $userId],
            ['reaction_type_id' => $reactionTypeId, 'voter_key' => null]
        );
    }

    private static function voteAsGuest(Series $series, Request $request, int $reactionTypeId): void
    {
        $key = self::voterKey($request);

        $existing = SeriesReactionVote::query()
            ->where('series_id', $series->id)
            ->where('voter_key', $key)
            ->first();

        if ($existing && (int)$existing->reaction_type_id === $reactionTypeId) {
            $existing->delete();
            return;
        }

        SeriesReactionVote::query()->updateOrCreate(
            ['series_id' => $series->id, 'voter_key' => $key],
            ['reaction_type_id' => $reactionTypeId, 'user_id' => null]
        );
    }

    public static function userReactionTypeId(Series $series, Request $request): ?int
    {
        if (Auth::check()) {
            $vote = SeriesReactionVote::query()
                ->where('series_id', $series->id)
                ->where('user_id', Auth::id())
                ->first();

            return $vote ? (int)$vote->reaction_type_id : null;
        }

        $vote = SeriesReactionVote::query()
            ->where('series_id', $series->id)
            ->where('voter_key', self::voterKey($request))
            ->first();

        return $vote ? (int)$vote->reaction_type_id : null;
    }

    public static function voterKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    public static function votesLabel(int $count): string
    {
        $n = abs($count);
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $n . ' голос';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $n . ' голоса';
        }

        return $n . ' голосов';
    }

    /**
     * @param array<int> $seriesIds
     * @return array<int, string> series_id => emoji
     */
    public static function topEmojisForSeriesIds(array $seriesIds): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        $seriesIds = array_values(array_unique(array_filter(array_map('intval', $seriesIds))));
        if ($seriesIds === []) {
            return [];
        }

        $minVotes = max(1, SiteConfig::int('card_reaction_min_votes'));
        $typeIds = self::activeTypes()->pluck('id')->all();
        if ($typeIds === []) {
            return [];
        }

        $rows = SeriesReactionVote::query()
            ->selectRaw('series_reaction_votes.series_id, series_reaction_votes.reaction_type_id, COUNT(*) as total, reaction_types.sort_order, reaction_types.emoji')
            ->join('reaction_types', 'reaction_types.id', '=', 'series_reaction_votes.reaction_type_id')
            ->whereIn('series_reaction_votes.series_id', $seriesIds)
            ->whereIn('series_reaction_votes.reaction_type_id', $typeIds)
            ->where('reaction_types.is_active', true)
            ->groupBy(
                'series_reaction_votes.series_id',
                'series_reaction_votes.reaction_type_id',
                'reaction_types.sort_order',
                'reaction_types.emoji'
            )
            ->orderByDesc('total')
            ->orderBy('reaction_types.sort_order')
            ->orderBy('series_reaction_votes.reaction_type_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $seriesId = (int)$row->series_id;
            if (isset($out[$seriesId])) {
                continue;
            }
            if ((int)$row->total < $minVotes) {
                continue;
            }
            $out[$seriesId] = (string)$row->emoji;
        }

        return $out;
    }
}
