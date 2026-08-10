<?php

namespace App\Http\Controllers;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Services\AllohaBulkPlayerProgress;
use App\Services\AllohaBulkPlayerSync;
use App\Services\CdnVideoHubBulkProgress;
use App\Services\CdnVideoHubPlayerSync;
use App\Services\RutubeBulkTrailerProgress;
use App\Services\RutubeBulkTrailerSync;
use App\Services\RutubeTrailerService;
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

        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
            'message' => (string) ($progress['message'] ?? ''),
            'synced' => (int) ($progress['synced'] ?? 0),
            'skipped' => (int) ($progress['skipped'] ?? 0),
            'failed' => (int) ($progress['failed'] ?? 0),
        ]);
    }

    public function allohaSyncProgress()
    {
        $progress = AllohaBulkPlayerProgress::get();
        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
        ]);
    }

    public function pauseAllohaSync(AllohaBulkPlayerSync $sync)
    {
        $progress = $sync->pause();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function resumeAllohaSync(AllohaBulkPlayerSync $sync)
    {
        $progress = $sync->resume();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function stopAllohaSync(AllohaBulkPlayerSync $sync)
    {
        $progress = $sync->stop();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => AllohaBulkPlayerProgress::percent($progress),
            'stopped' => ($progress['status'] ?? '') === 'stopped',
        ]);
    }

    public function syncRutubeTrailersAll(Request $request, RutubeBulkTrailerSync $sync)
    {
        $data = $request->validate([
            'tab_name' => ['nullable', 'string', 'max:120'],
            'existing_mode' => ['nullable', 'string', 'in:skip,update'],
            'kp_id' => ['nullable', 'string'],
            'sleep' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'restart' => ['nullable', 'boolean'],
            'continue' => ['nullable', 'boolean'],
        ]);

        $restart = (bool) ($data['restart'] ?? false);
        $continue = (bool) ($data['continue'] ?? false);

        $progress = $sync->runProgressiveBatch(
            $restart || !$continue,
            (string) ($data['tab_name'] ?? 'Трейлер'),
            (string) ($data['existing_mode'] ?? 'skip'),
            isset($data['kp_id']) ? (string) $data['kp_id'] : null,
            (float) ($data['sleep'] ?? 0.5),
            (int) ($data['batch_size'] ?? 10),
        );

        if (($progress['status'] ?? '') === 'failed') {
            return response()->json([
                'ok' => false,
                'error' => (string) ($progress['message'] ?? 'Не удалось выполнить массовую простановку трейлеров'),
                'progress' => $progress,
                'percent' => RutubeBulkTrailerProgress::percent($progress),
                'done' => false,
            ], 422);
        }

        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => RutubeBulkTrailerProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
            'message' => (string) ($progress['message'] ?? ''),
            'synced' => (int) ($progress['synced'] ?? 0),
            'skipped' => (int) ($progress['skipped'] ?? 0),
            'failed' => (int) ($progress['failed'] ?? 0),
        ]);
    }

    public function rutubeTrailerSyncProgress()
    {
        $progress = RutubeBulkTrailerProgress::get();
        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => RutubeBulkTrailerProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
        ]);
    }

    public function pauseRutubeTrailerSync(RutubeBulkTrailerSync $sync)
    {
        $progress = $sync->pause();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => RutubeBulkTrailerProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function resumeRutubeTrailerSync(RutubeBulkTrailerSync $sync)
    {
        $progress = $sync->resume();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => RutubeBulkTrailerProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function stopRutubeTrailerSync(RutubeBulkTrailerSync $sync)
    {
        $progress = $sync->stop();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => RutubeBulkTrailerProgress::percent($progress),
            'stopped' => ($progress['status'] ?? '') === 'stopped',
        ]);
    }

    public function syncCdnVideoHubAll(Request $request, CdnVideoHubPlayerSync $sync)
    {
        $data = $request->validate([
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
            'restart' => ['nullable', 'boolean'],
            'continue' => ['nullable', 'boolean'],
        ]);

        $restart = (bool) ($data['restart'] ?? false);
        $continue = (bool) ($data['continue'] ?? false);

        $progress = $sync->runProgressiveBatch(
            $restart || !$continue,
            (int) ($data['batch_size'] ?? 100),
        );

        if (($progress['status'] ?? '') === 'failed') {
            return response()->json([
                'ok' => false,
                'error' => (string) ($progress['message'] ?? 'Не удалось выполнить массовую синхронизацию'),
                'progress' => $progress,
                'percent' => CdnVideoHubBulkProgress::percent($progress),
                'done' => false,
            ], 422);
        }

        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => CdnVideoHubBulkProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
            'message' => (string) ($progress['message'] ?? ''),
            'synced' => (int) ($progress['synced'] ?? 0),
            'skipped' => (int) ($progress['skipped'] ?? 0),
            'failed' => (int) ($progress['failed'] ?? 0),
        ]);
    }

    public function cdnVideoHubSyncProgress()
    {
        $progress = CdnVideoHubBulkProgress::get();
        $status = (string) ($progress['status'] ?? '');

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => CdnVideoHubBulkProgress::percent($progress),
            'done' => $status === 'done',
            'paused' => $status === 'paused',
            'stopped' => $status === 'stopped',
        ]);
    }

    public function pauseCdnVideoHubSync(CdnVideoHubPlayerSync $sync)
    {
        $progress = $sync->pause();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => CdnVideoHubBulkProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function resumeCdnVideoHubSync(CdnVideoHubPlayerSync $sync)
    {
        $progress = $sync->resume();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => CdnVideoHubBulkProgress::percent($progress),
            'paused' => ($progress['status'] ?? '') === 'paused',
        ]);
    }

    public function stopCdnVideoHubSync(CdnVideoHubPlayerSync $sync)
    {
        $progress = $sync->stop();

        return response()->json([
            'ok' => true,
            'progress' => $progress,
            'percent' => CdnVideoHubBulkProgress::percent($progress),
            'stopped' => ($progress['status'] ?? '') === 'stopped',
        ]);
    }

    public function show(string $kpId)
    {
        $series = AdminSeriesResolver::byKey($kpId);

        return response()->json(['players' => $this->serializePlayers($series)]);
    }

    public function addAllohaPlayer(Request $request, string $kpId, AllohaBulkPlayerSync $sync)
    {
        $series = AdminSeriesResolver::byKey($kpId, true);

        $data = $request->validate([
            'tab_name' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $sync->syncOneAtLast($series, (string) ($data['tab_name'] ?? 'Смотреть онлайн'));
        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error']], 422);
        }

        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'players' => $this->serializePlayers($series->refresh()),
        ]);
    }

    public function searchRutubeTrailers(Request $request, string $kpId, RutubeTrailerService $rutube)
    {
        $series = AdminSeriesResolver::byKey($kpId, true);

        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $payload = $rutube->searchCandidates(
            $series,
            isset($data['query']) ? (string) $data['query'] : null,
            (int) ($data['limit'] ?? 12),
        );

        return response()->json(array_merge(['ok' => true], $payload));
    }

    public function addRutubeTrailer(Request $request, string $kpId, RutubeTrailerService $rutube)
    {
        $series = AdminSeriesResolver::byKey($kpId, true);

        $data = $request->validate([
            'tab_name' => ['nullable', 'string', 'max:120'],
            'embed_url' => ['nullable', 'string', 'max:500'],
            'video_id' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $manual = trim((string) ($data['embed_url'] ?? ''));
        if ($manual === '') {
            $manual = trim((string) ($data['video_id'] ?? ''));
        }

        $result = $rutube->addToSeries(
            $series,
            (string) ($data['tab_name'] ?? 'Трейлер'),
            'update',
            $manual !== '' ? $manual : null,
            isset($data['title']) ? (string) $data['title'] : null,
        );
        if (!$result['ok']) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Не удалось добавить трейлер Rutube'], 422);
        }

        TplCache::forgetSeries($series->id);

        return response()->json([
            'ok' => true,
            'trailer' => $result['trailer'] ?? null,
            'players' => $this->serializePlayers($series->refresh()),
        ]);
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
            'players' => $this->serializePlayers($series),
        ]);
    }

    /**
     * @return list<array{id: int, provider: string|null, iframe_url: string|null, is_active: bool, priority: int|null}>
     */
    private function serializePlayers(Series $series): array
    {
        return $series->playerSources()
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
    }
}
