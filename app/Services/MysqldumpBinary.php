<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

final class MysqldumpBinary
{
    public static function resolve(): string
    {
        foreach (self::candidates() as $candidate) {
            if ($candidate === 'mysqldump') {
                if (self::isExecutableInPath('mysqldump')) {
                    return 'mysqldump';
                }
                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Утилита mysqldump не найдена. '
            . 'Укажите полный путь в .env: MYSQLDUMP_PATH=C:\\OSPanel6\\modules\\database\\MySQL-8.4\\bin\\mysqldump.exe '
            . '(путь смотрите в OSPanel → MySQL → папка bin).'
        );
    }

    /**
     * @return list<string>
     */
    private static function candidates(): array
    {
        $items = [];

        $fromEnv = env('MYSQLDUMP_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            $items[] = trim($fromEnv);
        }

        foreach (self::discoverOspanelBinaries() as $path) {
            $items[] = $path;
        }

        $items[] = 'mysqldump';

        return array_values(array_unique($items));
    }

    /**
     * @return list<string>
     */
    private static function discoverOspanelBinaries(): array
    {
        $roots = [];

        $ospanelFromSite = dirname(dirname(dirname(base_path())));
        if (is_dir($ospanelFromSite . DIRECTORY_SEPARATOR . 'modules')) {
            $roots[] = $ospanelFromSite;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $roots[] = 'C:\\OSPanel6';
            $roots[] = 'C:\\OpenServer';
        }

        $paths = [];
        foreach (array_unique($roots) as $root) {
            $base = rtrim(str_replace('\\', '/', $root), '/');

            foreach ([
                $base . '/modules/database/*/bin/mysqldump.exe',
                $base . '/modules/database/*/bin/mysqldump',
                $base . '/modules/MariaDB-*/bin/mysqldump.exe',
                $base . '/modules/MySQL-*/bin/mysqldump.exe',
            ] as $pattern) {
                foreach (glob($pattern) ?: [] as $match) {
                    $paths[] = $match;
                }
            }
        }

        rsort($paths);

        return $paths;
    }

    private static function isExecutableInPath(string $command): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['where.exe', $command]);
        } else {
            $process = new Process(['which', $command]);
        }

        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }
}
