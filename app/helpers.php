<?php

use App\Http\Controllers\ConfiguracionBDController;
use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\biometrico_estado;
use App\Models\lista_organizaciones;
use App\Models\marcaciones_biometrico;
use App\Models\organizacion;
use App\Models\plantilla_empleadobio;
use App\Models\tareas_programadas;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

function getClientRHNUBE()
{
    static $client = null;
    if ($client === null) {
        $client = new Client(['base_uri' => env('RHNUBE_URL')]);
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
function is_negative_number($number = 0)
{
    if (is_numeric($number) && ($number < 0))
        return true;
    else
        return false;
}

function ObtenerOrganiIdXPuerto($puerto)
{

    $organizaciones_id = DB::table('organizacion_puerto as op')
        ->leftJoin('organizacion as o', 'o.organi_id', '=', 'op.organi_id')
        ->where('o.organi_estado', 1)
        ->where('puerto', $puerto)
        ->pluck('op.organi_id')->toArray();

    return $organizaciones_id;
}
function ObtenerConexionBDExtractor($organi_id)
{
    return new ConfiguracionDatabase($organi_id);
}
function create_list_organizacion($puerto, $SN)
{
    $organi_id = DB::table('organizacion_puerto as op')
        ->where('puerto', $puerto)
        ->pluck('op.organi_id')->first();
    lista_organizaciones::updateOrCreate(
        [
            'organi_id' => $organi_id,
            'serie' => $SN,
            'puerto' => $puerto
        ],
        [
            'ultima_conexion' => Carbon::now('America/Lima')
        ]
    );
}
function fotos_asistencia($img, $organi_id, $fecha, $codigo)
{
    // * encode de organizacion
    $org_carpeta = organizacion::findOrFail($organi_id);
    $codigo_hash = "{$org_carpeta->organi_id}{$org_carpeta->organi_ruc}";
    $enconde_has = intval($codigo_hash, 36);

    // * fecha de img
    $fecha_img = Carbon::parse($fecha)->isoFormat("YYYY-MM-DD HH-mm-ss");
    $fecha_carpeta = Carbon::parse($fecha)->isoFormat("YYYY-MM-DD");
    $basePath = app_path("foto_asistencia/{$enconde_has}/{$fecha_carpeta}");
    // * validación de carpeta
    if (!file_exists($basePath)) {
        File::makeDirectory($basePath, $mode = 0777, true, true);
    }

    // 2) Extraer (si existe) el tipo declarado en el prefijo data-uri
    $declaredExt = null;
    $data = $img;

    if (preg_match('/^data:image\/(\w+);base64,/', $img, $m)) {
        $declaredExt = strtolower($m[1]); // png | jpeg | jpg | webp | gif
        $data = substr($img, strpos($img, ',') + 1);
    }

    // 3) Normalizar base64 (quitar espacios, saltos, variantes url-safe)
    $data = preg_replace('/\s+/', '', $data ?? '');
    // algunas fuentes usan url-safe: '-' y '_'
    $data = strtr($data, '-_', '+/');

    // 4) Decodificar con validación estricta
    $binary = base64_decode($data, true);
    if ($binary === false || $binary === '') {
        return null;
    }

    // 5) Validar que realmente es imagen y detectar MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_buffer($finfo, $binary);
    finfo_close($finfo);

    $mimeToExt = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        // agrega más si necesitas
    ];

    if (!isset($mimeToExt[$mime])) {
        // fallback: intenta getimagesizefromstring para una segunda opinión
        $g = @getimagesizefromstring($binary);
        if (!$g || empty($g['mime']) || !isset($mimeToExt[$g['mime']])) {
            return null; // no es una imagen válida
        }
        $mime = $g['mime'];
    }

    $ext = $mimeToExt[$mime];

    // 6) Armar nombre y guardar
    $filename = "{$codigo}-{$fecha_img}.{$ext}";
    $fullpath = $basePath . DIRECTORY_SEPARATOR . $filename;

    // Guardar de forma atómica
    if (file_put_contents($fullpath, $binary, LOCK_EX) === false) {
        return null;
    }
    $file_name = "/foto_asistencia/{$enconde_has}/{$fecha_carpeta}/{$filename}";
    return $file_name;
}
function create_dialing($name_bdE, $data)
{
    //BUSCAMOS SI LA MARCACION YA EXISTE
    $search_dialing = marcaciones_biometrico::on($name_bdE)
        ->where('dni', '=', $data['dni'])
        ->where('organi_id', '=', $data['organi_id'])
        ->where('marcacion', '=', $data['marcacion'])
        ->doesntExist();
    if ($search_dialing) {
        //*REGISTRAMOS MARCACION
        //marcaciones_biometrico::on($name_bdE)->create($data);
        $marcacion_bio = new marcaciones_biometrico();
        $marcacion_bio->setConnection($name_bdE);
        $marcacion_bio->dni = $data['dni'];
        $marcacion_bio->organi_id = $data['organi_id'];
        $marcacion_bio->marcacion = $data['marcacion'];
        $marcacion_bio->idDispositivos = $data['idDispositivos'];
        $marcacion_bio->modo = $data['modo'];
        $marcacion_bio->fechaRegistro = $data['fechaRegistro'];
        $marcacion_bio->estado = $data['estado'];
        $marcacion_bio->zona_horaria = $data['zona_horaria'];
        $marcacion_bio->temperatura = $data['temperatura'];
        $marcacion_bio->save();
    }
}

function create_template_biometric($array_api)
{
    try {
        // * API DE OPTENER CARGOS
        $response = getClientRHNUBE()->request(
            'POST',
            '/api/web_services/registry-biometrics',
            [
                'json' => $array_api
            ]
        );
        $body = json_decode($response->getBody(), true);
        return $body;
    } catch (RequestException $e) {
        return response()->json(array(
            "mensaje" =>   $e->getMessage(),
            "status" => 404,
        ), 404);
    }
}

function ActualizarUltimaConexionExtractor($enviroment, $organi_id, $SN)
{
    if (!empty($SN)) {
        //actualizar biometrcio en capa extractor
        $connection = $enviroment->setBDD_Ex()->conexion;
        $name_bd = $enviroment->setBDD_Ex()->name;

        $dispositivo = $connection->table('biometrico_estado')
            ->where('organi_id', '=', $organi_id)
            ->where('serie', '=', $SN)
            ->first();
        if ($dispositivo) {
            $detalle_biometrico = biometrico_estado::on($name_bd)->find($dispositivo->id);
            $detalle_biometrico->ultima_conexion = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
            $detalle_biometrico->save();
        } else {
            $detalle_biometrico = new  biometrico_estado();
            $detalle_biometrico->setConnection($name_bd);
            $detalle_biometrico->serie = $SN;
            $detalle_biometrico->organi_id = $organi_id;
            $detalle_biometrico->ultima_conexion = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
            $detalle_biometrico->save();
        }
    }
    return 'OK';
}

function MantenedorInformacionBiometrico($enviroment, $organi_id, $SN, $INFO)
{
    try {
        $connection = $enviroment->setBDD_Ex()->conexion;
        $name_bd = $enviroment->setBDD_Ex()->name;
        //*OBTENEMOS VALORES RECUPERADOS DE INFO
        $arrayInfo = explode(',', $INFO);
        $firmware = $arrayInfo[0]; // : Número de versión del firmware
        $usuarioReg = $arrayInfo[1]; //número de usuarios inscritos
        $huellasReg = $arrayInfo[2]; //número de huellas dactilares registradas
        $marcacionesReg = $arrayInfo[3]; //Número de registros de asistencia
        $IPequipo = $arrayInfo[4]; // Dirección IP del Equipo
        $algoritmoHuella = $arrayInfo[5]; // versión del algoritmo de huellas dactilares
        $algoritmoFacial = $arrayInfo[6]; //versión del algoritmo facial
        $rostrosReg = $arrayInfo[8]; //número de rostros inscritos

        $dispositivo = $connection->table('biometrico_estado')
            ->where('organi_id', '=', $organi_id)
            ->where('serie', '=', $SN)
            ->first();
        if ($dispositivo) {
            $detalle_biometrico = biometrico_estado::on($name_bd)->find($dispositivo->id);
            $detalle_biometrico->ultima_conexion = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
            $detalle_biometrico->ipinterna = $IPequipo;
            $detalle_biometrico->firmware =  $firmware;
            $detalle_biometrico->n_colaboradores =  $usuarioReg;
            $detalle_biometrico->n_marcaciones = $marcacionesReg;
            $detalle_biometrico->n_huellas =  $huellasReg;
            $detalle_biometrico->n_rostros =  $rostrosReg;
            $detalle_biometrico->algoritmo_huella = $algoritmoHuella;
            $detalle_biometrico->algoritmo_facial =  $algoritmoFacial;
            $detalle_biometrico->puertoInterno = $_SERVER['SERVER_PORT'];
            $detalle_biometrico->save();
        }
    } catch (Exception $e) {
        Log::channel("daily")->error("Function MantenedorInformacionBiometrico", ["message" => $e->getMessage(), "file" => __FILE__, "line" => __LINE__]);
    }
    return 'OK';
}

function ObtenerComandosPendientesBiometrico($enviroment, $organi_id, $SN)
{
    $response = '';
    $connection = $enviroment->setBDD_Ex()->conexion;

    $comandos_biometrico = $connection->table('comandos_biometrico')
        ->where('organi_id', '=', $organi_id)
        ->where('serie_dispositivo', '=', $SN)
        ->where('estado', '=', 1)
        ->where('tipo_dispo', 1)
        ->take(3)
        ->get();

    if ($comandos_biometrico->isNotEmpty()) {
        foreach ($comandos_biometrico as $comando) {
            $response = $response . "C:" . $comando->idcomandos_biometrico . ":" . $comando->comando . "\n";

            $connection->table('comandos_biometrico')
                ->where('idcomandos_biometrico', '=', $comando->idcomandos_biometrico)
                ->update([
                    'fechaEnviado'  => Carbon::now('America/Lima')
                ]);
        }
    }

    return  $response;
}

function ActualizarEstadoComando($enviroment, $comando_id, $respuesta, $resp)
{
    try {
        $estado = 1; //cuando el estado es 0 se a registrado el estado es pendiente, cuando ele stado es 1 el comando a sido
        //tomado por el biometrico, estado 2 cuando la respuesta del biometrico es ok y si es 3 error al ejecutar comando
        $connection = $enviroment->setBDD_Ex()->conexion;
        if ($resp != null) {
            if ($resp == 0) {
                $estado = 2;
            } else {
                $estado = 3;
            }
        }
        //EL ESTADO DOS SIGNIFICA QUE EL COMANDO SE EJECUTO EXITISAMENTE
        $connection->table('comandos_biometrico')
            ->where('idcomandos_biometrico', '=', $comando_id)
            ->update([
                'respuesta'  => $respuesta,
                'estado'  => $estado,
            ]);
    } catch (\Throwable $th) {
        Log::info($th->getMessage());
    }
    return  "ok";
}
function BuscarBiometrias($enviromentEX, $id)
{
    try {
        $connection = $enviromentEX->setBDD_Ex()->conexion;
        $name_bd = $enviromentEX->setBDD_Ex()->name;
        $comando = $connection->table('comandos_biometrico')
            ->where('idcomandos_biometrico', '=', $id)
            ->whereNotNull('serie_dispositivo_origen')
            ->whereNotNull('serie_dispositivo')
            ->whereNotNull('identificador_biometrico')
            ->where('estado', 2)
            ->first();
        if (empty($comando)) {
            return array();
        }
        if ($comando->serie_dispositivo == $comando->serie_dispositivo_origen) {
            return array();
        }
        $array_api = [];
        $tipo_registro = [];
        if ($comando->password == 1) {
            array_push($tipo_registro, 5);
        }
        if ($comando->tarjeta == 1) {
            array_push($tipo_registro, 6);
        }
        if ($comando->palma == 1) {
            array_push($tipo_registro, 1);
        }
        if ($comando->huella == 1) {
            array_push($tipo_registro, 2);
            array_push($tipo_registro, 3);
        }
        if (empty($tipo_registro)) {
            //para plantilla con 12 fotos
            $buscar_facial12 = $connection->table('comandos_biometrico')
                ->where('idregistro_descargabiometrias', '=', $comando->idregistro_descargabiometrias)
                ->where('identificador_biometrico', '=',  $comando->identificador_biometrico)
                ->where('estado', '=', 2)
                ->where('comando', 'like', "%FACE%")->count();
            if ($buscar_facial12 == 12) {
                array_push($tipo_registro, 4);
            }
            //para biofoto
            $buscar_facial = $connection->table('comandos_biometrico')
                ->where('idregistro_descargabiometrias', '=', $comando->idregistro_descargabiometrias)
                ->where('identificador_biometrico', '=',  $comando->identificador_biometrico)
                ->where('estado', '=', 2)
                ->where('comando', 'like', "%BIOPHOTO%")->count();
            if ($buscar_facial == 1) {
                array_push($tipo_registro, 4);
            }
        }
        if (!empty($tipo_registro)) {
            $array_api = array(
                'organi_id' =>   $comando->organi_id,
                'identificador_biometrico' =>   $comando->identificador_biometrico,
                'serie_origen' =>   $comando->serie_dispositivo_origen,
                'serie_destino' =>   $comando->serie_dispositivo,
                'tipo_registro' =>     $tipo_registro
            );
        }

        DB::disconnect($name_bd);
        return $array_api;
    } catch (\Throwable $th) {
        Log::info("error");
        Log::info($th->getMessage());
        $connection->table('comandos_biometrico')
            ->where('idregistro_descargabiometrias', '=',   $id)
            ->update([
                'estado_biometrico' => 2,
            ]);
        return array();
    }
}
function GuardarBiometrias($array_api)
{
    try {
        foreach ($array_api as  $dato) {
            $dato = (object) $dato;
            $organi_id = $dato->organi_id;
            $identificador_biometrico = (is_numeric($dato->identificador_biometrico)) ?  intval($dato->identificador_biometrico) : $dato->identificador_biometrico;
            $serie_origen = $dato->serie_origen;
            $serie_destino = $dato->serie_destino;
            $tipo_registro = $dato->tipo_registro;

            // * ------------------ Conexion de base datos --------------------------------
            $environment =  (new ConfiguracionBDController($organi_id))->setBDD();
            $connection = $environment->conexion;
            $name_bd = $environment->name;
            // * ---------------------------------------------------------------------------
            $empleado = $connection->table('empleado')
                ->where(DB::raw("IF(codigo_biometrico REGEXP '^[0-9]+$' = 1, CAST(codigo_biometrico AS UNSIGNED), codigo_biometrico)"), $identificador_biometrico)
                ->where('organi_id', '=', $organi_id)
                ->pluck('emple_id')
                ->first();
            $idDispositivo = $connection->table('dispositivos')
                ->where('organi_id', $organi_id)
                ->where('dispo_estadoActivo', 1)
                ->where('dispo_codigo', $serie_origen)->pluck('idDispositivos')->first();

            $idDispositivo_destino = $connection->table('dispositivos')
                ->where('organi_id', $organi_id)
                ->where('dispo_estadoActivo', 1)
                ->where('dispo_codigo', $serie_destino)->pluck('idDispositivos')->first();
            //*OBTENER BIOMETRIAS
            $plantillas = $connection->table('plantilla_empleadobio')
                ->where('organi_id', '=', $organi_id)
                ->where('idDispositivos', '=', $idDispositivo)
                ->where('idempleado', '=', $empleado)
                ->where('estado', 1)
                ->whereIn('tipo_registro', $tipo_registro)
                ->orderBy('orden', 'asc')
                ->get();

            $cont = 0;

            foreach ($plantillas as  $b) {
                //registro de contraseña
                if ($b->tipo_registro == 5 && $b->path != null) {
                    $consulta_passw  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 5)
                        ->where('idempleado', '=',  $empleado)
                        ->first();
                    if ($consulta_passw) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_passw->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->posicion_huella = 0;
                    $plantilla->idempleado =  $empleado;
                    $plantilla->tipo_registro = 5;
                    $plantilla->path = $b->path;
                    $plantilla->iFlag = 0;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->iLength = 0;
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos = $idDispositivo_destino;
                    $plantilla->save();
                }
                //fin de registro contrseña
                //inicio registro de tarjeta
                if ($b->tipo_registro == 6 && $b->path != null) {
                    $consulta_tarjeta  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 6)
                        ->where('idempleado', '=', $empleado)
                        ->first();
                    if ($consulta_tarjeta) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_tarjeta->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->posicion_huella = 0;
                    $plantilla->idempleado = $empleado;
                    $plantilla->tipo_registro = 6;
                    $plantilla->path = $b->path;
                    $plantilla->iFlag = 0;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->iLength = 0;
                    $plantilla->creado = Carbon::now('America/Lima')->format("Y-m-d");
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos = $idDispositivo_destino;
                    $plantilla->save();
                }
                //fin registro tarjeta
                if ($b->tipo_registro == 2 || $b->tipo_registro == 3) {
                    if ($b->tipo_registro == 2) {

                        $consulta_huella  = $connection->table('plantilla_empleadobio')
                            ->where('organi_id', '=', $organi_id)
                            ->where('idDispositivos', '=', $idDispositivo_destino)
                            ->where('tipo_registro', '=', 2)
                            ->where('idempleado', '=', $empleado)
                            ->first();

                        if ($consulta_huella) {
                            $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                        } else {
                            $plantilla = new plantilla_empleadobio();
                            $plantilla->setConnection($name_bd);
                        }
                        $plantilla->idempleado =  $empleado;
                        $plantilla->posicion_huella =   $b->posicion_huella;
                        $plantilla->tipo_registro = 2;
                        $plantilla->path =  $b->path;
                        $plantilla->iLength = $b->iLength;
                        $plantilla->iFlag = 1;
                        $plantilla->iFaceIndex = 0;
                        $plantilla->organi_id = $organi_id;
                        $plantilla->idDispositivos =  $idDispositivo_destino;
                        $plantilla->save();
                    } else {
                        $consulta_huella  = $connection->table('plantilla_empleadobio')
                            ->where('organi_id', '=', $organi_id)
                            ->where('idDispositivos', '=', $idDispositivo_destino)
                            ->where('tipo_registro', '=', 3)
                            ->where('idempleado', '=',  $empleado)
                            ->first();

                        if ($consulta_huella) {
                            $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                        } else {
                            $plantilla = new plantilla_empleadobio();
                            $plantilla->setConnection($name_bd);
                        }
                        $plantilla->idempleado =  $empleado;
                        $plantilla->posicion_huella =   $b->posicion_huella;
                        $plantilla->tipo_registro = 2;
                        $plantilla->path =  $b->path;
                        $plantilla->iLength = $b->iLength;
                        $plantilla->iFlag = 1;
                        $plantilla->iFaceIndex = 0;
                        $plantilla->organi_id = $organi_id;
                        $plantilla->idDispositivos =  $idDispositivo_destino;
                        $plantilla->save();
                    }
                }
                //fin de registro huella

                //nincio facial
                if ($b->tipo_registro == 4) {
                    $cont++;
                }
                if ($b->tipo_registro == 4 && is_numeric($b->posicion_huella) && $b->iFlag == 2) {
                    $orden = $b->posicion_huella + 2;
                    $consulta_huella  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 4)
                        ->where('iFlag', '=', 2)
                        ->where('orden', '=', $orden)
                        ->where('idempleado', '=', $empleado)
                        ->first();

                    if ($consulta_huella) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->idempleado = $empleado;
                    $plantilla->posicion_huella =   $b->posicion_huella;
                    $plantilla->tipo_registro = 4;
                    $plantilla->path =  $b->path;
                    $plantilla->iLength = $b->iLength;
                    $plantilla->iFlag = 2;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->orden = $orden;
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos =  $idDispositivo_destino;
                    $plantilla->save();
                }
                if ($b->tipo_registro == 4 && $cont == 1 && $b->iFlag == 2) {
                    $consulta_huella  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 4)
                        ->where('iFlag', '=', 2)
                        ->where('orden', '=', 1)
                        ->where('idempleado', '=', $empleado)
                        ->first();

                    if ($consulta_huella) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->idempleado = $empleado;
                    $plantilla->posicion_huella =  null;
                    $plantilla->FileName =   $b->fileName;
                    $plantilla->tipo_registro = 4;
                    $plantilla->path =  $b->path;
                    $plantilla->iLength = $b->iLength;
                    $plantilla->iFlag = 2;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->orden = 1;
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos =  $idDispositivo_destino;
                    $plantilla->save();
                }
                if ($b->tipo_registro == 4 && $b->orden == 1 && $b->iFlag == 9) {
                    $consulta_huella  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 4)
                        ->where('iFlag', '=', 9)
                        ->where('orden', '=', 1)
                        ->where('idempleado', '=', $empleado)
                        ->first();

                    if ($consulta_huella) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->idempleado = $empleado;
                    $plantilla->posicion_huella =  null;
                    $plantilla->FileName =   $b->fileName;
                    $plantilla->tipo_registro = 4;
                    $plantilla->path =  $b->path;
                    $plantilla->iLength = $b->iLength;
                    $plantilla->iFlag = 9;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos =  $idDispositivo_destino;
                    $plantilla->orden = 1;
                    $plantilla->save();
                }
                if ($b->tipo_registro == 4 && $b->orden == 2 && $b->iFlag == 9) {
                    $consulta_huella  = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 4)
                        ->where('iFlag', '=', 9)
                        ->where('orden', '=', 2)
                        ->where('idempleado', '=',  $empleado)
                        ->first();

                    if ($consulta_huella) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->posicion_huella = 0;
                    $plantilla->idempleado =  $empleado;
                    $plantilla->FileName =   $b->fileName;
                    $plantilla->tipo_registro = 4;
                    $plantilla->path =  $b->path;
                    $plantilla->iLength = $b->iLength;
                    $plantilla->iFlag = 9;
                    $plantilla->iFaceIndex = 0;
                    $plantilla->organi_id = $organi_id;
                    $plantilla->idDispositivos =  $idDispositivo_destino;
                    $plantilla->orden = 2;
                    $plantilla->MajorVer = $b->MajorVer;
                    $plantilla->MinorVer = $b->MinorVer;
                    $plantilla->save();
                }
                //fin registro rostro
                //inicio registro palma
                if ($b->tipo_registro == 1 && $b->path != null) {
                    $consulta_palma = $connection->table('plantilla_empleadobio')
                        ->where('organi_id', '=', $organi_id)
                        ->where('idDispositivos', '=', $idDispositivo_destino)
                        ->where('tipo_registro', '=', 1)
                        ->where('idempleado', '=',  $empleado)
                        ->where('iFaceIndex', '=', $b->iFaceIndex)
                        ->first();
                    if ($consulta_palma) {
                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_palma->id);
                    } else {
                        $plantilla = new plantilla_empleadobio();
                        $plantilla->setConnection($name_bd);
                    }
                    $plantilla->posicion_huella =  $b->posicion_huella;
                    $plantilla->idempleado = $empleado;
                    $plantilla->tipo_registro = 1;
                    $plantilla->path =   $b->path; //agre
                    $plantilla->iFlag = $b->iFlag;
                    $plantilla->organi_id =  $organi_id;
                    $plantilla->idDispositivos = $idDispositivo_destino;
                    $plantilla->orden  = $b->orden;
                    $plantilla->iLength = 0;
                    $plantilla->iFaceIndex = $b->iFaceIndex;
                    $plantilla->MajorVer = $b->MajorVer;
                    $plantilla->MinorVer = $b->MinorVer;
                    $plantilla->save();
                }
            }
        }
        return "ok";
    } catch (\Throwable $th) {
        Log::info("error al consumir api");
        return "error";
    }
}
