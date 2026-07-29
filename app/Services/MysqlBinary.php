<?php

namespace App\Services;

use RuntimeException;

final class MysqlBinary
{
    public static function resolve(): string
    {
        $fromEnv = env('MYSQL_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '' && is_file(trim($fromEnv))) {
            return trim($fromEnv);
        }

        $dump = MysqldumpBinary::resolve();
        if ($dump === 'mysqldump') {
            return 'mysql';
        }

        $mysql = preg_replace('/mysqldump(\.exe)?$/i', 'mysql$1', $dump);
        if (is_string($mysql) && is_file($mysql)) {
            return $mysql;
        }

        throw new RuntimeException(
            'Утилита mysql не найдена. Укажите MYSQL_PATH в .env рядом с mysqldump.'
        );
    }
}
