<?php

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\marcaciones_biometrico;
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
