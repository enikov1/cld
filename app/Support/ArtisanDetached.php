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
        $php = self::resolvePhpBinary();
        $cmd = array_merge([$php, base_path('artisan')], array_values($artisanArgs));
        $cwd = base_path();

        if (PHP_OS_FAMILY === 'Windows') {
            $line = implode(' ', array_map(static function (string $part): string {
                return '"' . str_replace('"', '""', $part) . '"';
            }, $cmd));

            // start /B keeps the child alive after PHP request ends (OSPanel / Apache / php-cgi).
            // Redirect via cmd so the child is truly detached and does not inherit the web SAPI.
            pclose(popen('cmd /c "cd /d "' . $cwd . '" && start /B "" ' . $line . ' > NUL 2>&1"', 'r'));

            return;
        }

        $line = implode(' ', array_map('escapeshellarg', $cmd));
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $line . ' > /dev/null 2>&1 &');
    }

    /**
     * Prefer CLI php.exe — under Apache PHP_BINARY is often php-cgi.exe, which hangs on artisan.
     */
    public static function resolvePhpBinary(): string
    {
        $configured = env('PHP_BINARY_PATH');
        if (is_string($configured) && trim($configured) !== '' && is_file(trim($configured))) {
            return trim($configured);
        }

        $binary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $normalized = str_replace('\\', '/', $binary);

        if (preg_match('/php-cgi(\d.*)?\.exe$/i', $normalized) || preg_match('/php-cgi(\d.*)?$/i', $normalized)) {
            $cli = preg_replace('/php-cgi(\d.*)?(\.exe)?$/i', 'php$1$2', $binary);
            if (is_string($cli) && $cli !== $binary && is_file($cli)) {
                return $cli;
            }

            $dir = dirname($binary);
            foreach (['php.exe', 'php'] as $name) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        // php-fpm / php-win edge cases: look for php.exe next to the SAPI binary.
        if (PHP_OS_FAMILY === 'Windows' && !preg_match('/php\.exe$/i', $normalized)) {
            $candidate = dirname($binary) . DIRECTORY_SEPARATOR . 'php.exe';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $binary;
    }
}
