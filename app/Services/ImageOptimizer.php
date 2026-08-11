<?php

namespace App\Services;

use App\Support\SiteConfig;

class ImageOptimizer
{
    private const TARGET_CEILING_BYTES = 130 * 1024;

    private const TARGET_FLOOR_BYTES = 64 * 1024;

    private const TARGET_KEEP_RATIO = 0.11;

    /**
     * @param  array{edge_fade_rgb?: array{0: int, 1: int, 2: int}}|null  $options
     * @return array{body: string, ext: string}|null
     */
    public function process(
        string $binary,
        string $sourceExt,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?array $options = null,
    ): ?array {
        if (!SiteConfig::bool('images_optimize_enabled')) {
            // Still allow edge fade when optimization is off but GD is available.
            if (
                extension_loaded('gd')
                && isset($options['edge_fade_rgb'])
                && is_array($options['edge_fade_rgb'])
            ) {
                $image = $this->createImage($binary, $sourceExt);
                if ($image !== null) {
                    try {
                        $this->applyEdgeFade($image, $options['edge_fade_rgb']);
                        $ext = $this->normalizeExt($sourceExt);
                        $body = $this->encode($image, $ext === 'jpg' ? 'jpg' : $ext, 85);
                        if ($body !== null && $body !== '') {
                            return ['body' => $body, 'ext' => $ext];
                        }
                    } finally {
                        imagedestroy($image);
                    }
                }
            }

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
            if (isset($options['edge_fade_rgb']) && is_array($options['edge_fade_rgb'])) {
                $this->applyEdgeFade($resized, $options['edge_fade_rgb']);
            }

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

    /**
     * Bake side + bottom fade into the image using site background RGB.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    public function applyEdgeFade(\GdImage $image, array $rgb): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 8 || $height < 8) {
            return;
        }

        $br = max(0, min(255, (int)($rgb[0] ?? 0)));
        $bg = max(0, min(255, (int)($rgb[1] ?? 0)));
        $bb = max(0, min(255, (int)($rgb[2] ?? 0)));

        $sideFade = (int) max(48, min((int) floor($width / 2), (int) round($width * 0.18)));
        $bottomFade = (int) max(64, min((int) floor($height * 0.85), (int) round($height * 0.55)));

        imagealphablending($image, true);

        for ($x = 0; $x < $sideFade; $x++) {
            $t = $this->smoothStep(1 - ($x / max(1, $sideFade)));
            for ($y = 0; $y < $height; $y++) {
                $this->blendPixel($image, $x, $y, $br, $bg, $bb, $t);
                $this->blendPixel($image, $width - 1 - $x, $y, $br, $bg, $bb, $t);
            }
        }

        for ($i = 0; $i < $bottomFade; $i++) {
            $t = $this->smoothStep(1 - ($i / max(1, $bottomFade)));
            $y = $height - 1 - $i;
            for ($x = 0; $x < $width; $x++) {
                $this->blendPixel($image, $x, $y, $br, $bg, $bb, $t);
            }
        }
    }

    private function smoothStep(float $t): float
    {
        $t = max(0.0, min(1.0, $t));

        return $t * $t * (3 - 2 * $t);
    }

    private function blendPixel(\GdImage $image, int $x, int $y, int $br, int $bg, int $bb, float $t): void
    {
        if ($t <= 0) {
            return;
        }

        $color = imagecolorat($image, $x, $y);
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        $nr = (int) round($r * (1 - $t) + $br * $t);
        $ng = (int) round($g * (1 - $t) + $bg * $t);
        $nb = (int) round($b * (1 - $t) + $bb * $t);

        $allocated = imagecolorallocate($image, $nr, $ng, $nb);
        if ($allocated !== false) {
            imagesetpixel($image, $x, $y, $allocated);
        }
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
