<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\NotificationSetting;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Support\SeriesUrl;
use App\Support\RespondsWithJsonForms;
use App\Support\CommentBody;
use App\Support\SiteConfig;
use App\Support\Speedbar;
use App\Support\WatchlistDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends TplController
{
    use RespondsWithJsonForms;

    public function show()
    {
        $user = Auth::user();
        WatchlistDefaults::ensureForUser($user);

        $watchlists = Watchlist::query()
            ->where('user_id', $user->id)
            ->with(['items.series'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Watchlist $list) {
                $items = $list->items
                    ->filter(fn (WatchlistItem $item) => $item->series && $item->series->is_active)
                    ->map(fn (WatchlistItem $item) => [
                        'list_id' => $list->id,
                        'series_id' => $item->series_id,
                        'url' => SeriesUrl::path($item->series),
                        'slug' => $item->series->slug,
                        'title' => $item->series->title,
                        'poster_url' => $item->series->poster_url ?? '',
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $list->id,
                    'name' => $list->name,
                    'is_system' => $list->is_system,
                    'items' => $items,
                    'items_count' => count($items),
                ];
            })
            ->all();

        $comments = Comment::query()
            ->with(['series'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(SiteConfig::int('profile_comments_limit'))
            ->get()
            ->map(function (Comment $c) {
                $series = $c->series;

                return [
                    'body' => $c->body,
                    'body_html' => CommentBody::renderHtml($c->body),
                    'status' => $c->status,
                    'status_label' => match ($c->status) {
                        'approved' => 'Одобрен',
                        'pending' => 'На модерации',
                        'rejected' => 'Отклонён',
                        default => $c->status,
                    },
                    'status_badge_class' => $c->status === 'approved'
                        ? 'series-notification-badge--voice'
                        : '',
                    'created_at' => $c->created_at?->format('d.m.Y H:i') ?? '',
                    'series_title' => $series?->title ?? '—',
                    'series_url' => $series ? SeriesUrl::path($series) : '#',
                    'poster_url' => $series?->poster_url ?? '',
                ];
            })
            ->all();

        $initial = mb_strtoupper(mb_substr($user->name, 0, 1));
        $totalItems = array_sum(array_column($watchlists, 'items_count'));

        $notificationSubscriptions = [];
        if (SiteConfig::bool('notifications_enabled')) {
            $notificationSubscriptions = NotificationSetting::query()
                ->with(['series'])
                ->where('user_id', $user->id)
                ->where('is_enabled', true)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (NotificationSetting $setting) => [
                    'series_id' => $setting->series_id,
                    'series_title' => $setting->series?->title ?? '—',
                    'series_url' => $setting->series ? SeriesUrl::path($setting->series) : '#',
                    'poster_url' => $setting->series?->poster_url ?? '',
                    'notify_any' => $setting->notify_any ? '1' : '',
                    'voices_text' => $setting->notify_any
                        ? 'Любая озвучка'
                        : implode(', ', $setting->voices ?? []),
                ])
                ->all();
        }

        $vars = [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'registered_at' => $user->created_at?->format('d.m.Y') ?? '',
                'initial' => $initial,
                'notify_via_email' => $user->notify_via_email ? '1' : '',
                'notify_via_site' => $user->notify_via_site ? '1' : '',
                'notify_via_push' => $user->notify_via_push ? '1' : '',
            ],
            'profile_stats' => [
                'lists' => count($watchlists),
                'items' => $totalItems,
                'comments' => count($comments),
                'notifications' => count($notificationSubscriptions),
            ],
            'watchlists' => $watchlists,
            'profile_comments' => $comments,
            'notification_subscriptions' => $notificationSubscriptions,
            'has_notifications' => SiteConfig::bool('notifications_enabled'),
            'flash_success' => session('success', ''),
        ];

        $this->applySpeedbar(Speedbar::forProfile(), $vars);

        $meta = [
            'title' => 'Профиль — ' . $user->name,
            'description' => 'Личный кабинет пользователя',
        ];

        return $this->renderTplPage('profile/show.tpl', $vars, $meta);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk($request, 'Профиль обновлён.', [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Профиль обновлён.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            $message = 'Неверный текущий пароль.';
            if ($this->wantsFormJson($request)) {
                return $this->jsonError($request, $message, ['current_password' => [$message]]);
            }

            return back()->withErrors(['current_password' => $message]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk($request, 'Пароль изменён.');
        }

        return redirect()->route('profile.show')->with('success', 'Пароль изменён.');
    }

    public function storeList(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $maxSort = (int)Watchlist::query()->where('user_id', $user->id)->max('sort_order');

        Watchlist::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'slug' => WatchlistDefaults::uniqueSlug($user->id, $data['name']),
            'is_system' => false,
            'sort_order' => $maxSort + 10,
        ]);

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk($request, 'Список создан.', ['reload' => true]);
        }

        return redirect()->route('profile.show')->with('success', 'Список создан.');
    }

    public function updateList(Request $request, int $id)
    {
        $user = Auth::user();
        $list = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $list->update([
            'name' => $data['name'],
            'slug' => $list->is_system
                ? $list->slug
                : WatchlistDefaults::uniqueSlug($user->id, $data['name'], $list->id),
        ]);

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk($request, 'Список обновлён.', ['reload' => true]);
        }

        return redirect()->route('profile.show')->with('success', 'Список обновлён.');
    }

    public function destroyList(Request $request, int $id)
    {
        $user = Auth::user();
        $list = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($list->is_system) {
            $message = 'Системный список нельзя удалить.';
            if ($this->wantsFormJson($request)) {
                return $this->jsonError($request, $message, ['name' => [$message]]);
            }

            return redirect()->route('profile.show')->with('success', $message);
        }

        $list->delete();

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk($request, 'Список удалён.', ['reload' => true]);
        }

        return redirect()->route('profile.show')->with('success', 'Список удалён.');
    }

    public function removeListItem(Request $request, int $id)
    {
        $user = Auth::user();
        $list = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'series_id' => ['required', 'integer'],
        ]);

        WatchlistItem::query()
            ->where('watchlist_id', $list->id)
            ->where('series_id', $data['series_id'])
            ->delete();

        if ($this->wantsFormJson($request)) {
            $listItemsCount = WatchlistItem::query()
                ->where('watchlist_id', $list->id)
                ->whereHas('series', fn ($q) => $q->where('is_active', true))
                ->count();

            $totalItems = WatchlistItem::query()
                ->whereHas('watchlist', fn ($q) => $q->where('user_id', $user->id))
                ->whereHas('series', fn ($q) => $q->where('is_active', true))
                ->count();

            return $this->jsonOk($request, 'Сериал убран из списка.', [
                'list_id' => $list->id,
                'series_id' => (int)$data['series_id'],
                'items_count' => $listItemsCount,
                'stats' => [
                    'items' => $totalItems,
                ],
            ]);
        }

        return redirect()->route('profile.show')->with('success', 'Сериал убран из списка.');
    }
}
