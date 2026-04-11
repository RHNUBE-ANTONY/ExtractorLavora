<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessTarelaJobs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-tarela')]
#[Description('Command description')]
class ProcessTarela extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessTarelaJobs::dispatch();
        return 0;
    }
}
