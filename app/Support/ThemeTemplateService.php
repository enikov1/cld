<?php

namespace App\Support;

use InvalidArgumentException;

class ThemeTemplateService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['tpl', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'woff', 'woff2'];

    private const MAX_BYTES = 524288;

    private const MAX_UPLOAD_BYTES = 2097152;

    /**
     * @return list<array{key: string, title: string, path?: string, isLeaf?: bool, children?: list<array<string, mixed>>}>
     */
    public static function listTree(string $theme): array
    {
        $root = self::themeRoot($theme);

        return self::buildTree($root, $root);
    }

    /**
     * Unique CSS class names from theme stylesheets (for editor autocomplete).
     *
     * @return list<string>
     */
    public static function listCssClasses(string $theme): array
    {
        $assetsDir = self::themeRoot($theme) . DIRECTORY_SEPARATOR . 'assets';
        if (!is_dir($assetsDir)) {
            return [];
        }

        $classes = [];
        $files = glob($assetsDir . DIRECTORY_SEPARATOR . '*.css') ?: [];
        foreach ($files as $file) {
            $base = basename($file);
            if (str_ends_with($base, '.min.css')) {
                continue;
            }
            if (str_starts_with($base, 'font-awesome')) {
                continue;
            }

            $content = @file_get_contents($file);
            if ($content === false || $content === '') {
                continue;
            }

            if (!preg_match_all('/\.([a-zA-Z_][\w-]*)/', $content, $matches)) {
                continue;
            }

            foreach ($matches[1] as $className) {
                $classes[$className] = true;
            }
        }

        $list = array_keys($classes);
        sort($list, SORT_STRING);

        if (count($list) > 2500) {
            $list = array_slice($list, 0, 2500);
        }

        return $list;
    }

    public static function readFile(string $theme, string $path): array
    {
        $relative = self::normalizeRelativePath($path);
        $full = self::resolveExistingFile($theme, $relative);
        $modifiedAt = date('c', filemtime($full) ?: time());
        $size = filesize($full) ?: 0;

        if (!self::isTextFile($relative)) {
            return [
                'path' => $relative,
                'content' => '',
                'binary' => true,
                'preview_url' => self::publicAssetUrl($theme, $relative),
                'size' => $size,
                'modified_at' => $modifiedAt,
            ];
        }

        $content = file_get_contents($full);
        if ($content === false) {
            throw new InvalidArgumentException('Не удалось прочитать файл.');
        }

        $result = [
            'path' => $relative,
            'content' => $content,
            'binary' => false,
            'size' => $size,
            'modified_at' => $modifiedAt,
        ];

        if (self::isPreviewableImage($relative)) {
            $result['preview_url'] = self::publicAssetUrl($theme, $relative);
        }

        return $result;
    }

    public static function writeFile(string $theme, string $path, string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Файл слишком большой (макс. 512 КБ).');
        }

        $relative = self::normalizeRelativePath($path);
        $full = self::resolveWritableFile($theme, $relative);
        $dir = dirname($full);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Не удалось создать каталог.');
        }

        if (file_put_contents($full, $content) === false) {
            throw new InvalidArgumentException('Не удалось сохранить файл.');
        }

        return [
            'path' => $relative,
            'size' => filesize($full) ?: 0,
            'modified_at' => date('c', filemtime($full) ?: time()),
        ];
    }

    public static function createFile(string $theme, string $path, string $content = ''): array
    {
        $relative = self::normalizeRelativePath($path);
        $full = self::resolveWritableFile($theme, $relative);

        if (is_file($full)) {
            throw new InvalidArgumentException('Файл уже существует.');
        }

        return self::writeFile($theme, $relative, $content);
    }

    public static function deleteFile(string $theme, string $path): void
    {
        $relative = self::normalizeRelativePath($path);

        if ($relative === 'layout.tpl') {
            throw new InvalidArgumentException('Нельзя удалить layout.tpl.');
        }

        $full = self::resolveExistingFile($theme, $relative);

        if (!unlink($full)) {
            throw new InvalidArgumentException('Не удалось удалить файл.');
        }
    }

    public static function createDirectory(string $theme, string $path): array
    {
        $relative = self::normalizeDirectoryPath($path);
        $full = self::joinThemePath($theme, $relative);

        if (is_dir($full)) {
            throw new InvalidArgumentException('Папка уже существует.');
        }

        if (is_file($full)) {
            throw new InvalidArgumentException('Файл с таким именем уже существует.');
        }

        if (!mkdir($full, 0755, true) && !is_dir($full)) {
            throw new InvalidArgumentException('Не удалось создать папку.');
        }

        return ['path' => $relative];
    }

    public static function deleteEntry(string $theme, string $path): void
    {
        $relative = self::normalizeAnyPath($path);

        if ($relative === 'layout.tpl') {
            throw new InvalidArgumentException('Нельзя удалить layout.tpl.');
        }

        $root = self::themeRoot($theme);
        $full = self::joinThemePath($theme, $relative);

        if (is_file($full)) {
            self::assertWithinTheme($theme, $full);
            if (!unlink($full)) {
                throw new InvalidArgumentException('Не удалось удалить файл.');
            }

            return;
        }

        if (is_dir($full)) {
            if (realpath($full) === realpath($root)) {
                throw new InvalidArgumentException('Нельзя удалить корень темы.');
            }
            self::assertWithinTheme($theme, $full);
            self::deleteDirectoryRecursive($full, $root);

            return;
        }

        throw new InvalidArgumentException('Файл или папка не найдены.');
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function renameEntry(string $theme, string $from, string $to): array
    {
        $fromRelative = self::normalizeAnyPath($from);
        $fromFull = self::joinThemePath($theme, $fromRelative);

        if (!is_file($fromFull) && !is_dir($fromFull)) {
            throw new InvalidArgumentException('Исходный файл или папка не найдены.');
        }

        if (is_file($fromFull) && $fromRelative === 'layout.tpl') {
            throw new InvalidArgumentException('Нельзя переименовать layout.tpl.');
        }

        $toRelative = is_dir($fromFull)
            ? self::normalizeDirectoryPath($to)
            : self::normalizeRelativePath($to);

        if (is_file($fromFull) && $toRelative === 'layout.tpl') {
            throw new InvalidArgumentException('Нельзя заменить layout.tpl.');
        }

        $toFull = self::joinThemePath($theme, $toRelative);

        if (is_dir($fromFull)) {
            $root = self::themeRoot($theme);
            if (realpath($fromFull) === realpath($root)) {
                throw new InvalidArgumentException('Нельзя переименовать корень темы.');
            }
        }

        if (file_exists($toFull)) {
            throw new InvalidArgumentException('Целевой путь уже занят.');
        }

        $toDir = dirname($toFull);
        if (!is_dir($toDir) && !mkdir($toDir, 0755, true) && !is_dir($toDir)) {
            throw new InvalidArgumentException('Не удалось создать каталог назначения.');
        }

        if (!rename($fromFull, $toFull)) {
            throw new InvalidArgumentException('Не удалось переименовать.');
        }

        return ['from' => $fromRelative, 'to' => $toRelative];
    }

    /**
     * @param \Illuminate\Http\UploadedFile $uploadedFile
     */
    public static function uploadFile(string $theme, string $path, $uploadedFile): array
    {
        $relative = self::normalizeRelativePath($path);
        $full = self::resolveWritableFile($theme, $relative);

        if (is_file($full)) {
            throw new InvalidArgumentException('Файл уже существует.');
        }

        if (!$uploadedFile->isValid()) {
            throw new InvalidArgumentException('Ошибка загрузки файла.');
        }

        if ($uploadedFile->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Файл слишком большой (макс. 2 МБ).');
        }

        $originalName = (string)$uploadedFile->getClientOriginalName();
        $originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $targetExt = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if ($originalExt !== '' && $originalExt !== $targetExt) {
            throw new InvalidArgumentException('Расширение файла не совпадает с путём назначения.');
        }

        $dir = dirname($full);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Не удалось создать каталог.');
        }

        if (!$uploadedFile->move($dir, basename($full))) {
            throw new InvalidArgumentException('Не удалось сохранить загруженный файл.');
        }

        return [
            'path' => $relative,
            'size' => filesize($full) ?: 0,
            'modified_at' => date('c', filemtime($full) ?: time()),
        ];
    }

    public static function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException('Некорректный путь к файлу.');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $path)) {
            throw new InvalidArgumentException('Недопустимые символы в пути.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Разрешены только файлы .tpl, .css, .js, .svg, .png, .jpg, .jpeg, .gif, .webp, .woff, .woff2');
        }

        return $path;
    }

    public static function normalizeDirectoryPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException('Некорректный путь к папке.');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $path)) {
            throw new InvalidArgumentException('Недопустимые символы в пути папки.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Некорректный путь к папке.');
            }
        }

        return $path;
    }

    public static function normalizeAnyPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException('Некорректный путь.');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $path)) {
            throw new InvalidArgumentException('Недопустимые символы в пути.');
        }

        return $path;
    }

    private static function themeRoot(string $theme): string
    {
        if (!ThemeManager::themeExists($theme)) {
            throw new InvalidArgumentException('Тема не найдена.');
        }

        $root = realpath(ThemeManager::themesRoot() . DIRECTORY_SEPARATOR . $theme);
        if ($root === false) {
            throw new InvalidArgumentException('Тема не найдена.');
        }

        return $root;
    }

    /**
     * @return list<array{key: string, title: string, path?: string, isLeaf?: bool, children?: list<array<string, mixed>>}>
     */
    private static function buildTree(string $dir, string $root): array
    {
        $nodes = [];
        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        $dirs = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $dirs[] = $entry;
            } elseif (is_file($full) && self::isAllowedFile($entry)) {
                $files[] = $entry;
            }
        }

        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($dirs as $name) {
            $full = $dir . DIRECTORY_SEPARATOR . $name;
            $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
            $children = self::buildTree($full, $root);
            $nodes[] = [
                'key' => $relative,
                'title' => $name,
                'children' => $children,
            ];
        }

        foreach ($files as $name) {
            $full = $dir . DIRECTORY_SEPARATOR . $name;
            $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
            $nodes[] = [
                'key' => $relative,
                'title' => $name,
                'path' => $relative,
                'isLeaf' => true,
            ];
        }

        return $nodes;
    }

    private static function isAllowedFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, self::ALLOWED_EXTENSIONS, true);
    }

    private static function isTextFile(string $relative): bool
    {
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['tpl', 'css', 'js', 'svg'], true);
    }

    private static function isPreviewableImage(string $relative): bool
    {
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true);
    }

    private static function publicAssetUrl(string $theme, string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (!str_starts_with($relative, 'assets/')) {
            $relative = 'assets/' . $relative;
        }

        return ThemeManager::assetUrl($relative, $theme);
    }

    private static function resolveExistingFile(string $theme, string $relative): string
    {
        $full = self::joinThemePath($theme, $relative);
        if (!is_file($full)) {
            throw new InvalidArgumentException('Файл не найден.');
        }

        self::assertWithinTheme($theme, $full);

        return $full;
    }

    private static function resolveWritableFile(string $theme, string $relative): string
    {
        $full = self::joinThemePath($theme, $relative);
        self::assertWithinTheme($theme, $full);

        return $full;
    }

    private static function joinThemePath(string $theme, string $relative): string
    {
        $root = self::themeRoot($theme);
        $parts = array_values(array_filter(explode('/', $relative), static fn (string $p) => $p !== ''));

        return $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private static function assertWithinTheme(string $theme, string $fullPath): void
    {
        $root = self::themeRoot($theme);
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

        $cursor = $fullPath;
        while (true) {
            if (is_link($cursor)) {
                throw new InvalidArgumentException('Символические ссылки в теме запрещены.');
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            if (file_exists($cursor)) {
                break;
            }
            $cursor = $parent;
        }

        $targetDir = is_file($fullPath) ? dirname($fullPath) : $fullPath;
        $existing = $targetDir;
        while (!file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        $targetReal = realpath($existing);
        if ($targetReal === false) {
            throw new InvalidArgumentException('Путь выходит за пределы темы.');
        }

        $targetNorm = rtrim(str_replace('\\', '/', $targetReal), '/');
        if ($targetNorm !== $rootNorm && !str_starts_with($targetNorm, $rootNorm . '/')) {
            throw new InvalidArgumentException('Путь выходит за пределы темы.');
        }

        $check = $targetReal;
        while ($check !== false && $check !== '' && $check !== dirname($check)) {
            if (is_link($check)) {
                throw new InvalidArgumentException('Символические ссылки в теме запрещены.');
            }
            $norm = rtrim(str_replace('\\', '/', $check), '/');
            if ($norm === $rootNorm) {
                break;
            }
            $check = dirname($check);
        }
    }

    private static function deleteDirectoryRecursive(string $dir, string $themeRoot): void
    {
        $entries = scandir($dir);
        if ($entries === false) {
            throw new InvalidArgumentException('Не удалось прочитать папку.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                self::deleteDirectoryRecursive($full, $themeRoot);
                continue;
            }

            if (is_file($full)) {
                $relative = ltrim(str_replace('\\', '/', substr($full, strlen($themeRoot))), '/');
                if ($relative === 'layout.tpl') {
                    throw new InvalidArgumentException('Нельзя удалить layout.tpl.');
                }
                if (!unlink($full)) {
                    throw new InvalidArgumentException('Не удалось удалить файл в папке.');
                }
            }
        }

        if (!rmdir($dir)) {
            throw new InvalidArgumentException('Не удалось удалить папку.');
        }
    }
}
