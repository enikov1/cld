<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\BackupService;
use App\Services\CronRunLogger;
use Illuminate\Console\Command;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {name? : Имя архива backup_YYYY-MM-DD_HH-mm-ss.zip}
        {--source=local : local|remote}
        {--file= : Полный путь к ZIP (скопируется в storage/app/backups)}
        {--database : Восстановить только БД}
        {--files : Восстановить только файлы}
        {--trigger= : schedule|admin|cli}
    ';

    protected $description = 'Restore site from a local or remote backup archive';

    public function handle(BackupService $backup): int
    {
        $file = $this->option('file');
        $name = $this->argument('name');
        $source = (string)$this->option('source');

        if (is_string($file) && $file !== '') {
            $name = $backup->importArchiveFile($file);
            $source = 'local';
            $this->line('Архив подготовлен: ' . $name);
        }

        if (!is_string($name) || $name === '') {
            $latest = $backup->listLocalBackups()[0] ?? null;
            if ($latest === null) {
                $this->error('Архив не указан и локальных бэкапов нет.');

                return self::FAILURE;
            }
            $name = $latest['name'];
            $source = 'local';
            $this->line('Используется последний локальный архив: ' . $name);
        }

        $onlyDb = (bool)$this->option('database');
        $onlyFiles = (bool)$this->option('files');

        if ($onlyDb && !$onlyFiles) {
            $restoreDatabase = true;
            $restoreFiles = false;
        } elseif ($onlyFiles && !$onlyDb) {
            $restoreDatabase = false;
            $restoreFiles = true;
        } else {
            $restoreDatabase = true;
            $restoreFiles = true;
        }

        $trigger = CronRunLogger::detectTrigger($this->option('trigger'));

        $run = CronRunLogger::run(
            CronRunLogger::JOB_BACKUP_RESTORE,
            'backup:restore',
            $trigger,
            static fn ($run) => $backup->restore($name, $source, $restoreDatabase, $restoreFiles, $run),
            [
                'name' => $name,
                'source' => $source,
                'restore_database' => $restoreDatabase,
                'restore_files' => $restoreFiles,
            ],
            'Восстановление из бэкапа',
        );

        if ($run->status === CronRun::STATUS_FAILED) {
            $this->error((string)($run->error ?: $run->message));
            if ($run->log) {
                $this->line($run->log);
            }

            return self::FAILURE;
        }

        $this->info((string)$run->message);

        return self::SUCCESS;
    }
}
