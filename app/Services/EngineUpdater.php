<?php

namespace App\Services;

use App\Support\AdminAudit;
use App\Support\TplCache;
use Illuminate\Support\Facades\Process;

/**
 * Обновление движка Omro из админки.
 *
 * Движок обновляется через git: проверка «отстаёт ли рабочая копия от origin»,
 * затем pull + composer install + миграции + сборка минифицированных ассетов.
 * Работает с любым git-хостингом (GitHub, GitLab, свой сервер) — источник
 * берётся из `git remote get-url origin`.
 */
class EngineUpdater
{
    private const FETCH_TIMEOUT = 60;

    private const STEP_TIMEOUT = 600;

    public static function currentVersion(): string
    {
        $version = trim((string)@file_get_contents(self::baseDir() . DIRECTORY_SEPARATOR . 'VERSION'));

        return $version !== '' ? $version : 'dev';
    }

    /**
     * Проверка наличия обновлений.
     *
     * @return array<string, mixed>
     */
    public static function check(): array
    {
        $result = [
            'installed' => false,
            'current' => self::currentVersion(),
            'branch' => null,
            'remote' => null,
            'behind' => 0,
            'ahead' => 0,
            'local_changes' => false,
            'remote_head' => null,
            'available' => false,
            'error' => null,
        ];

        if (!is_dir(self::baseDir() . DIRECTORY_SEPARATOR . '.git')) {
            $result['error'] = 'Движок установлен без git — автообновление недоступно. Установите через git clone.';

            return $result;
        }

        try {
            $git = self::gitBin();
            if ($git === null) {
                $result['error'] = 'git недоступен в окружении веб-сервера (нет в PATH). Обновление через админку не работает — обновляйте через SSH (update.sh).';

                return $result;
            }

            $remoteUrl = trim(self::runCommand($git . ' remote get-url origin'));
            $result['remote'] = $remoteUrl !== '' ? $remoteUrl : null;
            $result['installed'] = $remoteUrl !== '';

            $branch = trim(self::runCommand($git . ' rev-parse --abbrev-ref HEAD'));
            $result['branch'] = $branch !== '' ? $branch : 'main';

            $fetch = Process::timeout(self::FETCH_TIMEOUT)
                ->path(self::baseDir())
                ->run($git . ' fetch --quiet origin');

            if (!$fetch->successful()) {
                $result['error'] = 'Не удалось связаться с git-сервером: ' . self::sanitize($fetch->errorOutput() ?: $fetch->output());

                return $result;
            }

            $remoteRef = 'origin/' . $result['branch'];
            $result['behind'] = max(0, (int)trim(self::runCommand($git . ' rev-list --count HEAD..' . $remoteRef)));
            $result['ahead'] = max(0, (int)trim(self::runCommand($git . ' rev-list --count ' . $remoteRef . '..HEAD')));
            $result['remote_head'] = trim(self::runCommand($git . ' rev-parse --short ' . $remoteRef)) ?: null;
            $result['local_changes'] = trim(self::runCommand($git . ' status --porcelain')) !== '';
            $result['available'] = $result['behind'] > 0;
        } catch (\Throwable $e) {
            // Не найден git, нет прав на запуск процессов и т.п. — отдаём ошибку в UI, а не 500.
            $result['error'] = 'Проверка обновлений недоступна: ' . self::sanitize($e->getMessage());
        }

        return $result;
    }

    /**
     * Запуск обновления: pull → composer → migrate → build:theme → сброс кэша шаблонов.
     *
     * @return array<string, mixed>
     */
    public static function run(): array
    {
        $previous = self::currentVersion();
        $log = [];

        $check = self::check();
        if ($check['error'] !== null) {
            return ['ok' => false, 'log' => ['Проверка не пройдена: ' . $check['error']], 'current' => $previous];
        }

        if (!$check['available']) {
            return ['ok' => false, 'log' => ['Обновлений нет — установлена последняя версия.'], 'current' => $previous];
        }

        if ($check['local_changes']) {
            $log[] = 'Внимание: в рабочей копии есть локальные изменения — git pull мог отклонить обновление.';
        }

        // 1. git pull
        $pull = self::step($log, 'git pull --ff-only origin ' . $check['branch']);
        if (!$pull) {
            return ['ok' => false, 'log' => $log, 'current' => $previous];
        }

        // 2. composer install --no-dev (если composer доступен)
        if (self::binaryExists('composer')) {
            self::step($log, 'composer install --no-dev --no-interaction --prefer-dist --no-scripts');
        } else {
            $log[] = 'composer не найден в PATH — шаг пропущен.';
        }

        // 3. миграции
        self::step($log, escapeshellarg(PHP_BINARY) . ' artisan migrate --force');

        // 4. сборка минифицированных ассетов темы (если есть node_modules)
        if (is_dir(self::baseDir() . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'esbuild') && self::binaryExists('node')) {
            self::step($log, 'npm run build:theme');
        } else {
            $log[] = 'node_modules/esbuild не найден — сборка ассетов пропущена (min-файлы поставляются в git).';
        }

        // 5. сброс кэша отрендеренных страниц
        TplCache::bumpGlobalVersion();
        $log[] = 'Кэш шаблонов сброшен.';

        $current = self::currentVersion();

        AdminAudit::log('system.update', summary: 'Обновление движка: ' . $previous . ' → ' . $current);

        return ['ok' => true, 'log' => $log, 'current' => $current, 'previous' => $previous];
    }

    /**
     * Проверка + запуск обновления целиком под защитой: любые исключения
     * (нет git/composer, отказ в запуске процессов) возвращаются как текст ошибки.
     *
     * @return array<string, mixed>
     */
    public static function runSafely(): array
    {
        try {
            return self::run();
        } catch (\Throwable $e) {
            return ['ok' => false, 'log' => ['Ошибка обновления: ' . self::sanitize($e->getMessage())], 'current' => self::currentVersion()];
        }
    }

    private static function step(array &$log, string $command): bool
    {
        $log[] = '$ ' . $command;

        $process = Process::timeout(self::STEP_TIMEOUT)
            ->path(self::baseDir())
            ->run($command);

        $output = trim($process->output() . ' ' . $process->errorOutput());
        if ($output !== '') {
            $log[] = self::sanitize($output);
        }

        if (!$process->successful()) {
            $log[] = 'Шаг завершился с ошибкой.';
            return false;
        }

        return true;
    }

    private static function runCommand(string $command): string
    {
        $process = Process::timeout(30)->path(self::baseDir())->run($command);

        return self::sanitize($process->successful() ? $process->output() : '');
    }

    /**
     * Путь к git с учётом Windows: веб-процесс может не видеть git в PATH —
     * проверяем стандартные пути установки Git for Windows.
     */
    private static function gitBin(): ?string
    {
        if (Process::timeout(15)->run('git --version')->successful()) {
            return 'git';
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\\Program Files\\Git\\cmd\\git.exe',
                'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
                'C:\\Program Files\\Git\\bin\\git.exe',
            ];
            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    return '"' . $candidate . '"';
                }
            }
        }

        return null;
    }

    /**
     * Приводит вывод внешних процессов к валидному UTF-8: на Windows вывод
     * консольных команд приходит в cp866/cp1251 и уронит JsonResponse.
     */
    private static function sanitize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Уже валидный UTF-8 — как есть.
        if (preg_match('//u', $value)) {
            return $value;
        }

        // Консоль Windows: сначала cp866, затем cp1251.
        foreach (['CP866', 'Windows-1251'] as $enc) {
            $converted = @iconv($enc, 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted)) {
                return $converted;
            }
        }

        $fallback = function_exists('mb_scrub') ? mb_scrub($value, 'UTF-8') : $value;
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $fallback);

        return is_string($clean) ? $clean : '';
    }

    private static function binaryExists(string $binary): bool
    {
        $process = Process::timeout(15)->run($binary . ' --version');

        return $process->successful();
    }

    private static function baseDir(): string
    {
        return (string)base_path();
    }
}
