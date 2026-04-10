<?php

namespace App\Jobs;

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\marcaciones_biometrico;
use App\Models\organizacion;
use App\Models\organizacion_puerto;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessComendiaJobs implements ShouldQueue
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
        $registrar_tarea['descripcion'] = "Proceso automático | Enviar marcaciones de extractor service a  modo comendia";
        $registrar_tarea['estado'] = 0;
        $registrar_tarea['type_process'] = 3;
        Log::info("INICIO JOB COMENDIA");
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
                        $organi_almacFoto =  organizacion::where('organi_id', $organi_id)->value('organi_almacFoto');
                        // INICIO: modo comendia
                        $marcaciones_comendia = marcaciones_biometrico::on($name_bd)
                            ->select(
                                'organi_id',
                                'idDispositivos as idDisposi',
                                'dni as identificador_biometrico',
                                'marcacion as fechaMarcacion',
                                'idmarcaciones_biometrico as id',
                                'temperatura as temperatura2',
                                'id_supervisor as controlador',
                                'latitud',
                                'longitud',
                                'id_puntoControl as punto',
                                'comentario as apcomentario',
                                'imagenes'
                            )
                            ->addSelect(DB::raw("null as foto"))
                            ->where('organi_id', '=', $organi_id)
                            ->where('estado', '=', 0)
                            //->where('estado_procesado', '=', 0)
                            ->where('modo', 'C')
                            ->whereNotNull('dni')
                            ->orderBy('marcacion')
                            ->get();
                        $update_tarea['estado'] = 1;
                        if ($marcaciones_comendia->isNotEmpty()) {
                            $marcaciones_comendia = $marcaciones_comendia->toArray();
                            foreach (array_chunk($marcaciones_comendia, 300) as $grupoMarcaciones) {

                                if ($organi_almacFoto == 1) {
                                    foreach ($grupoMarcaciones as $marcacion_com) {
                                        if (!empty($marcacion_com->foto)) {
                                            $marcacion_com->foto =  foto_base64($marcacion_com->foto);
                                        }
                                    }
                                }
                                $array_api = [
                                    'file' => $grupoMarcaciones,
                                ];
                                $content = enviar_marcaciones_comendia($array_api);

                                if (!isset($content->original)) {
                                    if (is_array($content)) {
                                        foreach ($content as $resp) {
                                            if (marcaciones_biometrico::on($name_bd)->where("idmarcaciones_biometrico", $resp['id'])->exists()) {
                                                marcaciones_biometrico::on($name_bd)->find($resp['id'])
                                                    ->update(
                                                        [
                                                            "estado" => isset($resp['error']) ? 2 : 1,
                                                            "respuesta" => isset($resp['error']) ? $resp['error'] : null,
                                                            //"estado_procesado" => 1
                                                        ]
                                                    );
                                            }
                                        }
                                    }
                                } else {
                                    Log::info("ERROR API COMENDIA ORIGINAL");
                                    Log::info($organi_id);
                                    Log::info($content->original);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $update_tarea['estado'] = 2;
                        Log::error("Error dispatching ProcessComendia job for organi_id {$organi_id}: " . $e->getMessage());
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
        Log::info("FIN JOB COMENDIA");
    }
}
