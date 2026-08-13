<?php

namespace App\Http\Controllers;

use App\Models\ReactionType;
use App\Models\SiteSetting;
use App\Services\AdminReactionStatsService;
use App\Services\ReactionWidgetService;
use App\Support\TplCache;
use Illuminate\Http\Request;

class AdminReactionController extends Controller
{
    public function index()
    {
        return response()->json([
            'enabled' => ReactionWidgetService::isEnabled(),
            'badge' => ReactionWidgetService::badgeText(),
            'title' => ReactionWidgetService::titleText(),
            'items' => ReactionType::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function stats(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,7d,30d,90d,365d,all,custom'],
            'group' => ['nullable', 'in:day,week,month'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'reaction_type_id' => ['nullable', 'integer', 'exists:reaction_types,id'],
            'top' => ['nullable', 'integer', 'min:5', 'max:50'],
            'fresh' => ['nullable', 'boolean'],
        ]);

        return response()->json(AdminReactionStatsService::report(
            $data['period'] ?? '30d',
            $data['group'] ?? 'day',
            $data['date_from'] ?? null,
            $data['date_to'] ?? null,
            isset($data['reaction_type_id']) ? (int) $data['reaction_type_id'] : null,
            (int) ($data['top'] ?? 25),
            (bool) ($data['fresh'] ?? false),
        ));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'badge' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:200'],
        ]);

        SiteSetting::query()->updateOrCreate(['key' => 'reactions_enabled'], ['value' => $data['enabled'] ? '1' : '0']);
        SiteSetting::query()->updateOrCreate(['key' => 'reactions_badge'], ['value' => $data['badge']]);
        SiteSetting::query()->updateOrCreate(['key' => 'reactions_title'], ['value' => $data['title']]);
        TplCache::bumpGlobalVersion();

        return response()->json(['ok' => true]);
    }

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:reaction_types,id'],
            'emoji' => ['required', 'string', 'max:16'],
            'label' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = isset($data['id'])
            ? ReactionType::query()->findOrFail($data['id'])
            : new ReactionType();

        $type->fill([
            'emoji' => $data['emoji'],
            'label' => $data['label'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $type->save();
        TplCache::bumpGlobalVersion();
        AdminReactionStatsService::bumpCache();

        return response()->json(['ok' => true, 'item' => $type]);
    }

    public function destroy(int $id)
    {
        ReactionType::query()->whereKey($id)->delete();
        TplCache::bumpGlobalVersion();
        AdminReactionStatsService::bumpCache();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:reaction_types,id'],
        ]);

        foreach ($data['ids'] as $index => $id) {
            ReactionType::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }
        TplCache::bumpGlobalVersion();
        AdminReactionStatsService::bumpCache();

        return response()->json(['ok' => true]);
    }
}
