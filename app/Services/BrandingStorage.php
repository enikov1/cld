<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BrandingStorage
{
    private const DIR = 'branding';

    private const MAX_BYTES = 2 * 1024 * 1024;

    private const LOGO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'svg'];

    private const BACKGROUND_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    public function publicUrl(string $relativePath): string
    {
        return '/storage/' . ltrim($relativePath, '/');
    }

    public function storeLogo(UploadedFile $file): string
    {
        $ext = $this->validateExtension($file, self::LOGO_EXTENSIONS);
        $this->validateSize($file);

        return $this->store($file, 'logo', $ext, 'site_logo_url');
    }

    public function storeBackground(UploadedFile $file): string
    {
        $ext = $this->validateExtension($file, self::BACKGROUND_EXTENSIONS);
        $this->validateSize($file);

        return $this->store($file, 'background', $ext, 'site_background_url');
    }

    public function deleteLogo(): void
    {
        $this->delete('logo', 'site_logo_url');
    }

    public function deleteBackground(): void
    {
        $this->delete('background', 'site_background_url');
    }

  /**
     * @param list<string> $allowed
     */
    private function validateExtension(UploadedFile $file, array $allowed): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (!in_array($ext, $allowed, true)) {
            throw new \InvalidArgumentException(
                'Недопустимый формат файла. Разрешены: ' . implode(', ', $allowed)
            );
        }

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    private function validateSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Файл слишком большой. Максимум 2 МБ.');
        }
    }

    private function store(UploadedFile $file, string $basename, string $ext, string $settingKey): string
    {
        $this->deleteExistingFiles($basename);

        $path = self::DIR . '/' . $basename . '.' . $ext;
        Storage::disk('public')->put($path, (string)file_get_contents($file->getRealPath()));

        $url = $this->publicUrl($path);
        SiteSetting::set($settingKey, $url);

        return $url;
    }

    private function delete(string $basename, string $settingKey): void
    {
        $this->deleteExistingFiles($basename);
        SiteSetting::set($settingKey, null);
    }

    private function deleteExistingFiles(string $basename): void
    {
        $disk = Storage::disk('public');
        $absoluteDir = storage_path('app/public/' . self::DIR);
        if (!is_dir($absoluteDir)) {
            return;
        }

        foreach (glob($absoluteDir . DIRECTORY_SEPARATOR . $basename . '.*') ?: [] as $absolute) {
            $relative = self::DIR . '/' . basename($absolute);
            if ($disk->exists($relative)) {
                $disk->delete($relative);
            }
        }
    }
}
