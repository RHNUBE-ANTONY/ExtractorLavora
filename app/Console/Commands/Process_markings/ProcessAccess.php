<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessAccessJobs;
use App\Models\tareas_programadas;
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
        $tareas_programadas = tareas_programadas::where('type_process', 2)->get()->last();
        if (!empty($tareas_programadas) && $tareas_programadas->estado == 0)  return 0;
        ProcessAccessJobs::dispatch();
        return 0;
    }
}
