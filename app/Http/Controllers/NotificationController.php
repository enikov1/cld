<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use App\Models\NotificationSetting;
use App\Support\SeriesUrl;
use App\Support\SiteConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['items' => [], 'unread' => 0]);
        }

        $limit = min(50, max(5, SiteConfig::int('notifications_inbox_limit')));

        $items = NotificationDelivery::query()
            ->with(['event.series'])
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->whereHas('event')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationDelivery $delivery) => $this->serializeDelivery($delivery))
            ->all();

        $unread = NotificationDelivery::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $items,
            'unread' => $unread,
        ]);
    }

    public function markRead(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'all' => ['nullable', 'boolean'],
        ]);

        $query = NotificationDelivery::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at');

        if (!empty($data['all'])) {
            $query->update(['read_at' => now()]);
        } elseif (!empty($data['ids'])) {
            $query->whereIn('id', $data['ids'])->update(['read_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, int $id)
    {
        $delivery = NotificationDelivery::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $delivery->update(['dismissed_at' => now(), 'read_at' => $delivery->read_at ?? now()]);

        return response()->json(['ok' => true]);
    }

    public function clearAll()
    {
        NotificationDelivery::query()
            ->where('user_id', Auth::id())
            ->whereNull('dismissed_at')
            ->update([
                'dismissed_at' => now(),
                'read_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    public function preferences()
    {
        $user = Auth::user();

        $subscriptions = NotificationSetting::query()
            ->with(['series'])
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (NotificationSetting $setting) => [
                'series_id' => $setting->series_id,
                'series_title' => $setting->series?->title ?? '—',
                'series_url' => $setting->series
                    ? SeriesUrl::path($setting->series)
                    : '#',
                'poster_url' => $setting->series?->poster_url ?? '',
                'notify_any' => $setting->notify_any,
                'voices' => $setting->voices ?? [],
            ])
            ->all();

        return response()->json([
            'notify_via_email' => (bool)$user->notify_via_email,
            'notify_via_site' => (bool)$user->notify_via_site,
            'subscriptions' => $subscriptions,
        ]);
    }

    public function savePreferences(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'notify_via_email' => ['nullable', 'boolean'],
            'notify_via_site' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'notify_via_email' => array_key_exists('notify_via_email', $data)
                ? (bool)$data['notify_via_email']
                : $user->notify_via_email,
            'notify_via_site' => array_key_exists('notify_via_site', $data)
                ? (bool)$data['notify_via_site']
                : $user->notify_via_site,
        ]);

        return response()->json([
            'ok' => true,
            'message' => SiteConfig::str('notifications_msg_prefs_saved'),
        ]);
    }

    public function unsubscribeSeries(int $seriesId)
    {
        NotificationSetting::query()
            ->where('user_id', Auth::id())
            ->where('series_id', $seriesId)
            ->delete();

        return response()->json([
            'ok' => true,
            'message' => SiteConfig::str('notifications_msg_unsubscribed'),
        ]);
    }

    private function serializeDelivery(NotificationDelivery $delivery): array
    {
        $event = $delivery->event;
        $series = $event?->series;

        $episodeLabel = '';
        if ($event?->season_number && $event?->episode_number) {
            $episodeLabel = sprintf('%d сезон, %d серия', $event->season_number, $event->episode_number);
        }

        return [
            'id' => $delivery->id,
            'read' => $delivery->read_at !== null,
            'created_at' => $delivery->created_at?->format('d.m.Y H:i') ?? '',
            'series_title' => $series?->title ?? '—',
            'series_url' => $series
                ? SeriesUrl::path($series)
                : '#',
            'poster_url' => $series?->poster_url ?? '',
            'episode_label' => $episodeLabel,
            'voice' => $event?->voice ?? '',
            'title' => $episodeLabel !== ''
                ? 'Новая серия — ' . $episodeLabel
                : 'Новая серия',
        ];
    }
}
