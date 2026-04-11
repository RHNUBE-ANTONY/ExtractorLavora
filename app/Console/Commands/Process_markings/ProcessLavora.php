<?php

namespace App\Console\Commands\Process_markings;

use App\Jobs\ProcessLavoraJobsV2;
use App\Models\organizacion_puerto as ModelsOrganizacion_puerto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('app:process-lavora {organi_id?}')]
#[Description('Command description')]
class ProcessLavora extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (DB::table('jobs')->where('queue', 'lavora')->count() > 0) {
            Log::warning('Too many pending jobs in the queue. Skipping this run.');
            return 0;
        }
        $organi_id = $this->argument('organi_id') ?? null;
        $organizaciones = ModelsOrganizacion_puerto::join('organizacion as o', 'organizacion_puerto.organi_id', '=', 'o.organi_id')
            ->when(!empty($organi_id), function ($query) use ($organi_id) {
                $query->where('o.organi_id', $organi_id);
            })
            ->where('o.organi_estado', 1)
            ->pluck('o.organi_almacFoto', 'o.organi_id')
            ->toArray();
        foreach ($organizaciones as $organi_id => $organi_almacFoto) {
            $result = valid_marking_pending($organi_id);
            if ($result) continue;
            Log::info("Dispatching ProcessLavoraJobsV2 for organi_id: $organi_id");
            ProcessLavoraJobsV2::dispatch($organi_id, $organi_almacFoto);
        }
        return 0;
    }
}
