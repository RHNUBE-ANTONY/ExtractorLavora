<?php

namespace App\Jobs;

use App\Http\Controllers\BD\ConfiguracionDatabase;
use App\Http\Controllers\DB\ConfiguracionDatabase as DBConfiguracionDatabase;
use App\Models\jobs_logs;
use App\Models\marcaciones_biometrico;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLavoraJobsV2 implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    protected $organi_id;
    protected $organi_almacFoto;
    public $tries = 0;
    public $timeout = 600;
    public function __construct($organi_id, $organi_almacFoto)
    {
        $this->organi_id = $organi_id;
        $this->organi_almacFoto = $organi_almacFoto;
        $this->onQueue('lavora');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $organi_id = $this->organi_id;
        $organi_almacFoto = $this->organi_almacFoto;
        $name_bd = null;
        //eliminamos registros mayores a 24 horas
        jobs_logs::where('fecha_inicio', '<', Carbon::now('America/Lima')->subHours(24))->delete();
        Log::info("Iniciando ProcessLavoraJobsV2 para organi_id: {$organi_id}");
        //registramos jobs_logs
        $logs = [
            'organi_id' => $organi_id,
            'fecha_inicio' => Carbon::now('America/Lima'),
        ];
        $logs_inset = jobs_logs::create($logs);
        // Crear conexión temporal con la base de datos específica
        try {
            $array_api = array(
                'organi_id' => $organi_id,
                'guy_id' => 0
            );
            $dispositivos = [];
            $data_result =  valid_process_organization($array_api);
            if ($data_result['status']) {
                $response = $data_result['data'];
                if ($response['result']) {
                    $dispositivos = $response['dispositivos'];
                }
            } else {
                Log::error("Error al validar organización para organi_id: {$organi_id} - Respuesta: ", $data_result);
            }
            if (empty($dispositivos)) return;
            // * ----------------------------------------------------------- Conexion de base datos -----------------------------------------------------------
            $environment =  (new DBConfiguracionDatabase($organi_id))->setBDD_Ex();
            $connection = $environment->conexion;
            $name_bd = $environment->name;
            // * ----------------------------------------------------------------------------------------------------------------------------------------------
            /*      $series = $connection->table('biometrico_estado')
                ->where('organi_id', '=', $organi_id)
                ->where('tipoProceso',  0)
                ->whereNotNull('serie')
                ->where('serie', '!=', '')
                ->pluck('serie')
                ->toArray(); */

            $connection->table('marcaciones_biometrico')
                ->select(
                    'organi_id',
                    'idDispositivos as dispositivo_id',
                    'serie',
                    'dni',
                    'marcacion',
                    'idmarcaciones_biometrico as id_extractor',
                    'temperatura',
                    'id_supervisor as supervisor',
                    'latitud',
                    'longitud',
                    'id_puntoControl as punto_control',
                    'comentario',
                    'imagenes',
                    'foto'
                )
                ->where('organi_id', '=', $organi_id)
                ->where('estado', '=', 0)
                ->where('modo', 'A')
                ->whereIn('idDispositivos',  $dispositivos)
                ->whereNotNull('dni')
                ->orderBy('dni', 'asc')
                ->chunk(200, function ($data_marcaciones) use ($organi_id, $organi_almacFoto, $name_bd) {
                    $array_api = array(
                        'organi_id' => $organi_id,
                        'guy_id' => 0
                    );
                    $data_result = valid_process_organization($array_api);
                    if ($data_result['status']) {
                        $response = $data_result['data'];
                        if ($response['result'] && $response['status']) {
                            $total_marcaciones = $data_marcaciones->count();
                            //agregando datos al poll de procesos
                            $procesar_marcaciones['fecha_inico'] = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                            $procesar_marcaciones['tipo'] = "Proceso automático | Enviar marcaciones de extractor service a  modo lavora";
                            $procesar_marcaciones['organi_id'] = $organi_id;
                            $procesar_marcaciones['n_empleados'] = $total_marcaciones;
                            $procesar_marcaciones['tipo_proceso'] = 2;
                            $procesar_marcaciones['estado'] = 0;
                            $procesar_marcaciones['id'] = null;
                            $response_marking = create_process_markings($procesar_marcaciones);
                            if ($response_marking['status']) {
                                $update_procesar_marcaciones = ['id' => $response_marking['data']['id']];
                                $update_procesar_marcaciones['organi_id'] = $organi_id;
                            }
                            // Obtener marcaciones y agrupar por identificador biométrico
                            $marcaciones_agrupadas = $data_marcaciones->groupBy('dni');
                            $array_data = [];

                            foreach ($marcaciones_agrupadas as $identificador => $marcaciones_filtradas) {
                                $marcaciones = [];

                                foreach ($marcaciones_filtradas->sortBy('marcacion') as $marcacion) {
                                    $marcacion = (array)$marcacion;
                                    unset($marcacion['dni'], $marcacion['organi_id']);

                                    // Procesar foto con manejo de errores
                                    $marcacion['foto'] = processPhoto(
                                        $marcacion['foto'],
                                        $organi_almacFoto
                                    );
                                    $marcaciones[] = $marcacion;
                                }

                                $array_data[] = [
                                    'identificador_biometrico' => $identificador,
                                    'marcaciones' => $marcaciones
                                ];
                            }
                            $array_api = array(
                                'organi_id' => $organi_id,
                                'data' => $array_data
                            );
                            $content = send_markings_lavora($array_api);
                            if ($content['status']) {
                                //*RECORREMOS RESPUESTAS
                                if (isset($content['data']) && is_array($content['data'])) {
                                    foreach ($content['data'] as $resp) {
                                        // Validar que la respuesta tenga el ID
                                        if (isset($resp['id_extractor'])) {
                                            $marcaciones_biometrico = marcaciones_biometrico::on($name_bd)->find($resp['id_extractor']);
                                            if ($marcaciones_biometrico) {
                                                $marcaciones_biometrico->estado = $resp['estado'] ? 1 : 2;
                                                $marcaciones_biometrico->respuesta = $resp['msg'] ?? null;
                                                $marcaciones_biometrico->save();
                                            }
                                        }
                                    }
                                    $update_procesar_marcaciones['n_procesados'] = $total_marcaciones;
                                    $update_procesar_marcaciones['estado'] = 1;
                                } else {
                                    $update_procesar_marcaciones['estado'] = 2;
                                }
                            } else {
                                $update_procesar_marcaciones['estado'] = 2;
                                Log::error("Error al enviar marcaciones a Lavora para organi_id: {$organi_id} - Respuesta: " . json_encode($content));
                            }

                            if ($response_marking['status']) {
                                //registrando proceso de marcaciones
                                $update_procesar_marcaciones['fecha_fin'] = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                                create_process_markings($update_procesar_marcaciones);
                            }
                        } else {
                            Log::error("Error al validar organización para organi_id: {$organi_id} - Respuesta: ", $data_result);
                        }
                    } else {
                        Log::error("Error al validar organización para organi_id: {$organi_id} - Respuesta: ", $data_result);
                    }
                });
        } catch (\Exception $e) {
            Log::error("Error en ProcessLavoraJobsV2 para organi_id: {$organi_id} - " . $e->getMessage());
            throw $e;
        } finally {
            // Actualizar logs de jobs_logs con fecha_fin
            jobs_logs::where('id', $logs_inset->id)->update(['fecha_fin' => Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss')]);

            // Cerrar conexión temporal solo si se llegó a crear
            if (!empty($name_bd)) {
                DB::disconnect($name_bd);
                DB::purge($name_bd);
            }

            // Liberar handlers de logs para evitar "too many open files"
            $logger = Log::getFacadeRoot();
            if (method_exists($logger, 'getHandlers')) {
                foreach ($logger->getHandlers() as $handler) {
                    if (method_exists($handler, 'close')) {
                        $handler->close();
                    }
                }
            }
            // Forzar limpieza de recursos y ciclos circulares
            gc_collect_cycles();
        }
    }
}
