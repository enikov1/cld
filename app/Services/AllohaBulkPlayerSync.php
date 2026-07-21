<?php

namespace App\Services;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;
use App\Support\TplCache;
use Illuminate\Database\Eloquent\Builder;

class AllohaBulkPlayerSync
{
    public const SOURCE_KEY = 'alloha';

    public function __construct(
        private readonly AllohaClient $client,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runProgressiveBatch(
        bool $restart,
        string $tabName,
        int $position,
        ?string $kpId = null,
        float $sleep = 0,
    ): array {
        if (!AllohaConfig::isConfigured()) {
            return array_merge(AllohaBulkPlayerProgress::get(), [
                'status' => 'failed',
                'message' => 'API-токен Alloha не настроен. Укажите его в Настройках.',
            ]);
        }

        @set_time_limit(120);

        $tabName = trim($tabName) ?: 'Смотреть онлайн';
        $position = max(1, min(20, $position));
        $kpId = $kpId !== null && trim($kpId) !== '' ? trim($kpId) : null;
        $sleep = max(0, min(30, $sleep));
        $batchSize = max(1, min(100, (int) config('alloha.bulk_batch_size', 40)));

        $progress = AllohaBulkPlayerProgress::get();

        if ($restart || $progress['status'] !== 'running') {
            $progress = AllohaBulkPlayerProgress::normalize([
                'status' => 'running',
                'after_id' => 0,
                'total' => $this->baseQuery($kpId)->count(),
                'processed' => 0,
                'synced' => 0,
                'skipped' => 0,
                'failed' => 0,
                'tab_name' => $tabName,
                'position' => $position,
                'kp_id' => $kpId,
                'sleep' => $sleep,
                'message' => 'Простановка плеера запущена',
                'started_at' => time(),
                'finished_at' => null,
            ]);
            AllohaBulkPlayerProgress::save($progress);
        } else {
            $tabName = $progress['tab_name'];
            $position = $progress['position'];
            $kpId = $progress['kp_id'];
            $sleep = $progress['sleep'];
        }

        $batch = $this->syncBatch(
            afterId: (int) $progress['after_id'],
            limit: $batchSize,
            tabName: $tabName,
            position: $position,
            kpId: $kpId,
            sleep: $sleep,
        );

        $progress['after_id'] = $batch['last_id'];
        $progress['processed'] += $batch['processed'];
        $progress['synced'] += $batch['synced'];
        $progress['skipped'] += $batch['skipped'];
        $progress['failed'] += $batch['failed'];
        $progress['message'] = sprintf(
            'Обработано %d из %d',
            $progress['processed'],
            max($progress['total'], $progress['processed']),
        );

        if ($batch['done']) {
            if ($progress['synced'] > 0) {
                TplCache::bumpGlobalVersion();
            }

            $progress['status'] = 'done';
            $progress['finished_at'] = time();
            $progress['message'] = sprintf(
                'Готово: проставлено %d, пропущено %d, ошибок %d',
                $progress['synced'],
                $progress['skipped'],
                $progress['failed'],
            );
        } else {
            $progress['status'] = 'running';
        }

        AllohaBulkPlayerProgress::save($progress);

        return $progress;
    }

    /**
     * @return array{
     *     last_id: int,
     *     processed: int,
     *     synced: int,
     *     skipped: int,
     *     failed: int,
     *     done: bool,
     *     remaining: int
     * }
     */
    public function syncBatch(
        int $afterId,
        int $limit,
        string $tabName,
        int $position,
        ?string $kpId,
        float $sleep,
    ): array {
        $out = [
            'last_id' => $afterId,
            'processed' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'done' => false,
            'remaining' => 0,
        ];

        $seriesList = $this->baseQuery($kpId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($seriesList as $series) {
            $out['last_id'] = (int) $series->id;
            $out['processed']++;

            $kp = trim((string) $series->kp_id);
            if ($kp === '' || !preg_match('/^\d+$/', $kp)) {
                $out['skipped']++;
                continue;
            }

            try {
                $resolved = $this->resolveIframe($series);
                if ($resolved['iframe'] === '') {
                    $out['skipped']++;
                    continue;
                }

                if ($resolved['token'] !== null && $resolved['token'] !== trim((string) ($series->alloha_token ?? ''))) {
                    $series->update(['alloha_token' => $resolved['token']]);
                }

                $this->upsertMainTab($series, [
                    'provider' => $tabName,
                    'iframe_url' => $resolved['iframe'],
                    'is_active' => true,
                    'alloha_translation_id' => null,
                ], $position);

                $firstUrl = PlayerUrlHelper::firstIframeUrlForSeries($series);
                $series->update(['player_url' => $firstUrl]);

                $out['synced']++;

                if ($sleep > 0 && $resolved['api_calls'] > 0) {
                    usleep((int) ($sleep * 1_000_000));
                }
            } catch (\Throwable) {
                $out['failed']++;
            }
        }

        $out['remaining'] = $this->baseQuery($kpId)
            ->where('id', '>', $out['last_id'])
            ->count();
        $out['done'] = $out['remaining'] === 0;

        return $out;
    }

    private function baseQuery(?string $kpId): Builder
    {
        $query = Series::query()
            ->whereNotNull('kp_id')
            ->where('kp_id', '!=', '');

        if ($kpId !== null && $kpId !== '') {
            $query->where('kp_id', $kpId);
        }

        return $query;
    }

    /**
     * @param array{provider: string, iframe_url: string, is_active: bool, alloha_translation_id: null} $payload
     */
    public function upsertMainTab(Series $series, array $payload, int $position): void
    {
        $existing = PlayerSource::query()
            ->where('series_id', $series->id)
            ->where('source_key', self::SOURCE_KEY)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $keepId = $existing->first()?->id;

        if ($existing->isEmpty()) {
            $created = PlayerSource::query()->create(array_merge($payload, [
                'series_id' => $series->id,
                'source_key' => self::SOURCE_KEY,
                'priority' => 0,
            ]));
            PlayerUrlHelper::applyPlayerTabPosition($series, (int) $created->id, $position);

            return;
        }

        $keep = $existing->first();
        $keep->update($payload);

        $deleteIds = $existing->skip(1)->pluck('id')->all();
        if ($deleteIds !== []) {
            PlayerSource::query()->whereIn('id', $deleteIds)->delete();
        }

        PlayerUrlHelper::applyPlayerTabPosition($series, (int) $keep->id, $position);
    }

    /**
     * @return array{iframe: string, token: string|null, api_calls: int}
     */
    public function resolveIframe(Series $series): array
    {
        $apiCalls = 0;
        $lastToken = null;

        $kpId = trim((string) $series->kp_id);
        if ($kpId !== '' && preg_match('/^\d+$/', $kpId)) {
            $result = $this->resolveByIdentifiers(
                ['kp' => $kpId],
                fn () => $this->client->getMovieByKp($kpId),
            );
            if ($result['iframe'] !== '') {
                return $result;
            }
            $apiCalls += $result['api_calls'];
            $lastToken = $result['token'] ?? $lastToken;
        }

        $imdbId = AllohaClient::normalizeImdbId($series->imdb_id);
        if ($imdbId !== '') {
            $result = $this->resolveByIdentifiers(
                ['imdb' => $imdbId],
                fn () => $this->client->getMovieByImdb($imdbId),
            );
            if ($result['iframe'] !== '') {
                return [
                    'iframe' => $result['iframe'],
                    'token' => $result['token'] ?? $lastToken,
                    'api_calls' => $apiCalls + $result['api_calls'],
                ];
            }
            $apiCalls += $result['api_calls'];
            $lastToken = $result['token'] ?? $lastToken;
        }

        $tmdbId = trim((string) ($series->tmdb_id ?? ''));
        if ($tmdbId !== '' && preg_match('/^\d+$/', $tmdbId)) {
            $result = $this->resolveByIdentifiers(
                ['tmdb' => $tmdbId],
                fn () => $this->client->getMovieByTmdb($tmdbId),
            );
            if ($result['iframe'] !== '') {
                return [
                    'iframe' => $result['iframe'],
                    'token' => $result['token'] ?? $lastToken,
                    'api_calls' => $apiCalls + $result['api_calls'],
                ];
            }
            $apiCalls += $result['api_calls'];
            $lastToken = $result['token'] ?? $lastToken;
        }

        $storedToken = trim((string) ($series->alloha_token ?? ''));
        if ($storedToken !== '') {
            $existsByToken = $this->client->movieExists(['token' => $storedToken]);
            $apiCalls++;

            if (!empty($existsByToken['exists'])) {
                $iframe = $this->normalizeIframe((string) ($existsByToken['iframe'] ?? ''));
                if ($iframe !== '') {
                    return [
                        'iframe' => $iframe,
                        'token' => $storedToken,
                        'api_calls' => $apiCalls,
                    ];
                }
            }
        }

        return ['iframe' => '', 'token' => $lastToken, 'api_calls' => $apiCalls];
    }

    /**
     * @param array{kp?: int|string, imdb?: string, tmdb?: int|string, token?: string} $identifiers
     * @param callable(): array<string, mixed> $fetchDetails
     * @return array{iframe: string, token: string|null, api_calls: int}
     */
    private function resolveByIdentifiers(array $identifiers, callable $fetchDetails): array
    {
        $apiCalls = 0;

        $exists = $this->client->movieExists($identifiers);
        $apiCalls++;

        if (!empty($exists['exists'])) {
            $iframe = $this->normalizeIframe((string) ($exists['iframe'] ?? ''));
            if ($iframe !== '') {
                return ['iframe' => $iframe, 'token' => null, 'api_calls' => $apiCalls];
            }
        }

        $response = $fetchDetails();
        $apiCalls++;

        if ($response === []) {
            return ['iframe' => '', 'token' => null, 'api_calls' => $apiCalls];
        }

        return $this->parseMovieResponse($response, $apiCalls);
    }

    /**
     * @param array<string, mixed> $response
     * @return array{iframe: string, token: string|null, api_calls: int}
     */
    private function parseMovieResponse(array $response, int $apiCalls): array
    {
        $data = $response['data'] ?? $response;
        $token = is_array($data) ? trim((string) ($data['token'] ?? '')) : '';

        if (is_array($data)) {
            $iframe = $this->normalizeIframe((string) ($data['iframe'] ?? ''));
            if ($iframe !== '') {
                return [
                    'iframe' => $iframe,
                    'token' => $token !== '' ? $token : null,
                    'api_calls' => $apiCalls,
                ];
            }
        }

        $mapped = AllohaMapper::toSeriesAttributes($response);

        foreach ($mapped['_translations'] ?? [] as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $iframe = $this->normalizeIframe((string) ($translation['iframe'] ?? ''));
            if ($iframe !== '') {
                $mappedToken = trim((string) ($mapped['alloha_token'] ?? ''));

                return [
                    'iframe' => $iframe,
                    'token' => $mappedToken !== '' ? $mappedToken : null,
                    'api_calls' => $apiCalls,
                ];
            }
        }

        $mappedToken = trim((string) ($mapped['alloha_token'] ?? ''));
        if ($mappedToken !== '') {
            $existsByToken = $this->client->movieExists(['token' => $mappedToken]);
            $apiCalls++;

            if (!empty($existsByToken['exists'])) {
                $iframe = $this->normalizeIframe((string) ($existsByToken['iframe'] ?? ''));
                if ($iframe !== '') {
                    return [
                        'iframe' => $iframe,
                        'token' => $mappedToken,
                        'api_calls' => $apiCalls,
                    ];
                }
            }
        }

        return ['iframe' => '', 'token' => $token !== '' ? $token : null, 'api_calls' => $apiCalls];
    }

    public function normalizeIframe(string $iframe): string
    {
        return PlayerUrlHelper::normalizePlayerContent(trim($iframe));
    }

    public function buildIframeFromToken(string $token): string
    {
        $src = 'https://alloha.tv/?token=' . rawurlencode(trim($token));

        return sprintf(
            '<iframe src="%s" width="100%%" height="100%%" frameborder="0" allowfullscreen></iframe>',
            htmlspecialchars($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
