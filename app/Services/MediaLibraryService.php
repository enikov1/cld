<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MediaLibraryService
{
    private const ALLOWED_ROOTS = ['posters', 'branding'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico'];

    public function __construct(
        private readonly ImageOptimizer $optimizer,
    ) {
    }

    /**
     * @return array{
     *   items: list<array{url: string, path: string, type: string, name: string, size: int|null, mtime: int|null, mime: string|null}>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   last_page: int
     * }
     */
    public function list(int $page = 1, int $perPage = 48, string $q = '', ?string $type = null): array
    {
        $page = max(1, $page);
        $perPage = min(200, max(12, $perPage));
        $q = mb_strtolower(trim($q));
        $type = $type !== null && $type !== '' ? strtolower(trim($type)) : null;

        $disk = Storage::disk('public');
        $entries = [];

        $roots = self::ALLOWED_ROOTS;
        if ($type === 'poster' || $type === 'posters') {
            $roots = ['posters'];
        } elseif ($type === 'branding') {
            $roots = ['branding'];
        }

        foreach ($roots as $root) {
            if (!$disk->exists($root)) {
                continue;
            }

            foreach ($disk->files($root) as $path) {
                $path = str_replace('\\', '/', $path);
                if (!$this->isAllowedPath($path) || !$this->isImagePath($path)) {
                    continue;
                }

                $name = basename($path);
                if ($q !== '' && !str_contains(mb_strtolower($name), $q) && !str_contains(mb_strtolower($path), $q)) {
                    continue;
                }

                $mtime = null;
                $size = null;
                try {
                    $mtime = $disk->lastModified($path) ?: null;
                    $bytes = $disk->size($path);
                    $size = $bytes > 0 ? $bytes : null;
                } catch (\Throwable) {
                    // keep null meta
                }

                $entries[] = [
                    'path' => $path,
                    'name' => $name,
                    'mtime' => $mtime,
                    'size' => $size,
                ];
            }
        }

        usort($entries, static function (array $a, array $b): int {
            $mtimeA = $a['mtime'] ?? 0;
            $mtimeB = $b['mtime'] ?? 0;
            if ($mtimeA === $mtimeB) {
                return strcmp($a['path'], $b['path']);
            }

            return $mtimeB <=> $mtimeA;
        });

        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($entries, $offset, $perPage);

        $items = array_map(
            fn (array $entry) => $this->serializePath($entry['path'], inspectMime: false, size: $entry['size'], mtime: $entry['mtime']),
            $slice,
        );

        return [
            'items' => array_values($items),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * @return array{url: string, path: string, type: string, name: string, size: int|null, mtime: int|null, mime: string|null}
     */
    public function upload(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $ext = 'jpg';
        }

        $binary = (string) file_get_contents($file->getRealPath());
        if ($binary === '') {
            throw new InvalidArgumentException('Пустой файл');
        }

        if (strlen($binary) > $this->optimizer->maxUploadBytes()) {
            throw new InvalidArgumentException('Файл слишком большой');
        }

        if ($ext !== 'ico') {
            $processed = $this->optimizer->process($binary, $ext);
            if ($processed !== null) {
                $binary = $processed['body'];
                $ext = $processed['ext'] === 'jpeg' ? 'jpg' : $processed['ext'];
            }
        }

        $hash = substr(hash('sha256', $binary), 0, 16);
        $path = 'posters/media-' . $hash . '.' . $ext;

        Storage::disk('public')->put($path, $binary);

        return $this->serializePath($path, inspectMime: true);
    }

    public function delete(string $path): bool
    {
        $path = $this->normalizePath($path);
        if (!$this->isAllowedPath($path)) {
            throw new InvalidArgumentException('Удаление разрешено только из posters/ и branding/');
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return false;
        }

        return $disk->delete($path);
    }

    /**
     * @return array{url: string, path: string, type: string, name: string, size: int|null, mtime: int|null, mime: string|null}
     */
    private function serializePath(
        string $path,
        bool $inspectMime = true,
        ?int $size = null,
        ?int $mtime = null,
    ): array {
        $disk = Storage::disk('public');

        if ($size === null || $mtime === null) {
            try {
                if ($disk->exists($path)) {
                    if ($size === null) {
                        $bytes = $disk->size($path);
                        $size = $bytes > 0 ? $bytes : null;
                    }
                    if ($mtime === null) {
                        $mtime = $disk->lastModified($path) ?: null;
                    }
                }
            } catch (\Throwable) {
                // ignore missing meta
            }
        }

        $mime = $this->mimeFromExtension($path);
        if ($inspectMime) {
            try {
                $fullPath = $disk->path($path);
                $info = @getimagesize($fullPath);
                if (is_array($info) && !empty($info['mime'])) {
                    $mime = (string) $info['mime'];
                } elseif (is_file($fullPath)) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $detected = $finfo->file($fullPath);
                    if (is_string($detected) && $detected !== '') {
                        $mime = $detected;
                    }
                }
            } catch (\Throwable) {
                // keep extension-based mime
            }
        }

        $type = str_starts_with($path, 'branding/') ? 'branding' : 'poster';

        return [
            'url' => '/storage/' . ltrim($path, '/'),
            'path' => $path,
            'type' => $type,
            'name' => basename($path),
            'size' => $size,
            'mtime' => $mtime,
            'mime' => $mime,
        ];
    }

    private function mimeFromExtension(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => null,
        };
    }

    private function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        // Collapse accidental absolute public URLs to relative disk paths.
        if (preg_match('#^https?://[^/]+/storage/(.+)$#i', $path, $m)) {
            $path = $m[1];
        }

        return $path;
    }

    private function isAllowedPath(string $path): bool
    {
        $path = $this->normalizePath($path);
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        foreach (self::ALLOWED_ROOTS as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    private function isImagePath(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');

        return in_array($ext, self::IMAGE_EXTENSIONS, true);
    }
}
