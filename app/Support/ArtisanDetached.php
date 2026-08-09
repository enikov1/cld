<?php

namespace App\Support;

/**
 * Starts an artisan command in a detached OS process so the HTTP request can return immediately.
 */
class ArtisanDetached
{
    /**
     * @param list<string> $artisanArgs Arguments after "artisan", e.g. ['backup:run', '--force']
     */
    public static function spawn(array $artisanArgs): void
    {
        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cmd = array_merge([$php, base_path('artisan')], array_values($artisanArgs));
        $cwd = base_path();

        if (PHP_OS_FAMILY === 'Windows') {
            $line = implode(' ', array_map(static function (string $part): string {
                return '"' . str_replace('"', '""', $part) . '"';
            }, $cmd));

            // start /B keeps the child alive after PHP request ends (OSPanel / Apache / php-cgi).
            pclose(popen('cd /d "' . $cwd . '" && start /B "" ' . $line . ' > NUL 2>&1', 'r'));

            return;
        }

        $line = implode(' ', array_map('escapeshellarg', $cmd));
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $line . ' > /dev/null 2>&1 &');
    }
}
