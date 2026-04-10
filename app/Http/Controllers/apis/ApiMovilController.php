<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\biometrico_estado;
use App\Models\marcaciones_biometrico;
use App\Models\organizacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiMovilController extends Controller
{
    public  function ultimaConexion(Request $request)
    {
        $validaciones = Validator::make($request->all(), [
            'organi_id' => 'required',
            'serie' => 'required',
        ]);
        if ($validaciones->fails()) {
            return response()->json($validaciones->errors(), 400);
        }
        $organi_id = $request->organi_id;
        $SN = $request->serie;
        $environment =  new ConfiguracionDatabase($organi_id);
        ActualizarUltimaConexionExtractor($environment, $organi_id, $SN);
        DB::disconnect($environment->setBDD_Ex()->name);
        return response()->json(["resultado" => true]);
    }
    public function RegistroDatosMovil(Request $request)
    {
        $validaciones = Validator::make($request->all(), [
            'organi_id' => 'required',
            'serie' => 'required',
            'modelo' => 'required',
            'firmware' => 'required',
        ]);
        if ($validaciones->fails()) {
            return response()->json($validaciones->errors(), 400);
        }
        try {
            $organi_id = $request->organi_id;
            $SN = $request->serie;
            $modelo = $request->modelo;
            $firmware = $request->firmware;
            $tipo =  isset($request->tipo) ? $request->tipo : 2;

            $environmentE =  (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
            $connectionE = $environmentE->conexion;
            $name_bdE = $environmentE->name;
            $dispositivo = $connectionE->table('biometrico_estado')
                ->where('organi_id', '=', $organi_id)
                ->where('serie', '=', $SN)
                ->first();
            if (empty($dispositivo)) {
                $detalle_biometrico = new  biometrico_estado();
                $detalle_biometrico->ultima_conexion = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                $detalle_biometrico->setConnection($name_bdE);
                $detalle_biometrico->firmware =  $firmware;
                $detalle_biometrico->modelo = $modelo;
                $detalle_biometrico->serie = $SN;
                $detalle_biometrico->organi_id = $organi_id;
                if ($tipo == 2) {
                    $detalle_biometrico->algoritmo_facial = "Nchk";
                    $detalle_biometrico->tipo_dispositivo = 2;
                } else {
                    $detalle_biometrico->tipo_dispositivo =  $tipo;
                }
                $detalle_biometrico->save();
            }

            DB::disconnect($name_bdE);
            return response()->json(["resultado" => true]);
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return response()->json(["resultado" => false]);
        }
    }
    public function RegistrarMarcaciones(Request $request)
    {
        $validacion = Validator::make($request->all(), [
            'data' => 'required',
        ], [
            'data.required' => '*Debe ingresar datos',
        ]);

        if ($validacion->fails()) {
            return response($validacion->errors(), 400);
        }
        $array_logs = [1484, 828, 608, 1520, 783];
        $ids_organis = [1230, 1231, 1232, 1233, 1234, 1325, 1329, 1327];
        $ids_ruta = [4145, 4166, 4258, 4254];
        try {
            $data = $request->data;
            foreach ($data as $marcacion) {
                $datos = (object)$marcacion;
                $guardar_marcacion =  isset($datos->guardar_marcacion) ? $datos->guardar_marcacion : true;
                if ($guardar_marcacion) {
                    if (!isset($datos->organi_id)) continue;
                    if (!isset($datos->codigo_biometrico)) continue;
                    if (!isset($datos->id_dispositivo)) continue;
                    if (!isset($datos->marcacion)) continue;
                    //validamos organizaciones estado
                    $organi_id =  $datos->organi_id;
                    $organizacion = organizacion::where('organi_id', $organi_id)
                        ->where('organi_estado', 1)->doesntExist();
                    if ($organizacion) continue;
                    $dispositivo =  $datos->id_dispositivo;
                    if (in_array($organi_id, $ids_organis) && in_array($dispositivo, $ids_ruta)) continue;
                    $codigo_biometrico = $datos->codigo_biometrico;
                    $marcacion = $datos->marcacion;
                    $foto = isset($datos->foto) ? $datos->foto : null;
                    $latitud = isset($datos->latitud) ? $datos->latitud : null;
                    $longitud = isset($datos->longitud) ? $datos->longitud : null;
                    $idSupervisor = isset($datos->idControlador) ? $datos->idControlador : null;
                    $puntoC_id = isset($datos->puntoC_id) ? $datos->puntoC_id : null;
                    $fecha_dispositivo_automatica = isset($datos->fecha_dispositivo_automatica) ? $datos->fecha_dispositivo_automatica : "NO";
                    $fecha_dispositivo_cliente = isset($datos->fecha_dispositivo_cliente) ? $datos->fecha_dispositivo_cliente : null;
                    $id_actividad = isset($datos->actividad) ? $datos->actividad : null;
                    //datos para tareo
                    $idsubActividad = isset($datos->idsubActividad) ? $datos->idsubActividad : null;
                    $centC_id = isset($datos->centC_id) ? $datos->centC_id : null;
                    $avance = isset($datos->avance) ? $datos->avance : null;
                    $idunidad_medida = isset($datos->idunidad_medida) ? $datos->idunidad_medida : null;
                    $idHoraEmp = isset($datos->idHoraEmp) ? $datos->idHoraEmp : null;
                    $tiempo_trabajado = isset($datos->tiempo_trabajado) ? $datos->tiempo_trabajado : null;
                    $tipoMarcacion = isset($datos->tipoMarcacion) ? $datos->tipoMarcacion : null;
                    $modo = isset($datos->modo) ? $datos->modo : null;
                    $comentario = isset($datos->comentario) ? $datos->comentario : null;
                    $imagenes = isset($datos->imagenes) ? json_encode($datos->imagenes) : null;

                    $environmentE =  (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
                    $connectionEx = $environmentE->conexion;
                    $name_bdEx = $environmentE->name;
                    //para almacenar la foto
                    $marcacion_foto = null;
                    $organi_almacFoto = DB::table('organizacion')->where('organi_id', $organi_id)
                        ->pluck('organi_almacFoto')->first();
                    if ($organi_almacFoto == 1) {
                        if (!empty($foto)) {
                            $marcacion_foto = fotos_asistencia($foto, $organi_id, $marcacion, $codigo_biometrico);
                        }
                    }

                    //*BUSCAR SI YA EXISTE MARCACION
                    $buscarMarcacion = $connectionEx->table('marcaciones_biometrico')
                        ->where('dni', '=', $codigo_biometrico)
                        ->where('organi_id', '=', $organi_id)
                        ->where('marcacion', '=',  $marcacion)
                        ->where('idDispositivos', '=',  $dispositivo)
                        ->pluck('idmarcaciones_biometrico')
                        ->first();
                    if (!$buscarMarcacion) {
                        //*REGISTRAMOS MARCACION
                        $marcaciones_biometrico = new marcaciones_biometrico();
                        $marcaciones_biometrico->setConnection($name_bdEx);
                        $marcaciones_biometrico->organi_id = $organi_id;
                        $marcaciones_biometrico->idDispositivos =  $dispositivo;
                        $marcaciones_biometrico->marcacion = $marcacion;
                        $marcaciones_biometrico->fechaRegistro = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                        $marcaciones_biometrico->estado = 0;
                        $marcaciones_biometrico->dni = $codigo_biometrico;
                        $marcaciones_biometrico->foto = $marcacion_foto;
                        $marcaciones_biometrico->latitud = $latitud;
                        $marcaciones_biometrico->longitud = $longitud;
                        $marcaciones_biometrico->id_supervisor = $idSupervisor;
                        $marcaciones_biometrico->id_puntoControl = $puntoC_id;
                        $marcaciones_biometrico->fecha_dispositivo_automatica = $fecha_dispositivo_automatica;
                        $marcaciones_biometrico->fecha_dispositivo_cliente = $fecha_dispositivo_cliente;
                        $marcaciones_biometrico->id_actividad = $id_actividad;
                        $marcaciones_biometrico->idsubActividad = $idsubActividad;
                        $marcaciones_biometrico->centC_id = $centC_id;
                        $marcaciones_biometrico->avance = $avance;
                        $marcaciones_biometrico->idunidad_medida = $idunidad_medida;
                        $marcaciones_biometrico->idHoraEmp =    $idHoraEmp;
                        $marcaciones_biometrico->tiempo_trabajado = $tiempo_trabajado;
                        $marcaciones_biometrico->tipoMarcacion = $tipoMarcacion;
                        if (!empty($modo)) $marcaciones_biometrico->modo = $modo;
                        $marcaciones_biometrico->comentario = $comentario;
                        $marcaciones_biometrico->imagenes = $imagenes;
                        $marcaciones_biometrico->save();
                    }
                    DB::disconnect($name_bdEx);
                    $environmentE = null;
                    $connectionEx = null;
                    $name_bdEx = null;
                }
            }
            return response()->json(["resultado" => true]);
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return response()->json(["resultado" => false]);
        }
    }
    public function almacenar_image(Request $request)
    {
        $validacion = validator::make($request->all(), [
            'organi_id' => 'required',
            'dispositivo_id' => 'required',
            'imagen' => 'required',
            'identificador' => 'required',

        ], [
            'data.required' => '*Debe ingresar datos',
        ]);
        if ($validacion->fails()) {
            return response($validacion->errors(), 400);
        }
        $dispositivo = $request->dispositivo_id;
        $img = $request->imagen;
        $organi_id = $request->organi_id;
        $identificador = $request->identificador;
        // * encode de organizacion
        // * encode de organizacion
        $org_carpeta = organizacion::findOrFail($organi_id);
        $codigo_hash = "{$org_carpeta->organi_id}{$org_carpeta->organi_ruc}";
        $enconde_has = intval($codigo_hash, 36);

        $basePath = public_path("fotos_biometrico/{$enconde_has}");
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
        $filename = "{$identificador}-{$dispositivo}.{$ext}";
        $fullpath = $basePath . DIRECTORY_SEPARATOR . $filename;

        // Guardar de forma atómica
        if (file_put_contents($fullpath, $binary, LOCK_EX) === false) {
            return null;
        }
        $file_name = "/fotos_biometrico/{$enconde_has}/{$filename}";
        return response()->json(["url" => $file_name], 200);
    }
}
