<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessComendiaJobs;
use App\Models\tareas_programadas;
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

        $tareas_programadas = tareas_programadas::where('type_process', 3)->get()->last();
        if (!empty($tareas_programadas) && $tareas_programadas->estado == 0)  return 0;
        ProcessComendiaJobs::dispatch();
        return 0;
    }
}
