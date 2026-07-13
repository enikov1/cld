<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BuildThemeAssets extends Command
{
    protected $signature = 'theme:build-assets';

    protected $description = 'Minify theme CSS/JS assets (requires npm)';

    public function handle(): int
    {
        $process = new Process(['npm', 'run', 'build:theme'], base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('Theme asset build failed.');

            return self::FAILURE;
        }

        $this->info('Theme assets minified.');

        return self::SUCCESS;
    }
}
