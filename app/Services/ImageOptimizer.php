<?php

namespace App\Services;

use App\Support\SiteConfig;

class ImageOptimizer
{
    private const TARGET_CEILING_BYTES = 130 * 1024;

    private const TARGET_FLOOR_BYTES = 64 * 1024;

    private const TARGET_KEEP_RATIO = 0.11;

    /**
     * @return array{body: string, ext: string}|null
     */
    public function process(string $binary, string $sourceExt, ?int $maxWidth = null, ?int $maxHeight = null): ?array
    {
        if (!SiteConfig::bool('images_optimize_enabled')) {
            return ['body' => $binary, 'ext' => $this->normalizeExt($sourceExt)];
        }

        if (!extension_loaded('gd')) {
            return ['body' => $binary, 'ext' => $this->resolveOutputExt($sourceExt)];
        }

        $image = $this->createImage($binary, $sourceExt);
        if ($image === null) {
            return null;
        }

        $resized = $this->resize($image, $maxWidth, $maxHeight);
        try {
            $targetExt = $this->resolveOutputExt($sourceExt);
            $targetBytes = $this->resolveTargetBytes(strlen($binary));
            $body = $this->encodeToTarget($resized, $targetExt, $targetBytes);

            if ($body === null || $body === '') {
                return null;
            }

            return ['body' => $body, 'ext' => $targetExt === 'jpeg' ? 'jpg' : $targetExt];
        } finally {
            imagedestroy($resized);
        }
    }

    public function maxUploadBytes(): int
    {
        return max(100, SiteConfig::int('images_poster_max_upload_kb')) * 1024;
    }

    private function resolveTargetBytes(int $sourceBytes): int
    {
        $byRatio = (int) round($sourceBytes * self::TARGET_KEEP_RATIO);

        return min(self::TARGET_CEILING_BYTES, max(self::TARGET_FLOOR_BYTES, $byRatio));
    }

    private function createImage(string $binary, string $ext): ?\GdImage
    {
        $ext = $this->normalizeExt($ext);

        return match ($ext) {
            'png' => @imagecreatefromstring($binary) ?: null,
            'webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromstring($binary) ?: null) : null,
            'gif' => @imagecreatefromstring($binary) ?: null,
            default => @imagecreatefromstring($binary) ?: null,
        };
    }

    private function resize(\GdImage $image, ?int $maxWidth = null, ?int $maxHeight = null): \GdImage
    {
        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $maxW = $maxWidth ?? SiteConfig::int('images_poster_max_width');
        $maxH = $maxHeight ?? SiteConfig::int('images_poster_max_height');

        if ($maxW <= 0 && $maxH <= 0) {
            return $image;
        }

        $targetW = $srcW;
        $targetH = $srcH;

        if ($maxW > 0 && $targetW > $maxW) {
            $ratio = $maxW / $targetW;
            $targetW = $maxW;
            $targetH = (int) round($targetH * $ratio);
        }

        if ($maxH > 0 && $targetH > $maxH) {
            $ratio = $maxH / $targetH;
            $targetH = $maxH;
            $targetW = (int) round($targetW * $ratio);
        }

        if ($targetW === $srcW && $targetH === $srcH) {
            return $image;
        }

        $resized = imagecreatetruecolor($targetW, $targetH);
        if ($resized === false) {
            return $image;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($resized, 0, 0, $targetW, $targetH, $transparent);
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($image);

        return $resized;
    }

    private function encodeToTarget(\GdImage $image, string $ext, int $targetBytes): ?string
    {
        $configured = max(10, min(100, SiteConfig::int('images_poster_quality')));
        // Prefer aggressive quality for JPEG/WebP (iLoveIMG-like), still respect configured floor.
        $startQuality = min($configured, 70);
        $minQuality = 28;

        if ($ext === 'png' || $ext === 'gif') {
            return $this->encode($image, $ext, $startQuality);
        }

        $bestUnderTarget = null;
        $bestUnderTargetQuality = -1;
        $smallest = null;
        $lo = $minQuality;
        $hi = $startQuality;

        for ($i = 0; $i < 10; $i++) {
            $quality = (int) round(($lo + $hi) / 2);
            $body = $this->encode($image, $ext, $quality);
            if ($body === null) {
                break;
            }

            $size = strlen($body);
            if ($smallest === null || $size < strlen($smallest)) {
                $smallest = $body;
            }

            if ($size <= $targetBytes) {
                if ($quality >= $bestUnderTargetQuality) {
                    $bestUnderTarget = $body;
                    $bestUnderTargetQuality = $quality;
                }
                $lo = $quality;
            } else {
                $hi = $quality - 1;
            }

            if ($lo > $hi) {
                break;
            }
        }

        // Explicit low pass if still above target.
        if ($bestUnderTarget === null) {
            $low = $this->encode($image, $ext, $minQuality);
            if ($low !== null && ($smallest === null || strlen($low) < strlen($smallest))) {
                $smallest = $low;
            }
            if ($low !== null && strlen($low) <= $targetBytes) {
                $bestUnderTarget = $low;
            }
        }

        return $bestUnderTarget ?? $smallest ?? $this->encode($image, $ext, $startQuality);
    }

    private function encode(\GdImage $image, string $ext, int $quality): ?string
    {
        $quality = max(10, min(100, $quality));

        ob_start();
        $ok = match ($ext) {
            'png' => imagepng($image, null, (int) round((100 - $quality) / 11)),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, $quality) : imagejpeg($image, null, $quality),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, $quality),
        };
        $body = ob_get_clean();

        return $ok ? ($body ?: null) : null;
    }

    private function resolveOutputExt(string $sourceExt): string
    {
        $format = SiteConfig::str('images_poster_format');

        return match ($format) {
            'jpg', 'webp', 'png' => $format,
            // "keep" still prefers JPEG for heavy raster sources — much smaller output.
            default => in_array($this->normalizeExt($sourceExt), ['png', 'webp', 'gif', 'jpg'], true)
                ? 'jpg'
                : $this->normalizeExt($sourceExt),
        };
    }

    private function normalizeExt(string $ext): string
    {
        $ext = strtolower($ext);

        return match ($ext) {
            'jpeg' => 'jpg',
            'jpg', 'png', 'webp', 'gif' => $ext,
            default => 'jpg',
        };
    }
}
