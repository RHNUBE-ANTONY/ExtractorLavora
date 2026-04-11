<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessAccessJobs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-access')]
#[Description('Command description')]
class ProcessAccess extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessAccessJobs::dispatch();
        return 0;
    }
}
