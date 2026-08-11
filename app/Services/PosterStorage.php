<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PosterStorage
{
    /** Wider assets (brand background / gallery) — keep more detail than posters. */
    private const WIDE_MAX_WIDTH = 1920;

    public function __construct(
        private readonly PosterKeyBuilder $keyBuilder,
        private readonly ImageOptimizer $optimizer,
    ) {
    }

    public function publicUrl(string $relativePath): string
    {
        return '/storage/' . ltrim($relativePath, '/');
    }

    /**
     * @return array{width: ?int, height: ?int, bytes: ?int, mime: ?string, format: ?string}|null
     */
    public function inspectPublicUrl(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '' || !str_starts_with($url, '/storage/')) {
            return null;
        }

        $path = ltrim(substr($url, strlen('/storage/')), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return null;
        }

        $bytes = $disk->size($path);
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        $width = null;
        $height = null;
        $mime = null;
        try {
            $fullPath = $disk->path($path);
            $info = @getimagesize($fullPath);
            if (is_array($info)) {
                $width = isset($info[0]) ? (int) $info[0] : null;
                $height = isset($info[1]) ? (int) $info[1] : null;
                $mime = isset($info['mime']) ? (string) $info['mime'] : null;
            }
        } catch (\Throwable) {
            // ignore — still return size/format when available
        }

        return [
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes > 0 ? $bytes : null,
            'mime' => $mime,
            'format' => $format !== '' ? $format : null,
        ];
    }

    public function storeFromUpload(UploadedFile $file, PosterContext $context): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $binary = (string)file_get_contents($file->getRealPath());

        return $this->storeBinary($binary, $ext, $context, true);
    }

    public function storeFromUrl(?string $url, PosterContext $context, bool $optimize = true): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || !$this->isSafeRemoteUrl($url)) {
            return null;
        }

        $candidates = [$url];
        // If a TMDB /original URL is too heavy, fall back to a resized CDN variant.
        if (preg_match('#^(https?://image\.tmdb\.org/t/p)/original(/.*)$#i', $url, $m)) {
            $candidates[] = $m[1] . '/w1280' . $m[2];
            $candidates[] = $m[1] . '/w780' . $m[2];
        } elseif (preg_match('#^(https?://image\.tmdb\.org/t/p)/w\d+(/.*)$#i', $url, $m)) {
            $candidates[] = $m[1] . '/w780' . $m[2];
            $candidates[] = $m[1] . '/w500' . $m[2];
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $stored = $this->downloadAndStore($candidate, $context, $optimize);
            if ($stored !== null) {
                return $stored;
            }
        }

        return null;
    }

    private function downloadAndStore(string $url, PosterContext $context, bool $optimize): ?string
    {
        if (!$this->isSafeRemoteUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'User-Agent' => 'LordSerialBot/1.0',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                ])
                ->get($url);
            if (!$response->ok()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > $this->optimizer->maxUploadBytes()) {
                return null;
            }

            $ext = $this->guessExtension($url, (string) $response->header('Content-Type'));

            // Keep SVG/logo binaries as-is — poster optimizer often breaks transparent logos.
            if (!$optimize || $ext === 'svg') {
                return $this->storeBinaryRaw($body, $ext === 'svg' ? 'svg' : ($ext === 'jpeg' ? 'jpg' : $ext), $context);
            }

            return $this->storeBinary($body, $ext, $context, true);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Store from remote https URL or local /storage/... path. Always optimizes raster images.
     */
    public function storeOptimizedSource(string $source, PosterContext $context): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $source)) {
            return $this->storeFromUrl($source, $context, true);
        }

        if (!str_starts_with($source, '/storage/')) {
            return null;
        }

        $path = ltrim(substr($source, strlen('/storage/')), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return null;
        }

        try {
            $binary = $disk->get($path);
        } catch (\Throwable) {
            return null;
        }

        if ($binary === '' || strlen($binary) > $this->optimizer->maxUploadBytes()) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext === 'svg') {
            return $this->storeBinaryRaw($binary, 'svg', $context);
        }
        if (!in_array($ext, ['jpg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        return $this->storeBinary($binary, $ext, $context, true);
    }

    private function isSafeRemoteUrl(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = gethostbynamel($host) ?: [];
            $ips = array_merge($ips, $resolved);
            $ipv6 = @dns_get_record($host, DNS_AAAA) ?: [];
            foreach ($ipv6 as $row) {
                if (!empty($row['ipv6'])) {
                    $ips[] = $row['ipv6'];
                }
            }
        }

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function storeBinaryRaw(string $binary, string $ext, PosterContext $context): string
    {
        $key = $this->keyBuilder->build($context);
        $path = $this->buildPath($key, $ext);
        Storage::disk('public')->put($path, $binary);

        return $this->publicUrl($path);
    }

    private function storeBinary(string $binary, string $sourceExt, PosterContext $context, bool $optimize): string
    {
        if (!$optimize) {
            return $this->storeBinaryRaw($binary, $sourceExt === 'jpeg' ? 'jpg' : $sourceExt, $context);
        }

        [$maxWidth, $maxHeight] = $this->limitsForContext($context);
        $options = [];
        if (strtolower(trim((string)($context->variant ?? ''))) === 'brand') {
            $options['edge_fade_rgb'] = \App\Support\SiteBranding::backgroundColorRgb();
        }

        $processed = $this->optimizer->process($binary, $sourceExt, $maxWidth, $maxHeight, $options);
        if ($processed === null) {
            $processed = ['body' => $binary, 'ext' => $sourceExt === 'jpeg' ? 'jpg' : $sourceExt];
        }

        $key = $this->keyBuilder->build($context);
        $path = $this->buildPath($key, $processed['ext']);
        Storage::disk('public')->put($path, $processed['body']);

        return $this->publicUrl($path);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function limitsForContext(PosterContext $context): array
    {
        $variant = strtolower(trim((string)($context->variant ?? '')));
        if ($variant === 'brand' || str_starts_with($variant, 'gallery')) {
            return [self::WIDE_MAX_WIDTH, 0];
        }

        // null → ImageOptimizer falls back to SiteConfig poster limits
        return [null, null];
    }

    private function buildPath(string $key, string $ext): string
    {
        $safe = Str::slug($key);
        if ($safe === '') {
            $safe = 'item-' . Str::random(8);
        }

        $ext = strtolower($ext);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return 'posters/' . $safe . '.' . $ext;
    }

    private function guessExtension(string $url, string $contentType): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        return match (true) {
            str_contains($contentType, 'svg') => 'svg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };
    }
}
