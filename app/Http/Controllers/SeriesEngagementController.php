<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSeries;
use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\GuestVote;
use App\Models\NotificationSetting;
use App\Models\PlayerReport;
use App\Models\Series;
use App\Models\UserVote;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Services\UserLibraryService;
use App\Support\CommentBody;
use App\Support\CommentModeration;
use App\Support\CommentTree;
use App\Support\SiteConfig;
use App\Support\TplCache;
use App\Support\WatchlistDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SeriesEngagementController extends Controller
{
    use ResolvesSeries;

    public function engagement(Request $request, int $seriesId)
    {
        $series = $this->resolveActiveSeries($seriesId);

        $user = Auth::user();
        $userVote = null;
        $listIds = [];

        if ($user) {
            WatchlistDefaults::ensureForUser($user);

            $vote = UserVote::query()
                ->where('user_id', $user->id)
                ->where('series_id', $series->id)
                ->first();
            $userVote = $vote?->value;

            $listIds = WatchlistItem::query()
                ->where('series_id', $series->id)
                ->whereHas('watchlist', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('watchlist_id')
                ->map(fn ($id) => (int)$id)
                ->all();
        } else {
            $guestVote = GuestVote::query()
                ->where('series_id', $series->id)
                ->where('voter_key', $this->voterKey($request))
                ->first();
            $userVote = $guestVote?->value;
        }

        $lists = [];
        $isFavourite = false;
        if ($user) {
            $lists = Watchlist::query()
                ->where('user_id', $user->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Watchlist $list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'is_system' => $list->is_system,
                    'system_key' => $list->system_key,
                    'contains' => in_array($list->id, $listIds, true),
                ])
                ->all();
            $isFavourite = UserLibraryService::isFavourite($series->id, $user, $request);
        } else {
            if (SiteConfig::bool('favourites_enabled') && SiteConfig::bool('favourites_guest_enabled')) {
                $isFavourite = UserLibraryService::isFavourite($series->id, null, $request);
            }
        }

        return response()->json([
            'likes' => $series->likesCount(),
            'dislikes' => $series->dislikesCount(),
            'user_rating' => $series->userRatingLabel(),
            'user_vote' => $userVote,
            'list_ids' => $listIds,
            'lists' => $lists,
            'logged_in' => (bool)$user,
            'is_favourite' => $isFavourite,
        ]);
    }

    public function vote(Request $request, int $seriesId)
    {
        if (!SiteConfig::bool('series_vote_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('series_vote_msg_disabled')], 403);
        }

        $data = $request->validate([
            'value' => ['required', 'in:1,-1'],
        ]);

        $series = $this->resolveActiveSeries($seriesId);
        $value = (int)$data['value'];

        if (Auth::check()) {
            $this->voteAsUser($series, Auth::id(), $value);
        } else {
            if (!SiteConfig::bool('series_vote_guest_enabled')) {
                return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
            }
            $this->voteAsGuest($request, $series, $value);
        }

        $this->bustSeriesCache($series);

        return response()->json([
            'ok' => true,
            'likes' => $series->likesCount(),
            'dislikes' => $series->dislikesCount(),
            'user_rating' => $series->userRatingLabel(),
        ]);
    }

    public function comments(Request $request, int $seriesId)
    {
        if (!SiteConfig::bool('comments_enabled')) {
            return response()->json(['items' => [], 'logged_in' => Auth::check(), 'disabled' => true]);
        }

        $series = $this->resolveActiveSeries($seriesId);
        $sort = CommentTree::normalizeSort($request->query('sort'));

        return response()->json([
            'items' => CommentTree::forSeries($series->id, $request, $sort),
            'sort' => $sort,
            'logged_in' => Auth::check(),
        ]);
    }

    public function storeComment(Request $request, int $seriesId)
    {
        if (!SiteConfig::bool('comments_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('comments_msg_disabled')], 403);
        }

        $series = $this->resolveActiveSeries($seriesId);

        if (!Auth::check() && !SiteConfig::bool('comments_guest_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
        }

        $min = SiteConfig::int('comments_body_min_length');
        $max = SiteConfig::int('comments_body_max_length');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:' . $min, 'max:' . $max],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
            'guest_name' => ['nullable', 'string', 'max:' . SiteConfig::int('comments_guest_name_max_length')],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);

        CommentTree::assertValidParent($series, isset($data['parent_id']) ? (int)$data['parent_id'] : null);

        $body = CommentBody::assertValid($data['body']);

        $payload = [
            'series_id' => $series->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $body,
            'status' => CommentModeration::initialStatus(),
        ];

        if (Auth::check()) {
            $payload['user_id'] = Auth::id();
            $payload['is_anonymous'] = false;
        } else {
            $isAnonymous = $request->boolean('is_anonymous');
            $guestName = trim((string)($data['guest_name'] ?? ''));

            if (!$isAnonymous && $guestName === '') {
                throw ValidationException::withMessages([
                    'guest_name' => SiteConfig::str('comments_msg_guest_name_required'),
                ]);
            }

            $payload['user_id'] = null;
            $payload['guest_name'] = $isAnonymous ? null : $guestName;
            $payload['is_anonymous'] = $isAnonymous;
        }

        Comment::query()->create($payload);

        $this->bustSeriesCache($series);

        $pending = !CommentModeration::autoApproveEnabled();
        $message = $pending
            ? SiteConfig::str('comments_msg_pending')
            : SiteConfig::str('comments_msg_published');

        return response()->json([
            'ok' => true,
            'pending' => $pending,
            'message' => $message,
        ]);
    }

    public function voteComment(Request $request, int $id)
    {
        if (!SiteConfig::bool('comments_vote_enabled')) {
            return response()->json(['ok' => false, 'message' => SiteConfig::str('comments_msg_disabled')], 403);
        }

        $data = $request->validate([
            'value' => ['required', 'in:1,-1'],
        ]);

        $comment = Comment::query()
            ->where('id', $id)
            ->where('status', 'approved')
            ->firstOrFail();

        $value = (int)$data['value'];

        if (Auth::check()) {
            $this->voteCommentAsUser($comment, Auth::id(), $value);
        } else {
            if (!SiteConfig::bool('comments_vote_guest_enabled')) {
                return response()->json(['ok' => false, 'message' => SiteConfig::str('auth_msg_auth_required')], 401);
            }
            $this->voteCommentAsGuest($request, $comment, $value);
        }

        $series = Series::query()->find($comment->series_id);
        if ($series) {
            $this->bustSeriesCache($series);
        }

        return response()->json([
            'ok' => true,
            'likes' => $comment->likesCount(),
            'dislikes' => $comment->dislikesCount(),
            'user_vote' => $this->commentUserVote($request, $comment),
        ]);
    }

    public function watchlist(Request $request, int $seriesId)
    {
        $this->requireAuth();

        $data = $request->validate([
            'list_id' => ['required', 'integer'],
            'action' => ['required', 'in:add,remove,toggle'],
        ]);

        $series = $this->resolveActiveSeries($seriesId);
        $userId = Auth::id();

        WatchlistDefaults::ensureForUser($userId);

        $list = Watchlist::query()
            ->where('user_id', $userId)
            ->where('id', $data['list_id'])
            ->firstOrFail();

        $existing = WatchlistItem::query()
            ->where('watchlist_id', $list->id)
            ->where('series_id', $series->id)
            ->first();

        $action = $data['action'];
        if ($action === 'toggle') {
            $action = $existing ? 'remove' : 'add';
        }

        if ($action === 'remove') {
            $existing?->delete();
        } else {
            WatchlistItem::query()->firstOrCreate([
                'watchlist_id' => $list->id,
                'series_id' => $series->id,
            ]);
        }

        $listIds = WatchlistItem::query()
            ->where('series_id', $series->id)
            ->whereHas('watchlist', fn ($q) => $q->where('user_id', $userId))
            ->pluck('watchlist_id')
            ->map(fn ($id) => (int)$id)
            ->all();

        return response()->json([
            'ok' => true,
            'list_ids' => $listIds,
            'contains' => in_array((int)$list->id, $listIds, true),
        ]);
    }

    public function saveNotifications(Request $request, int $seriesId)
    {
        $this->requireAuth();

        $data = $request->validate([
            'voices' => ['nullable', 'array'],
            'voices.*' => ['string', 'max:120'],
            'notify_any' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $series = $this->resolveActiveSeries($seriesId);

        if (array_key_exists('enabled', $data) && $data['enabled'] === false) {
            NotificationSetting::query()
                ->where('user_id', Auth::id())
                ->where('series_id', $series->id)
                ->delete();

            $this->bustSeriesCache($series);

            return response()->json([
                'ok' => true,
                'message' => SiteConfig::str('notifications_msg_unsubscribed'),
                'subscribed' => false,
            ]);
        }

        $voices = $data['voices'] ?? [];
        $notifyAny = $data['notify_any'] ?? true;

        if (!$notifyAny && $voices === []) {
            NotificationSetting::query()
                ->where('user_id', Auth::id())
                ->where('series_id', $series->id)
                ->delete();

            $this->bustSeriesCache($series);

            return response()->json([
                'ok' => true,
                'message' => SiteConfig::str('notifications_msg_unsubscribed'),
                'subscribed' => false,
            ]);
        }

        NotificationSetting::query()->updateOrCreate(
            ['user_id' => Auth::id(), 'series_id' => $series->id],
            [
                'voices' => $voices,
                'notify_any' => $notifyAny,
                'is_enabled' => true,
            ]
        );

        $this->bustSeriesCache($series);

        return response()->json([
            'ok' => true,
            'message' => SiteConfig::str('notifications_msg_saved'),
            'subscribed' => true,
        ]);
    }

    public function deleteNotifications(int $seriesId)
    {
        $this->requireAuth();

        $series = $this->resolveActiveSeries($seriesId);

        NotificationSetting::query()
            ->where('user_id', Auth::id())
            ->where('series_id', $series->id)
            ->delete();

        $this->bustSeriesCache($series);

        return response()->json([
            'ok' => true,
            'message' => SiteConfig::str('notifications_msg_unsubscribed'),
        ]);
    }

    public function notificationSettings(int $seriesId)
    {
        $this->requireAuth();

        $series = $this->resolveActiveSeries($seriesId);

        $setting = NotificationSetting::query()
            ->where('user_id', Auth::id())
            ->where('series_id', $series->id)
            ->first();

        return response()->json([
            'voices' => $setting?->voices ?? [],
            'notify_any' => $setting?->notify_any ?? true,
            'subscribed' => (bool)$setting,
        ]);
    }

    public function storePlayerReport(Request $request, int $seriesId)
    {
        $series = $this->resolveActiveSeries($seriesId);

        $data = $request->validate([
            'reason' => ['required', 'string', 'in:player_not_shown,video_not_start,audio_desync,description_error,other'],
            'reason_label' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'player_label' => ['nullable', 'string', 'max:120'],
        ]);

        $message = trim((string)($data['message'] ?? ''));
        if ($data['reason'] === 'other' && $message === '') {
            throw ValidationException::withMessages([
                'message' => 'Для пункта «Другое» опишите проблему.',
            ]);
        }

        $recent = PlayerReport::query()
            ->where('series_id', $series->id)
            ->where('ip', $request->ip())
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'reason' => 'Вы недавно уже отправляли жалобу. Подождите пару минут.',
            ]);
        }

        PlayerReport::query()->create([
            'series_id' => $series->id,
            'user_id' => Auth::id(),
            'reason' => $data['reason'],
            'reason_label' => trim((string)($data['reason_label'] ?? '')) ?: null,
            'message' => $message !== '' ? $message : null,
            'player_label' => trim((string)($data['player_label'] ?? '')) ?: null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string)$request->userAgent(), 500, ''),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Спасибо! Жалоба отправлена.',
        ]);
    }

    private function voteCommentAsUser(Comment $comment, int $userId, int $value): void
    {
        $existing = CommentVote::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();
            return;
        }

        CommentVote::query()->updateOrCreate(
            ['comment_id' => $comment->id, 'user_id' => $userId],
            ['value' => $value, 'voter_key' => null]
        );
    }

    private function voteCommentAsGuest(Request $request, Comment $comment, int $value): void
    {
        $key = $this->voterKey($request);

        $existing = CommentVote::query()
            ->where('comment_id', $comment->id)
            ->where('voter_key', $key)
            ->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();
            return;
        }

        CommentVote::query()->updateOrCreate(
            ['comment_id' => $comment->id, 'voter_key' => $key],
            ['value' => $value, 'user_id' => null]
        );
    }

    private function commentUserVote(Request $request, Comment $comment): ?int
    {
        if (Auth::check()) {
            $vote = CommentVote::query()
                ->where('comment_id', $comment->id)
                ->where('user_id', Auth::id())
                ->first();

            return $vote ? (int)$vote->value : null;
        }

        $key = $this->voterKey($request);
        $vote = CommentVote::query()
            ->where('comment_id', $comment->id)
            ->where('voter_key', $key)
            ->first();

        return $vote ? (int)$vote->value : null;
    }

    private function voteAsUser(Series $series, int $userId, int $value): void
    {
        $existing = UserVote::query()
            ->where('user_id', $userId)
            ->where('series_id', $series->id)
            ->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();
            return;
        }

        UserVote::query()->updateOrCreate(
            ['user_id' => $userId, 'series_id' => $series->id],
            ['value' => $value]
        );
    }

    private function voteAsGuest(Request $request, Series $series, int $value): void
    {
        $key = $this->voterKey($request);

        $existing = GuestVote::query()
            ->where('series_id', $series->id)
            ->where('voter_key', $key)
            ->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();
            return;
        }

        GuestVote::query()->updateOrCreate(
            ['series_id' => $series->id, 'voter_key' => $key],
            ['value' => $value]
        );
    }

    private function voterKey(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function requireAuth(): void
    {
        if (!Auth::check()) {
            abort(401, SiteConfig::str('auth_msg_auth_required'));
        }
    }

    private function bustSeriesCache(Series $series): void
    {
        TplCache::forgetSeries($series->id);
    }
}
