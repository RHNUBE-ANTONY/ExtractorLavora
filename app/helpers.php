<?php

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\marcaciones_biometrico;
use App\Models\tareas_programadas;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

function getClientRHNUBE()
{
    static $client = null;
    if ($client === null) {
        $client = new Client(['base_uri' => config("rhnube.api_rhnube")]);
    }
    return $client;
}
function valid_process_organization($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/web_services/valid-process-organization',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return array(
            "data" =>  $body,
            "status" => true,
        );
    } catch (RequestException $e) {
        return array(
            "mensaje" =>  $e->getMessage(),
            "status" => false,
        );
    }
}
function create_process_markings($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/web_services/create-process-markings',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return array(
            "data" =>  $body,
            "status" => true,
        );
    } catch (RequestException $e) {
        return array(
            "mensaje" =>  $e->getMessage(),
            "status" => false,
        );
    }
}
function processPhoto($foto, $almacFoto)
{
    if ($almacFoto != 1 || empty($foto)) {
        return null;
    }
    try {
        return foto_base64($foto);
    } catch (\Exception $e) {
        return null;
    }
}
function foto_base64($foto)
{
    $imagenComoBase64 = null;
    if (!empty($foto)) {
        $rutaImagen = app_path() . $foto;
        if (file_exists($rutaImagen)) {
            $contenidoBinario = file_get_contents($rutaImagen);
            if ($contenidoBinario !== false) {
                $imagenComoBase64 = base64_encode($contenidoBinario);
                //fclose($contenidoBinario); // Cerrar el archivo
            } else {
                // Manejar el caso en que no se pudo leer el archivo
            }
        }
    }
    return $imagenComoBase64;
}
function send_markings_lavora($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/internal/marcacionesV6',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return array(
            "data" =>  $body,
            "status" => true,
        );
    } catch (RequestException $e) {
        return array(
            "mensaje" =>  $e->getMessage(),
            "status" => false,
        );
    }
}

function valid_marking_pending($organi_id)
{
    // * ----------------------------------------------------------- Conexion de base datos -----------------------------------------------------------
    $environment =  new ConfiguracionDatabase($organi_id);
    //$connection = $environment->setBDD_Ex()->conexion;
    $name_bd = $environment->setBDD_Ex()->name;
    // * ----------------------------------------------------------------------------------------------------------------------------------------------
    $result = marcaciones_biometrico::on($name_bd)
        ->where('organi_id', '=', $organi_id)
        ->where('estado', '=', 0)
        ->doesntExist();
    DB::disconnect($name_bd);
    return $result;
}
function create_tareas_programadas($registrar_tarea)
{
    $create_tarea = new tareas_programadas();
    $create_tarea->fecha_inicio = $registrar_tarea['fecha_inicio'];
    $create_tarea->descripcion = $registrar_tarea['descripcion'];
    $create_tarea->estado = $registrar_tarea['estado'];
    $create_tarea->type_process = $registrar_tarea['type_process'];
    $create_tarea->save();
    return $create_tarea->id;
}
function enviar_marcaciones_acceso($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/MarcacionAccesoV1',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return $body;
    } catch (RequestException $e) {
        $decode = json_decode($e->getResponse()->getBody()->getContents(), true);
        return response()->json(array(
            "mensaje" =>  $decode,
            "mensaje_servicio" => $e->getResponse()->getBody()->getContents(),
            "status_servicio" => $e->getResponse()->getStatusCode()
        ), 404);
    }
}
function update_tareas_programadas($update_tarea)
{
    $tarea = tareas_programadas::find($update_tarea['id']);
    if ($tarea) {
        $tarea->fecha_fin = $update_tarea['fecha_fin'];
        $tarea->estado = $update_tarea['estado'] ?? 2;
        $tarea->save();
    }
    return 'OK';
}
function enviar_marcaciones_comendia($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/MarcacionComendiaV1',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return $body;
    } catch (RequestException $e) {
        $decode = json_decode($e->getResponse()->getBody()->getContents(), true);
        return response()->json(array(
            "mensaje" =>  $decode,
            "mensaje_servicio" => $e->getResponse()->getBody()->getContents(),
            "status_servicio" => $e->getResponse()->getStatusCode()
        ), 404);
    }
}
function enviar_marcaciones_tarela($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/v2/marcacionTareo',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return $body;
    } catch (RequestException $e) {
        $decode = json_decode($e->getResponse()->getBody()->getContents(), true);
        return response()->json(array(
            "mensaje" =>  $decode,
            "status" => 404,
            "mensaje_servicio" => $e->getResponse()->getBody()->getContents(),
            "status_servicio" => $e->getResponse()->getStatusCode()
        ), 404);
    }
}