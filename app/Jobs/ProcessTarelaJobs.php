<?php

namespace App\Jobs;

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\marcaciones_biometrico;
use App\Models\organizacion_puerto;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTarelaJobs implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $registrar_tarea['fecha_inicio'] = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
        $registrar_tarea['descripcion'] = "Proceso automático | Enviar marcaciones de extractor service a  modo tarela";
        $registrar_tarea['estado'] = 0;
        $registrar_tarea['type_process'] = 1;
        Log::info("INICIO JOB TARELA");
        $id =  create_tareas_programadas($registrar_tarea);
        $update_tarea = ['id' => $id];

        // Usar chunk() en lugar de cursor() para liberar conexiones entre iteraciones
        organizacion_puerto::join('organizacion as o', 'organizacion_puerto.organi_id', '=', 'o.organi_id')
            ->select('o.organi_id')
            ->where('o.organi_estado', 1)
            ->chunk(10, function ($organizaciones) use (&$update_tarea) {
                foreach ($organizaciones as $organizacion) {
                    $organi_id = $organizacion->organi_id;
                    $name_bd = null;

                    try {
                        // * ----------------------------------------------------------- Conexion de base datos -----------------------------------------------------------
                        $environment =  new ConfiguracionDatabase($organi_id);
                        //$connection = $environment->setBDD_Ex()->conexion;
                        $name_bd = $environment->setBDD_Ex()->name;
                        // * ----------------------------------------------------------------------------------------------------------------------------------------------
                        // * PARA API DE TAREO
                        $marcaciones_tareo = marcaciones_biometrico::on($name_bd)
                            ->select(
                                'idmarcaciones_biometrico',
                                'tipoMarcacion',
                                'marcacion as fechaMarcacion',
                                'dni as codigo_biometrico',
                                'id_supervisor as idControlador',
                                'idDispositivos as idDisposi',
                                'organi_id',
                                'id_actividad as activ_id',
                                'idsubActividad',
                                'idHoraEmp',
                                'latitud',
                                'longitud',
                                'id_puntoControl as puntoC_id',
                                'centC_id',
                                'avance',
                                'idunidad_medida',
                                'tiempo_trabajado'
                            )
                            ->where('organi_id', '=', $organi_id)
                            ->where('estado', '=', 0)
                            //->where('estado_procesado', '=', 0)
                            ->whereNotNull('dni')
                            ->where('modo', 'T')->get();
                        $update_tarea['estado'] = 1;
                        if ($marcaciones_tareo->isNotEmpty()) {
                            //*ENVIANDO ARCHIVO A API
                            $content = enviar_marcaciones_tarela($marcaciones_tareo);
                            if (!isset($content->original)) {
                                // * Si respuesta es tipo array
                                if (isset($content["status"])) {
                                    if ($content["status"] == 200) {
                                        foreach ($content["detail"] as  $value) {
                                            marcaciones_biometrico::on($name_bd)
                                                ->where('idmarcaciones_biometrico',  $value["id"])
                                                ->update([
                                                    "estado" => 2,
                                                    //"estado_procesado" => 1,
                                                    "respuesta" => $value["error"] ?? null
                                                ]);
                                        }
                                    }
                                }
                            } else {
                                Log::info("ERROR API TAREO");
                                Log::info($organi_id);
                                Log::info($content->original);
                            }
                        }
                    } catch (\Exception $e) {
                        $update_tarea['estado'] = 2;
                        Log::error("Error dispatching ProcessTarela job for organi_id {$organi_id}: " . $e->getMessage());
                    } finally {
                        // Asegurar que la conexión se cierre siempre
                        if ($name_bd) {
                            DB::disconnect($name_bd);
                            DB::purge($name_bd);
                        }
                    }
                }

                // Liberar recursos después de cada chunk (solo conexiones dinámicas, no la predeterminada)
                gc_collect_cycles();
            });

        $update_tarea['fecha_fin'] = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
        update_tareas_programadas($update_tarea);

        // Liberar handlers de logs
        $logger = Log::getFacadeRoot();
        if (method_exists($logger, 'getHandlers')) {
            foreach ($logger->getHandlers() as $handler) {
                if (method_exists($handler, 'close')) {
                    $handler->close();
                }
            }
        }

        gc_collect_cycles();
        Log::info("FIN JOB TARELA");
    }
}
