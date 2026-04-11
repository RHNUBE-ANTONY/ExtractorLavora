<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessComendiaJobs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-comendia')]
#[Description('Command description')]
class ProcessComendia extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessComendiaJobs::dispatch();
        return 0;
    }
}
