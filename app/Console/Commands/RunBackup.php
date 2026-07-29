<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\BackupService;
use App\Services\CronRunLogger;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run
        {--trigger= : schedule|admin|cli}
        {--force : Run even if auto-backup is disabled}
    ';

    protected $description = 'Create database and site backup, optionally upload to remote server';

    public function handle(BackupService $backup): int
    {
        $trigger = CronRunLogger::detectTrigger($this->option('trigger'));

        if (!$this->option('force') && $trigger === CronRun::TRIGGER_SCHEDULE) {
            if (!\App\Services\BackupSettings::isEnabled()) {
                $this->line('Auto-backup is disabled.');

                return self::SUCCESS;
            }
        }

        $run = CronRunLogger::run(
            CronRunLogger::JOB_BACKUP,
            'backup:run',
            $trigger,
            static fn () => $backup->run(),
            ['force' => (bool)$this->option('force')],
            'Резервное копирование',
        );

        if ($run->status === CronRun::STATUS_FAILED) {
            $this->error((string)($run->error ?: $run->message));

            return self::FAILURE;
        }

        $this->info((string)$run->message);

        return self::SUCCESS;
    }
}
