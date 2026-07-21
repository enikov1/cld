<?php

namespace App\Http\Controllers;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Services\AllohaBulkPlayerProgress;
use App\Services\AllohaBulkPlayerSync;
use App\Services\CdnVideoHubPlayerSync;
use App\Support\AdminSeriesResolver;
use App\Support\PlayerUrlHelper;
use App\Support\TplCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPlayersController extends Controller
{
    public function syncAllohaAll(Request $request, AllohaBulkPlayerSync $sync)
    {
        $data = $request->validate([
            'tab_name' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:1', 'max:20'],
            'kp_id' => ['nullable', 'string'],
            'sleep' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'restart' => ['nullable', 'boolean'],
            'continue' => ['nullable', 'boolean'],
        ]);

        $restart = (bool) ($data['restart'] ?? false);
        $continue = (bool) ($data['continue'] ?? false);

        $progress = $sync->runProgressiveBatch(
            $restart || !$continue,
            (string) ($data['tab_name'] ?? 'Смотреть онлайн'),
            (int) ($data['position'] ?? 1),
            isset($data['kp_id']) ? (string) $data['kp_id'] : null,
            (float) ($data['sleep'] ?? 0),
        );

        if (($progress['status'] ?? '') === 'failed') {
            return response()->json([
                'ok' => false,
                'error' => (string) ($progress['message'] ?? 'Не удалось выполнить массовую синхронизацию'),
                'progress' => $progress,
                'percent' => AllohaBulkPlayerProgress::percent($progress),
                'done' => false,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'done' => ($progress['status'] ?? '') === 'done',
            'message' => (string) ($progress['message'] ?? ''),
            'synced' => (int) ($progress['synced'] ?? 0),
            'skipped' => (int) ($progress['skipped'] ?? 0),
            'failed' => (int) ($progress['failed'] ?? 0),
        ]);
    }

    public function allohaSyncProgress()
    {
        $progress = AllohaBulkPlayerProgress::get();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'done' => ($progress['status'] ?? '') === 'done',
        ]);
    }

    public function syncCdnVideoHubAll(CdnVideoHubPlayerSync $sync)
    {
        $result = $sync->syncAll();

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Не удалось выполнить массовую синхронизацию',
                'synced' => $result['synced'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'synced' => $result['synced'],
            'skipped' => $result['skipped'],
            'message' => sprintf(
                'CDN VideoHub проставлен: %d сериалов, пропущено: %d',
                $result['synced'],
                $result['skipped'],
            ),
        ]);
    }

    public function show(string $kpId)
    {
        $series = AdminSeriesResolver::byKey($kpId);

        $items = $series->playerSources()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (PlayerSource $source) => [
                'id' => $source->id,
                'provider' => $source->provider,
                'iframe_url' => $source->iframe_url,
                'is_active' => $source->is_active,
                'priority' => $source->priority,
            ])
            ->all();

        return response()->json(['players' => $items]);
    }

    public function save(Request $request, string $kpId)
    {
        $series = AdminSeriesResolver::byKey($kpId);

        $data = $request->validate([
            'players' => ['nullable', 'array'],
            'players.*.id' => ['nullable', 'integer'],
            'players.*.provider' => ['nullable', 'string', 'max:120'],
            'players.*.iframe_url' => ['required', 'string', 'max:5000'],
            'players.*.is_active' => ['nullable', 'boolean'],
            'players.*.priority' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($series, $data) {
            $existingIds = $series->playerSources()->pluck('id')->all();
            $keptIds = [];

            foreach ($data['players'] ?? [] as $i => $row) {
                $url = PlayerUrlHelper::normalizePlayerContent($row['iframe_url'] ?? '');
                if ($url === '') {
                    continue;
                }

                $payload = [
                    'provider' => trim((string)($row['provider'] ?? '')) ?: ('Плеер ' . ($i + 1)),
                    'iframe_url' => $url,
                    'is_active' => array_key_exists('is_active', $row) ? (bool)$row['is_active'] : true,
                    'priority' => isset($row['priority']) ? (int)$row['priority'] : (100 - $i),
                ];

                if (!empty($row['id'])) {
                    $source = PlayerSource::query()
                        ->where('series_id', $series->id)
                        ->where('id', $row['id'])
                        ->first();

                    if ($source) {
                        $source->update($payload);
                        $keptIds[] = $source->id;
                        continue;
                    }
                }

                $created = PlayerSource::query()->create(array_merge($payload, [
                    'series_id' => $series->id,
                ]));
                $keptIds[] = $created->id;
            }

            $removeIds = array_diff($existingIds, $keptIds);
            if ($removeIds !== []) {
                PlayerSource::query()
                    ->where('series_id', $series->id)
                    ->whereIn('id', $removeIds)
                    ->delete();
            }

            $series->refresh();
            $activePlayers = PlayerUrlHelper::activePlayersForSeries($series);
            if ($activePlayers === []) {
                $series->update(['player_url' => null]);
            } else {
                $series->update(['player_url' => PlayerUrlHelper::firstIframeUrlForSeries($series)]);
            }
        });

        TplCache::forgetSeries($series->id);

        $series->refresh();

        return response()->json([
            'ok' => true,
            'players' => $series->playerSources()
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (PlayerSource $source) => [
                    'id' => $source->id,
                    'provider' => $source->provider,
                    'iframe_url' => $source->iframe_url,
                    'is_active' => $source->is_active,
                    'priority' => $source->priority,
                ])
                ->all(),
        ]);
    }
}
