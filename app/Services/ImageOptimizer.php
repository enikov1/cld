<?php

namespace App\Services;

use App\Support\SiteConfig;

class ImageOptimizer
{
    /**
     * @return array{body: string, ext: string}|null
     */
    public function process(string $binary, string $sourceExt): ?array
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

        $resized = $this->resize($image);
        try {
            $targetExt = $this->resolveOutputExt($sourceExt);
            $body = $this->encode($resized, $targetExt);

            if ($body === null || $body === '') {
                return null;
            }

            return ['body' => $body, 'ext' => $targetExt];
        } finally {
            imagedestroy($resized);
        }
    }

    public function maxUploadBytes(): int
    {
        return max(100, SiteConfig::int('images_poster_max_upload_kb')) * 1024;
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

    private function resize(\GdImage $image): \GdImage
    {
        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $maxW = SiteConfig::int('images_poster_max_width');
        $maxH = SiteConfig::int('images_poster_max_height');

        if ($maxW <= 0 && $maxH <= 0) {
            return $image;
        }

        $targetW = $srcW;
        $targetH = $srcH;

        if ($maxW > 0 && $targetW > $maxW) {
            $ratio = $maxW / $targetW;
            $targetW = $maxW;
            $targetH = (int)round($targetH * $ratio);
        }

        if ($maxH > 0 && $targetH > $maxH) {
            $ratio = $maxH / $targetH;
            $targetH = $maxH;
            $targetW = (int)round($targetW * $ratio);
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
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);
        imagedestroy($image);

        return $resized;
    }

    private function encode(\GdImage $image, string $ext): ?string
    {
        $quality = max(10, min(100, SiteConfig::int('images_poster_quality')));

        ob_start();
        $ok = match ($ext) {
            'png' => imagepng($image, null, (int)round((100 - $quality) / 11)),
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
            default => $this->normalizeExt($sourceExt),
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
