<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CommentTree
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forSeries(int $seriesId, Request $request, string $sort = 'date', bool $includeUserVotes = true): array
    {
        $sort = self::normalizeSort($sort);

        $comments = Comment::query()
            ->with('user')
            ->withCount([
                'votes as likes_count' => fn ($q) => $q->where('value', 1),
                'votes as dislikes_count' => fn ($q) => $q->where('value', -1),
            ])
            ->where('series_id', $seriesId)
            ->where('status', 'approved')
            ->orderBy('created_at')
            ->get();

        $voteMap = $includeUserVotes
            ? self::userVoteMap($comments->pluck('id')->all(), $request)
            : [];

        $sortMeta = [];
        $roots = self::build(
            $comments,
            function (Comment $c) use ($voteMap, &$sortMeta) {
                $sortMeta[$c->id] = [
                    'sort_ts' => $c->created_at?->getTimestamp() ?? 0,
                    'pinned_at' => $c->pinned_at?->toIso8601String() ?? '',
                ];

                return self::serialize($c, $voteMap[$c->id] ?? null);
            }
        );

        self::sortRoots($roots, $sort, $sortMeta);

        return $roots;
    }

    public static function normalizeSort(?string $sort): string
    {
        return in_array($sort, ['date', 'rating'], true) ? $sort : 'date';
    }

    /**
     * @param list<array<string, mixed>> $roots
     * @param array<int, array{sort_ts: int, pinned_at: string}> $sortMeta
     */
    private static function sortRoots(array &$roots, string $sort, array $sortMeta): void
    {
        usort($roots, function (array $a, array $b) use ($sort, $sortMeta): int {
            $metaA = $sortMeta[$a['id']] ?? ['sort_ts' => 0, 'pinned_at' => ''];
            $metaB = $sortMeta[$b['id']] ?? ['sort_ts' => 0, 'pinned_at' => ''];

            $pinA = !empty($a['is_pinned']);
            $pinB = !empty($b['is_pinned']);
            if ($pinA !== $pinB) {
                return $pinB <=> $pinA;
            }

            if ($pinA && $pinB) {
                $pinnedCmp = strcmp($metaB['pinned_at'], $metaA['pinned_at']);
                if ($pinnedCmp !== 0) {
                    return $pinnedCmp;
                }
            }

            if ($sort === 'rating') {
                $scoreA = (int)($a['likes'] ?? 0) - (int)($a['dislikes'] ?? 0);
                $scoreB = (int)($b['likes'] ?? 0) - (int)($b['dislikes'] ?? 0);
                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }
            }

            return $metaA['sort_ts'] <=> $metaB['sort_ts'];
        });
    }

    /**
     * @param list<int> $commentIds
     * @return array<int, int|null>
     */
    private static function userVoteMap(array $commentIds, Request $request): array
    {
        if ($commentIds === []) {
            return [];
        }

        $map = [];
        $userId = Auth::id();

        if ($userId) {
            foreach (CommentVote::query()
                ->whereIn('comment_id', $commentIds)
                ->where('user_id', $userId)
                ->get() as $vote) {
                $map[$vote->comment_id] = (int)$vote->value;
            }
        } else {
            $key = hash('sha256', $request->session()->getId());
            foreach (CommentVote::query()
                ->whereIn('comment_id', $commentIds)
                ->where('voter_key', $key)
                ->get() as $vote) {
                $map[$vote->comment_id] = (int)$vote->value;
            }
        }

        return $map;
    }

    /**
     * @param Collection<int, Comment> $comments
     * @param callable(Comment): array<string, mixed> $serialize
     * @return list<array<string, mixed>>
     */
    public static function build(Collection $comments, callable $serialize): array
    {
        $nodes = [];
        foreach ($comments as $comment) {
            $nodes[$comment->id] = $serialize($comment);
            $nodes[$comment->id]['children'] = [];
        }

        $roots = [];
        foreach ($comments as $comment) {
            if ($comment->parent_id && isset($nodes[$comment->parent_id])) {
                $nodes[$comment->parent_id]['children'][] = &$nodes[$comment->id];
            } else {
                $roots[] = &$nodes[$comment->id];
            }
        }

        return $roots;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serialize(Comment $comment, ?int $userVote = null): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'author' => $comment->displayName(),
            'body' => $comment->body,
            'created_at' => $comment->created_at?->format('d.m.Y H:i') ?? '',
            'likes' => (int)($comment->likes_count ?? $comment->likesCount()),
            'dislikes' => (int)($comment->dislikes_count ?? $comment->dislikesCount()),
            'user_vote' => $userVote,
            'is_pinned' => (bool)$comment->is_pinned,
            'children' => [],
        ];
    }

    public static function assertValidParent(Series $series, ?int $parentId): void
    {
        if (!$parentId) {
            return;
        }

        $parent = Comment::query()
            ->where('id', $parentId)
            ->where('series_id', $series->id)
            ->where('status', 'approved')
            ->firstOrFail();

        $depth = 1;
        $current = $parent;
        $maxDepth = SiteConfig::int('comments_max_reply_depth');
        while ($current->parent_id && $depth < $maxDepth) {
            $current = Comment::query()->find($current->parent_id);
            if (!$current) {
                break;
            }
            $depth++;
        }

        if ($depth >= $maxDepth) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'parent_id' => SiteConfig::str('comments_msg_max_depth'),
            ]);
        }
    }
}
