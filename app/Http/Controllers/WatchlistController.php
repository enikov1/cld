<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\Watchlist;
use App\Models\WatchlistItem;
use App\Support\WatchlistDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        WatchlistDefaults::ensureForUser($user);

        $lists = Watchlist::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Watchlist $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'slug' => $list->slug,
                'is_system' => $list->is_system,
                'items_count' => $list->items()->count(),
            ]);

        return response()->json(['lists' => $lists]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $maxSort = (int)Watchlist::query()->where('user_id', $user->id)->max('sort_order');

        $list = Watchlist::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'slug' => WatchlistDefaults::uniqueSlug($user->id, $data['name']),
            'is_system' => false,
            'sort_order' => $maxSort + 10,
        ]);

        return response()->json([
            'ok' => true,
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'slug' => $list->slug,
                'is_system' => false,
            ],
        ], 201);
    }

    public function update(Request $request, int $id)
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

        return response()->json(['ok' => true, 'name' => $list->name]);
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        $list = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($list->is_system) {
            return response()->json(['message' => 'Системный список нельзя удалить.'], 422);
        }

        $list->delete();

        return response()->json(['ok' => true]);
    }

    public function removeItem(Request $request, int $id)
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

        return response()->json(['ok' => true]);
    }
}
