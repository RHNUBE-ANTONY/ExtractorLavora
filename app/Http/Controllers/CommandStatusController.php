<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandStatusController extends Controller
{
    public function command_status(Request $request)
    {
        try {
            $response = '';
            $puerto = $_SERVER['SERVER_PORT'];

            // Lectura de parametros de entrada
            $SN = $request->SN;
            $INFO = $request->INFO;
            // Filtramos el puerto en la tabla 'organizacion_puerto' para obtener las organizaciones con las cuales trabaja el dispositivo biometrico
            $organizaciones_id = ObtenerOrganiIdXPuerto($puerto);

            if (!empty($organizaciones_id)) {
                $response = '';
                foreach ($organizaciones_id as $organi_id) {
                    //obtengo de la bd_rh_extractor la conexion dinamica a la bd de rh nube
                    $enviromentEX = ObtenerConexionBDExtractor($organi_id);
                    ActualizarUltimaConexionExtractor($enviromentEX, $organi_id, $SN);
                    if (isset($INFO)) {
                        MantenedorInformacionBiometrico($enviromentEX, $organi_id, $SN, $INFO);
                    }
                    $response = $response . ObtenerComandosPendientesBiometrico($enviromentEX, $organi_id, $SN);
                    DB::disconnect($enviromentEX->setBDD_Ex()->name);
                    $enviromentEX = null;
                    $organi_id = null;
                }
            } else {
                create_list_organizacion($puerto, $SN);
            }

            if ($response == '') {
                return 'OK';
            }
            return $response;
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            Log::info(Carbon::now('America/Lima'));
            return 'error';
        }
    }
    public function update_command_status(Request $request)
    {
        $response = 'error';
        // Lectura de parametros de entrada
        $SN = $request->SN;
        $contenido = $request->getContent();
        $puerto = $_SERVER['SERVER_PORT'];
        $array_api = [];
        try {
            // Filtramos el puerto en la tabla 'organizacion_puerto' para obtener las organizaciones con las cuales trabaja el dispositivo biometrico
            $organizaciones_id = ObtenerOrganiIdXPuerto($puerto);
            if (!empty($organizaciones_id)) {
                foreach ($organizaciones_id as $organi_id) {
                    $enviromentEX = ObtenerConexionBDExtractor($organi_id);
                    ActualizarUltimaConexionExtractor($enviromentEX, $organi_id, $SN);
                    $connection = $enviromentEX->setBDD_Ex()->conexion;
                    $content = explode("\n", $contenido);
                    foreach ($content as $linea_comando) {
                        $contenido_comando = explode("&", $linea_comando);
                        $comando_id = "0";
                        $respuesta = "";
                        $cmd_data = "";

                        foreach ($contenido_comando as $item) {
                            if (substr($item, 0, strlen("ID=")) == "ID=") {
                                $comando_id = substr($item, strpos($item, 'ID=') + strlen("ID="));
                                continue;
                            }
                            if (substr($item, 0, strlen("Return=")) == "Return=") {
                                $respuesta = substr($item, strpos($item, 'Return=') + strlen("Return="));
                                continue;
                            }
                            if (substr($item, 0, strlen("CMD=")) == "CMD=") {
                                $cmd_data = substr($item, strpos($item, 'CMD=') + strlen("CMD="));
                                if ($respuesta == 0) $resultado = 2;
                                else $resultado = 3;
                                $connection->table('comandos_biometrico')
                                    ->where('idcomandos_biometrico', '=', $comando_id)
                                    ->update([
                                        'respuesta'  => $respuesta . "  " . $cmd_data,
                                        'estado'  => $resultado
                                    ]);
                            }
                        }

                        $resultados = BuscarBiometrias($enviromentEX, $comando_id);
                        if (!empty($resultados)) {
                            array_push($array_api,   $resultados);
                        }
                    }
                    //actualizar nueva recoge
                    $ids = $connection->table('comandos_biometrico')
                        ->where('organi_id', '=', $organi_id)
                        ->where('serie_dispositivo', '=', $SN)
                        ->where('estado', '=', 0)
                        ->where('tipo_dispo', 1)
                        ->take(3)
                        ->pluck('idcomandos_biometrico')->toArray();

                    $connection->table('comandos_biometrico')
                        ->whereIn('idcomandos_biometrico', $ids)
                        ->update([
                            'estado'  => 1
                        ]);

                    DB::disconnect($enviromentEX->setBDD_Ex()->name);
                }
                if (!empty($array_api)) {
                    $content = GuardarBiometrias($array_api);
                }
                return 'OK';
            } else {
                Log::info("Biometrico con serie= $SN conectandose mediante el puerto $puerto , no se encontro organización asignada - cdataPostStamp");
            }
        } catch (\Throwable $th) {
            Log::info($SN);
            Log::info($contenido);
            Log::info($th->getMessage());
            Log::info('API deviceCmdGet : ERROR');
            return 'error';
        }
    }
}
