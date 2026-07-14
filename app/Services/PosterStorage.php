<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PosterStorage
{
    public function __construct(
        private readonly PosterKeyBuilder $keyBuilder,
        private readonly ImageOptimizer $optimizer,
    ) {
    }

    public function publicUrl(string $relativePath): string
    {
        return '/storage/' . ltrim($relativePath, '/');
    }

    public function storeFromUpload(UploadedFile $file, PosterContext $context): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $binary = (string)file_get_contents($file->getRealPath());
        return $this->storeBinary($binary, $ext, $context);
    }

    public function storeFromUrl(?string $url, PosterContext $context, bool $optimize = true): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'LordSerialBot/1.0',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ])
                ->get($url);
            if (!$response->ok()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > $this->optimizer->maxUploadBytes()) {
                return null;
            }

            $ext = $this->guessExtension($url, (string)$response->header('Content-Type'));

            // Keep SVG/logo binaries as-is — poster optimizer often breaks transparent logos.
            if (!$optimize || $ext === 'svg') {
                return $this->storeBinaryRaw($body, $ext === 'svg' ? 'svg' : ($ext === 'jpeg' ? 'jpg' : $ext), $context);
            }

            return $this->storeBinary($body, $ext, $context);
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeBinaryRaw(string $binary, string $ext, PosterContext $context): string
    {
        $key = $this->keyBuilder->build($context);
        $path = $this->buildPath($key, $ext);
        Storage::disk('public')->put($path, $binary);

        return $this->publicUrl($path);
    }

    private function storeBinary(string $binary, string $sourceExt, PosterContext $context): string
    {
        $processed = $this->optimizer->process($binary, $sourceExt);
        if ($processed === null) {
            $processed = ['body' => $binary, 'ext' => $sourceExt === 'jpeg' ? 'jpg' : $sourceExt];
        }

        $key = $this->keyBuilder->build($context);
        $path = $this->buildPath($key, $processed['ext']);
        Storage::disk('public')->put($path, $processed['body']);

        return $this->publicUrl($path);
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
