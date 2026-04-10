<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DB\ConfiguracionDatabase;
use App\Models\biometrico_estado;
use App\Models\marcaciones_biometrico;
use App\Models\organizacion;
use App\Models\organizacion_puerto;
use App\Models\plantilla_empleadobio;
use Carbon\Carbon;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordEventsController extends Controller
{
    public function record_events(Request $request)
    {
        try {
            //SN Y TABLE(DATOS DE TABLA)
            //agregar validate validaciones para el puerto
            $SN = $request->SN;
            $table = $request->table;
            $content = $request->getContent();
            //*--------------OBTNENER ID DE ORGANIAION-----------------
            $puerto = $_SERVER['SERVER_PORT'];
            //*BUSCAR ORGANIZACION
            $organizacion = ObtenerOrganiIdXPuerto($puerto);
            //*DEVOLVER ERROR
            if (empty($organizacion)) {
                create_list_organizacion($puerto, $SN);
                return 'error';
            }
            $array_modelo = ["Face 3A", "Face 2A"];
            $new_version = array_unique(organizacion_puerto::where('new_version', 1)
                ->where('puerto',   $puerto)
                ->pluck('organi_id')
                ->toArray());
            $organizacion = array_diff($organizacion, $new_version);
            //*SE RECORROE POR ORGANACIONES ENCONTRADAS
            foreach ($organizacion as $org) {
                // *-----------  CONEXION BD-------------------------------
                $environment =  new ConfiguracionBDController($org);
                $connection = $environment->setBDD()->conexion;
                $name_bd = $environment->setBDD()->name;
                //*BUSCAR DISPOSITIVO SI LO TENEMOS REGISTRADO
                $dispositivo_data = $connection->table('dispositivos')
                    ->where('tipoDispositivo', '=', 3)
                    ->where('organi_id', '=', $org)
                    ->where('dispo_estadoActivo', 1)
                    ->where('dispo_codigo', '=', $SN)
                    ->select('idDispositivos', 'tipoProceso', 'biometrico_tipoTexto')
                    ->first();

                //*SI TENEMO DISPOSITIO REGISTRADO
                if (!empty($dispositivo_data)) {
                    $tipoTexto =  $dispositivo_data->biometrico_tipoTexto;
                    $dispositivo = $dispositivo_data->idDispositivos;
                    $tipoProceso = $dispositivo_data->tipoProceso;
                    /* ActualizarUltimaConexionBiometrico($SN, $org, $environment); */
                    switch ($table) {
                        case "ATTLOG":
                            $environmentE =  (new ConfiguracionDatabase($org))->setBDD_Ex();
                            $connectionE = $environmentE->conexion;
                            $name_bdE = $environmentE->name;
                            /******* Iniciamos lectura de marcaciones *************/
                            //Obtenemos el array de saltos de linea
                            $contenido = explode("\n", $content);
                            $zona_horaria =    $connectionE->table('biometrico_estado')
                                ->where('organi_id', '=', $org)
                                ->where('serie', '=',  $SN)
                                ->pluck('zona_horaria')->first();
                            if (empty($zona_horaria)) {
                                $zona_horaria = "America/Lima";
                            }
                            foreach ($contenido as $item) {
                                //Array de tabulaciones
                                $linea = explode("\t", $item);

                                //Validamos frente a espacios iniciales, algunos dispositivos envian un espacio inicial en la primera linea
                                if (!$linea[0] == "") {
                                    //verifico si el biometrico requiere que el identiicador lleve ceros delante
                                    if ($tipoTexto == 1) {
                                        $identificador_dispositivo = $linea[0];
                                    } else {
                                        $identificador_dispositivo = is_numeric($linea[0]) ? intval($linea[0]) : $linea[0];
                                    }
                                    $buscarMarcacion = $connectionE->table('marcaciones_biometrico')
                                        ->where('dni', '=', $identificador_dispositivo)
                                        ->where('organi_id', '=', $org)
                                        ->where('marcacion', '=', $linea[1])
                                        ->pluck('idmarcaciones_biometrico')
                                        ->first();
                                    if (!$buscarMarcacion) {
                                        //*REGISTRAMOS MARCACION
                                        $marcaciones_biometrico = new marcaciones_biometrico();
                                        $marcaciones_biometrico->setConnection($name_bdE);
                                        $marcaciones_biometrico->organi_id = $org;
                                        $marcaciones_biometrico->idDispositivos = $dispositivo;
                                        $marcaciones_biometrico->serie = $SN;
                                        $marcaciones_biometrico->marcacion = $linea[1];
                                        $marcaciones_biometrico->modo = $tipoProceso == 2 ? 'C' : ($tipoProceso == 3 ? 'AC' : 'A');
                                        $marcaciones_biometrico->fechaRegistro = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                                        $marcaciones_biometrico->estado = 0;
                                        //*SI HAY TEMPERATURA
                                        if (count($linea) > 8) {
                                            $marcaciones_biometrico->temperatura = $linea[8];
                                        }
                                        $marcaciones_biometrico->dni =  $identificador_dispositivo;
                                        $marcaciones_biometrico->zona_horaria = $zona_horaria;
                                        $marcaciones_biometrico->save();
                                    }
                                }
                            }
                            DB::disconnect($name_bdE);
                            break;
                        case "OPLOG":
                            break;
                        case "BIODATA":
                            $contenidoGeneral = explode("\n", $content);
                            foreach ($contenidoGeneral  as $item) {
                                $contenido = explode("\t", $item);
                                $iFaceIndex = null;
                                $iFlag = null;
                                $MinorVer = null;
                                $MajorVer = null;
                                $paths = null;
                                $No = null;
                                $Duress = null;
                                $valido = null;
                                $Format = null;
                                $dni = null;
                                foreach ($contenido as  $value) {
                                    if (substr($value, 0, strlen("BIODATA Pin=")) == "BIODATA Pin=") {
                                        $dni = substr($value, strpos($value, 'BIODATA Pin=') + strlen("BIODATA Pin="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Index=")) == "Index=") {
                                        $iFaceIndex = substr($value, strpos($value, 'Index=') + strlen("Index="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Type=")) == "Type=") {
                                        $iFlag = substr($value, strpos($value, 'Type=') + strlen("Type="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("MajorVer=")) == "MajorVer=") {
                                        $MajorVer = substr($value, strpos($value, 'MajorVer=') + strlen("MajorVer="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("MinorVer=")) == "MinorVer=") {
                                        $MinorVer = substr($value, strpos($value, 'MinorVer=') + strlen("MinorVer="));
                                        continue;
                                    }

                                    // Apartir de aqui esta data no se envia en API RH Nube
                                    if (substr($value, 0, strlen("No=")) == "No=") {
                                        $No = substr($value, strpos($value, 'No=') + strlen("No="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Duress=")) == "Duress=") {
                                        $Duress = substr($value, strpos($value, 'Duress=') + strlen("Duress="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Valid=")) == "Valid=") {
                                        $valido = substr($value, strpos($value, 'Valid=') + strlen("Valid="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Format=")) == "Format=") {
                                        $Format = substr($value, strpos($value, 'Format=') + strlen("Format="));
                                        continue;
                                    }

                                    if (substr($value, 0, strlen("Tmp=")) == "Tmp=") {
                                        $paths = substr($value, strpos($value, 'Tmp=') + strlen("Tmp="));
                                        if ($tipoTexto == 1) {
                                            $identificador = $dni;
                                        } else {
                                            $identificador = is_numeric($dni) ? intval($dni) : $dni;
                                        }
                                        $empleado = $connection->table('empleado')
                                            ->where('codigo_biometrico', '=', $identificador)
                                            ->where('organi_id', '=', $org)
                                            ->pluck('emple_id')
                                            ->first();

                                        //Terminamos el proceso
                                        if ($empleado) {
                                            if (empty($paths)) {
                                                $paths = substr($contenido[9], strpos($contenido[9], 'Tmp=') + strlen("Tmp="));
                                            }
                                            if ($iFlag == 9) {
                                                $consulta_caras  = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', $org)
                                                    ->where('idDispositivos', '=', $dispositivo)
                                                    ->where('tipo_registro', '=', 4)
                                                    ->where('idempleado', '=', $empleado)
                                                    ->where('orden', '=', 2)
                                                    ->first();
                                                if ($consulta_caras) {
                                                    $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_caras->id);
                                                } else {
                                                    $plantilla = new plantilla_empleadobio();
                                                    $plantilla->setConnection($name_bd);
                                                }
                                                //ceparo el TMP PARA OPTENER UNICAMENTE EL CODIGO BASE 64 DE LA IMAGEN
                                                $plantilla->posicion_huella =  0;
                                                $plantilla->idempleado = $empleado;
                                                $plantilla->tipo_registro = 4;
                                                $plantilla->path =  $paths; //agre
                                                $plantilla->iFlag = $iFlag;
                                                $plantilla->organi_id = $org;
                                                $plantilla->idDispositivos = $dispositivo;
                                                $plantilla->orden = 2;
                                                $plantilla->iLength = 0;
                                                $plantilla->iFaceIndex = $iFaceIndex;
                                                $plantilla->MajorVer = $MajorVer;
                                                $plantilla->MinorVer = $MinorVer;
                                                $plantilla->save();
                                            } else {
                                                $consulta_palma  = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', $org)
                                                    ->where('idDispositivos', '=', $dispositivo)
                                                    ->where('tipo_registro', '=', 1)
                                                    ->where('idempleado', '=', $empleado)
                                                    ->where('orden', '=',  $iFaceIndex)
                                                    ->first();
                                                if ($consulta_palma) {
                                                    $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_palma->id);
                                                } else {
                                                    $plantilla = new plantilla_empleadobio();
                                                    $plantilla->setConnection($name_bd);
                                                }

                                                $plantilla->posicion_huella =  0;
                                                $plantilla->idempleado = $empleado;
                                                $plantilla->tipo_registro = 1;
                                                $plantilla->path =  $paths; //agre
                                                $plantilla->iFlag = $iFlag;
                                                $plantilla->organi_id = $org;
                                                $plantilla->idDispositivos = $dispositivo;
                                                $plantilla->orden  = $iFaceIndex;
                                                $plantilla->iLength = 0;
                                                $plantilla->iFaceIndex = $iFaceIndex;
                                                $plantilla->MajorVer = $MajorVer;
                                                $plantilla->MinorVer = $MinorVer;
                                                $plantilla->save();
                                            }
                                            $iFaceIndex = null;
                                            $iFlag = null;
                                            $posicion_huella = null;
                                            $MinorVer = null;
                                            $MajorVer = null;
                                            $paths = null;
                                            $No = null;
                                            $Duress = null;
                                            $valido = null;
                                            $Format = null;
                                            $dni = null;
                                        }
                                    }
                                }
                            }
                            break;
                        case "OPERLOG":
                            $contenidoGeneral = explode("\t", $content);
                            //dESCRAGAR INFORMACION DEL HUELLA POR USUARIO
                            $opcion = explode(" ",  $contenidoGeneral[0]);

                            if (count($opcion) > 1 && !is_bool(strpos($opcion[1], 'PIN='))) {

                                $identificador_dispositivo = substr($opcion[1], strpos($opcion[1], 'PIN=') + strlen("PIN="));
                                if ($tipoTexto == 1) {
                                    $identificador_dispositivo =  $identificador_dispositivo;
                                } else {
                                    $identificador_dispositivo = is_numeric($identificador_dispositivo) ? intval($identificador_dispositivo) :  $identificador_dispositivo;
                                }
                                //*BUSCAR EMPLEADO
                                $empleado = $connection->table('empleado')
                                    ->where('codigo_biometrico', '=', $identificador_dispositivo)
                                    ->where('organi_id', '=', $org)
                                    ->pluck('emple_id')
                                    ->first();
                                if ($empleado) {
                                    switch ($opcion[0]) {
                                        case 'FP':
                                            $consulta_huella  = $connection->table('plantilla_empleadobio')
                                                ->where('organi_id', '=', $org)
                                                ->where('idDispositivos', '=', $dispositivo)
                                                ->where('tipo_registro', '=', 2)
                                                ->where('idempleado', '=', $empleado)
                                                ->first();
                                            if ($consulta_huella) {
                                                $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_huella->id);
                                            } else {
                                                $plantilla = new plantilla_empleadobio();
                                                $plantilla->setConnection($name_bd);
                                            }
                                            $plantilla->idempleado = $empleado;
                                            $plantilla->posicion_huella = substr($contenidoGeneral[1], strpos($contenidoGeneral[1], 'FID=') + strlen("FID="));
                                            $plantilla->tipo_registro = 2;
                                            $plantilla->path = substr($contenidoGeneral[4], strpos($contenidoGeneral[4], 'TMP=') + strlen("TMP="));
                                            $plantilla->iLength = substr($contenidoGeneral[2], strpos($contenidoGeneral[2], 'Size=') + strlen("Size="));
                                            $plantilla->iFlag = 1;
                                            $plantilla->iFaceIndex = 0;
                                            $plantilla->organi_id = $org;
                                            $plantilla->idDispositivos = $dispositivo;
                                            $plantilla->save();
                                            break;
                                        case 'USERPIC':
                                            // *-----------  CONEXION BD-------------------------------
                                            $environmentE = (new ConfiguracionDatabase($org))->setBDD_Ex();
                                            $connectionE = $environmentE->conexion;
                                            $name_bdE = $environmentE->name;
                                            $iFlag = 2;
                                            $modelo = $connectionE->table('biometrico_estado')
                                                ->where('organi_id', '=', $org)
                                                ->where('serie', '=',  $SN)
                                                ->pluck('modelo')->first();
                                            foreach ($array_modelo as $value) {
                                                $pos = strpos($modelo, $value);
                                                if ($pos !== false) {
                                                    $iFlag = 9;
                                                    break;
                                                }
                                            }
                                            //silkbio 101tc
                                            $consulta_cara  = $connection->table('plantilla_empleadobio')
                                                ->where('organi_id', '=', $org)
                                                ->where('idDispositivos', '=', $dispositivo)
                                                ->where('tipo_registro', '=', 4)
                                                ->where('idempleado', '=', $empleado)
                                                ->where('orden', '=', 1)
                                                ->first();
                                            if ($consulta_cara) {
                                                $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_cara->id);
                                            } else {
                                                $plantilla = new plantilla_empleadobio();
                                                $plantilla->setConnection($name_bd);
                                            }
                                            $plantilla->idempleado = $empleado;
                                            $plantilla->posicion_huella = null;
                                            $plantilla->fileName = substr($contenidoGeneral[1], strpos($contenidoGeneral[1], 'FileName=') + strlen("FileName="));
                                            $plantilla->tipo_registro = 4;
                                            $plantilla->path = substr($contenidoGeneral[3], strpos($contenidoGeneral[3], 'Content=') + strlen("Content="));
                                            $plantilla->iLength = substr($contenidoGeneral[2], strpos($contenidoGeneral[2], 'Size=') + strlen("Size="));
                                            $plantilla->iFlag =  $iFlag;
                                            $plantilla->organi_id = $org;
                                            $plantilla->iFaceIndex = 0;
                                            $plantilla->idDispositivos = $dispositivo;
                                            $plantilla->orden = 1;
                                            $plantilla->save();
                                            break;
                                        case 'FACE':
                                            //silkbio 101tc
                                            $biometria = null;
                                            $facial = null;
                                            $huella_p = null;
                                            $size = null;
                                            $valid = null;
                                            $base64_face = null;
                                            //recorrido por cada uno de los datos enviados al momento de registrar un rostro
                                            foreach ($contenidoGeneral as  $faciales) {
                                                $facial = strtoupper($faciales); //combierto a mayuscula el contenio del array

                                                if (gettype(strpos($facial, 'FID=')) == "integer") {
                                                    $huella_p = substr($facial, strpos($facial, 'FID=') + strlen("FID=")); //obtengo el numero de foto
                                                    continue;
                                                }
                                                if (gettype(strpos($facial, 'SIZE=')) == "integer") {
                                                    $size = substr($facial, strpos($facial, 'SIZE=') + strlen("SIZE=")); //btengo el tamaño de la cadena
                                                    continue;
                                                }
                                                //$valid = substr($facial, strpos($facial, 'VALID=') + strlen("VALID=")); //obtengo el valor del valid
                                                if (gettype(strpos($faciales, 'TMP=')) == "integer") {
                                                    $base64_face = substr($faciales, strpos($faciales, 'TMP=') + strlen("TMP=")); //obtengo el valor de la cadeda en base64
                                                    $biometria = explode("\n",  $base64_face); //separo la base 64 con el pin del usuario

                                                    if (!empty($biometria)) {
                                                        $consulta_caras  = $connection->table('plantilla_empleadobio')
                                                            ->where('organi_id', '=', $org)
                                                            ->where('idDispositivos', '=', $dispositivo)
                                                            ->where('tipo_registro', '=', 4)
                                                            ->where('idempleado', '=', $empleado)
                                                            ->where('posicion_huella', '=', $huella_p)
                                                            ->first();
                                                        if ($consulta_caras) {
                                                            $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_caras->id);
                                                        } else {
                                                            $plantilla = new plantilla_empleadobio();
                                                            $plantilla->setConnection($name_bd);
                                                        }
                                                        //ceparo el TMP PARA OPTENER UNICAMENTE EL CODIGO BASE 64 DE LA IMAGEN
                                                        $plantilla->idempleado = $empleado;
                                                        $plantilla->posicion_huella = $huella_p;
                                                        $plantilla->tipo_registro = 4;
                                                        $plantilla->path =   $biometria[0]; //agre
                                                        $plantilla->iLength = $size;
                                                        $plantilla->iFaceIndex = 0;
                                                        $plantilla->iFlag = 2;
                                                        $plantilla->organi_id = $org;
                                                        $plantilla->idDispositivos = $dispositivo;
                                                        $plantilla->orden = (int)$huella_p + 2;
                                                        $plantilla->save();
                                                        $biometria = null;
                                                        $facial = null;
                                                        $huella_p = null;
                                                        $size = null;
                                                        $valid = null;
                                                        $base64_face = null;
                                                    }
                                                }
                                            }
                                            break;
                                        case 'USER':
                                            $password = substr($contenidoGeneral[3], strpos($contenidoGeneral[3], 'Passwd=') + strlen("Passwd="));

                                            if ($password != null) {
                                                $consulta_passw  = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', $org)
                                                    ->where('idDispositivos', '=', $dispositivo)
                                                    ->where('tipo_registro', '=', 5)
                                                    ->where('idempleado', '=', $empleado)
                                                    ->first();
                                                if ($consulta_passw) {
                                                    $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_passw->id);
                                                } else {
                                                    $plantilla = new plantilla_empleadobio();
                                                    $plantilla->setConnection($name_bd);
                                                }
                                                $plantilla->posicion_huella =  0;
                                                $plantilla->idempleado = $empleado;
                                                $plantilla->tipo_registro = 5;
                                                $plantilla->path = $password;
                                                $plantilla->iLength = 0;
                                                $plantilla->iFlag = 0;
                                                $plantilla->iFaceIndex = 0;
                                                $plantilla->organi_id = $org;
                                                $plantilla->idDispositivos = $dispositivo;
                                                $plantilla->save();
                                            }
                                            $tarjeta = substr($contenidoGeneral[4], strpos($contenidoGeneral[4], 'Card=') + strlen("Card="));

                                            if ($tarjeta != null) {
                                                //DAMOS DE BAJA A LA TARJETA USADA POR EL EMPEADO SI ES Q TIENE
                                                $plantilla_empre = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', session('sesionidorg'))
                                                    ->where('tipo_registro', 6)
                                                    ->where('idempleado', '=', $empleado)
                                                    ->where('estado', 1)
                                                    ->where('idDispositivos', $dispositivo)
                                                    ->update([
                                                        'estado'  => 0,
                                                        'baja'  => Carbon::now('America/Lima')
                                                    ]);
                                                //DAMOS D EBAJA A LA TARJETA SI ESTUBIERA ASIGNADO
                                                $plantilla_empre = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', session('sesionidorg'))
                                                    ->where('tipo_registro', 6)
                                                    ->where('path', $tarjeta)
                                                    ->where('estado', 1)
                                                    ->where('idDispositivos', $dispositivo)
                                                    ->update([
                                                        'estado'  => 0,
                                                        'baja'  => Carbon::now('America/Lima')
                                                    ]);
                                                $consulta_tarjeta  = $connection->table('plantilla_empleadobio')
                                                    ->where('organi_id', '=', $org)
                                                    ->where('idDispositivos', '=', $dispositivo)
                                                    ->where('tipo_registro', '=', 6)
                                                    ->where('path', $tarjeta)
                                                    ->where('idempleado', '=', $empleado)
                                                    ->first();
                                                if ($consulta_tarjeta) {
                                                    $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_tarjeta->id);
                                                } else {
                                                    $plantilla = new plantilla_empleadobio();
                                                    $plantilla->setConnection($name_bd);
                                                }
                                                $plantilla->posicion_huella =  0;
                                                $plantilla->idempleado = $empleado;
                                                $plantilla->tipo_registro = 6;
                                                $plantilla->path = $tarjeta;
                                                $plantilla->organi_id = $org;
                                                $plantilla->iLength = 0;
                                                $plantilla->iFlag = 0;
                                                $plantilla->iFaceIndex = 0;
                                                $plantilla->estado = 1;
                                                $plantilla->creado = Carbon::now('America/Lima')->format("Y-m-d");
                                                $plantilla->idDispositivos = $dispositivo;
                                                $plantilla->save();
                                            }
                                            break;
                                        case "BIOPHOTO":
                                            $FileName = null;
                                            $type = null;
                                            $size = null;
                                            foreach ($contenidoGeneral as $item) {
                                                if (substr($item, 0, strlen("FileName=")) == "FileName=") {
                                                    $FileName = substr($item, strpos($item, 'FileName=') + strlen("FileName="));
                                                    continue;
                                                }
                                                if (substr($item, 0, strlen("Type=")) == "Type=") {
                                                    $type = substr($item, strpos($item, 'Type=') + strlen("Type="));
                                                    continue;
                                                }

                                                if (substr($item, 0, strlen("Size=")) == "Size=") {
                                                    $size = substr($item, strpos($item, 'Size=') + strlen("Size="));
                                                    continue;
                                                }

                                                if (substr($item, 0, strlen("Content=")) == "Content=") {
                                                    $path = substr($item, strpos($item, 'Content=') + strlen("Content="));

                                                    $consulta_cara  = $connection->table('plantilla_empleadobio')
                                                        ->where('organi_id', '=', $org)
                                                        ->where('idDispositivos', '=', $dispositivo)
                                                        ->where('tipo_registro', '=', 4)
                                                        ->where('idempleado', '=', $empleado)
                                                        ->where('orden', '=', 1)
                                                        ->first();
                                                    if ($consulta_cara) {
                                                        $plantilla = plantilla_empleadobio::on($name_bd)->find($consulta_cara->id);
                                                    } else {
                                                        $plantilla = new plantilla_empleadobio();
                                                        $plantilla->setConnection($name_bd);
                                                    }
                                                    $plantilla->idempleado = $empleado;
                                                    $plantilla->posicion_huella = null;
                                                    $plantilla->fileName = $FileName;
                                                    $plantilla->tipo_registro = 4;
                                                    $plantilla->path =  $path;
                                                    $plantilla->iLength =   $size;
                                                    $plantilla->iFlag = ($type == 0) ? 9 : $type;
                                                    $plantilla->iFaceIndex = 0;
                                                    $plantilla->organi_id = $org;
                                                    $plantilla->idDispositivos = $dispositivo;
                                                    $plantilla->orden = 1;
                                                    $plantilla->save();
                                                    $FileName = null;
                                                    $type = null;
                                                    $size = null;
                                                }
                                            }
                                            break;
                                    }
                                }
                            }
                            break;
                        case "ATTPHOTO":
                            $organi_almacFoto = DB::table('organizacion')->where('organi_id', $org)
                                ->pluck('organi_almacFoto')->first();
                            if ($organi_almacFoto == 1) {
                                $FileName = "";
                                $size = 0;
                                $SN = "";
                                $codigo_biometrico = 0;
                                $foto = "";
                                $fecha = "";
                                $datosObtenidos = explode("\n", $content, 4);
                                if (substr($datosObtenidos[0], 0, strlen("PIN=")) == "PIN=") {
                                    $FileName = substr($datosObtenidos[0], strpos($datosObtenidos[0], 'PIN=') + strlen("PIN="));
                                }
                                if ($FileName) {
                                    $empleado = explode("-", $FileName);
                                    if (count($empleado) >= 2) {
                                        $marcacion = $empleado[0];
                                        $extraer_codigo = explode(".", $empleado[1])[0];
                                        if ($tipoTexto == 1) {
                                            $codigo_biometrico = $extraer_codigo;
                                        } else {
                                            $codigo_biometrico = is_numeric($extraer_codigo) ? intval($extraer_codigo) :  $extraer_codigo;
                                        }
                                        $fecha = Carbon::parse($marcacion)->isoFormat("YYYY-MM-DD HH:mm:ss");
                                    }
                                }
                                if (substr($datosObtenidos[1], 0, strlen("SN=")) == "SN=") {
                                    $SN = substr($datosObtenidos[1], strpos($datosObtenidos[1], 'SN=') + strlen("SN="));
                                }
                                if (substr($datosObtenidos[2], 0, strlen("size=")) == "size=") {
                                    $size = substr($datosObtenidos[2], strpos($datosObtenidos[2], 'size=') + strlen("size="));
                                }
                                $uploadphoto = "";
                                if (substr($datosObtenidos[3], 0, strlen("CMD=uploadphoto")) == "CMD=uploadphoto") {
                                    $uploadphoto = substr($datosObtenidos[3], strpos($datosObtenidos[3], 'CMD=uploadphoto') + strlen("CMD=uploadphoto"));
                                }
                                if ($uploadphoto) {
                                    $foto_base64 =  substr($uploadphoto, strlen($uploadphoto) - $size,  $size);
                                    $foto = base64_encode($foto_base64);
                                }
                                //*
                                $environmentE =  (new ConfiguracionDatabase($org))->setBDD_Ex();
                                $connectionE = $environmentE->conexion;
                                $name_bdE = $environmentE->name;
                                $buscarMarcacion = $connectionE->table('marcaciones_biometrico')
                                    ->where('dni', $codigo_biometrico)
                                    ->where('organi_id',  $org)
                                    ->where('marcacion',  $fecha)
                                    ->first();
                                if (!empty($buscarMarcacion)) {
                                    $marcacion = marcaciones_biometrico::on($name_bdE)->find($buscarMarcacion->idmarcaciones_biometrico);
                                    if (!empty($foto)) {
                                        if (!empty($marcacion->foto)) {
                                            $ruta_imagen = app_path() . $marcacion->foto;
                                            // * VALIDACION SI EXISTE FOTO
                                            if (file_exists($ruta_imagen)) {
                                                unlink($ruta_imagen);
                                            }
                                        }
                                        $img_foto = fotos_asistencia($foto, $org, $fecha, $codigo_biometrico);
                                        $marcacion->foto = $img_foto;
                                    }
                                    $marcacion->save();
                                }
                                DB::disconnect($name_bdE);
                            }
                            break;
                        case "options":
                            $environmentE =  (new ConfiguracionDatabase($org))->setBDD_Ex();
                            $connectionE = $environmentE->conexion;
                            $name_bdE = $environmentE->name;
                            //*EXPLODE A TIPO PARA QVER MODELO
                            $tipo = explode(",", $content);
                            $modelo =  ltrim($tipo[0], '~DeviceName=');

                            //*ACTUALIZAR MODELO
                            $connectionE->table('biometrico_estado')->where('serie', $SN)
                                ->update(['modelo' => $modelo]);
                            DB::disconnect($name_bdE);
                            break;
                        case "rtstate":

                            break;
                        case 'rtlog':
                            $contenido = explode("\n", $content);
                            // *-----------  CONEXION BD-------------------------------
                            $environment =  (new ConfiguracionBDController($org))->setBDD();
                            $connection = $environment->conexion;
                            $name_bd = $environment->name;
                            //*BUSCAR DISPOSITIVO SI LO TENEMOS REGISTRADO
                            $dispo_id = $connection->table('dispositivos')
                                ->where('tipoDispositivo', '=', 3)
                                ->where('organi_id', '=', $org)
                                ->where('dispo_estadoActivo', 1)
                                ->where('dispo_codigo', '=', $SN)
                                ->value('idDispositivos');
                            DB::disconnect($name_bd);
                            if (!empty($dispo_id)) {
                                foreach ($contenido as $item) {
                                    //Array de tabulaciones
                                    $linea = explode("\t", $item);
                                    //verifico si el biometrico requiere que el identiicador lleve ceros delante
                                    // pin=0
                                    $pin = $linea[1];
                                    $pin = substr($pin, strpos($pin, 'pin=') + strlen("pin="));
                                    $pin = $tipoTexto == 1 ? $linea[0] : (is_numeric($pin) ? intval($pin) : $pin);

                                    if ($pin == 0) continue;
                                    $time = $linea[0];
                                    $marcacion = substr($time, strpos($time, 'time=') + strlen("time="));

                                    //armamos array data
                                    $array = [
                                        'dni' => $pin,
                                        'organi_id' => $org,
                                        'marcacion' => $marcacion,
                                        'idDispositivos' => $dispo_id,
                                        'serie' => $SN,
                                        'modo' => $tipoProceso == 2 ? 'C' : 'A',
                                        'fechaRegistro' => Carbon::now('America/Lima')->isoFormat("Y-MM-DD HH:mm:ss"),
                                        'estado' => 0,
                                        'zona_horaria' => $zona_horaria,
                                    ];
                                    create_dialing($name_bdE, $array);
                                }
                            }
                            break;
                    }
                }
                DB::disconnect($name_bd);
            }
            if ($puerto == 9004) {
                Log::info($new_version);
            }
            Log::info("inicio proceso de marcaciones");
            foreach ($new_version as $organi_id) {
                // *-----------  CONEXION BD EXTRACTOR-------------------------------
                $environmentE =  (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
                $connectionE = $environmentE->conexion;
                $name_bdE = $environmentE->name;
                // *---------------------FIN CONEXION EXTRACTOR -------------------------*
                $biometric = biometrico_estado::on($name_bdE)
                    ->select(
                        'serie',
                        'tipoProceso',
                        'text_type',
                        'modelo',
                        'zona_horaria',
                        'algoritmo_facial',
                        'algoritmo_huella'
                    )
                    ->where('organi_id', '=', $organi_id)
                    ->where('serie', '=',  $SN)
                    ->first();
                if (empty($biometric))  continue;
                $tipoProceso = $biometric->tipoProceso;
                $tipoTexto = $biometric->text_type;
                $modelo = $biometric->modelo;
                $zona_horaria = $biometric->zona_horaria;
                $algoritmo_facial = $biometric->algoritmo_facial;
                $algoritmo_fingerprint = $biometric->algoritmo_huella;
                if ($puerto == 9004) {
                    Log::info($organi_id . "ingresa al switch con modelo: " . $table);
                }
                switch ($table) {
                    case "ATTLOG":
                        // *-----------  CONEXION BD-------------------------------
                        $environment =  (new ConfiguracionBDController($organi_id))->setBDD();
                        $connection = $environment->conexion;
                        $name_bd = $environment->name;
                        //*BUSCAR DISPOSITIVO SI LO TENEMOS REGISTRADO
                        $dispo_id = $connection->table('dispositivos')
                            ->where('tipoDispositivo', '=', 3)
                            ->where('organi_id', '=', $organi_id)
                            ->where('dispo_estadoActivo', 1)
                            ->where('dispo_codigo', '=', $SN)
                            ->value('idDispositivos');
                        DB::disconnect($name_bd);
                        if (!empty($dispo_id)) {
                            //Obtenemos el array de saltos de linea
                            $contenido = explode("\n", $content);
                            foreach ($contenido as $item) {
                                //Array de tabulaciones
                                $linea = explode("\t", $item);
                                if (empty($linea[0])) continue;
                                //verifico si el biometrico requiere que el identiicador lleve ceros delante
                                $identificador_dispositivo = $tipoTexto == 1 ? $linea[0] : (is_numeric($linea[0]) ? intval($linea[0]) : $linea[0]);
                                //armamos array data
                                $array = [
                                    'dni' => $identificador_dispositivo,
                                    'organi_id' => $organi_id,
                                    'marcacion' => $linea[1],
                                    'idDispositivos' => $dispo_id,
                                    'serie' => $SN,
                                    'modo' => $tipoProceso == 2 ? 'C' : 'A',
                                    'fechaRegistro' => Carbon::now('America/Lima')->isoFormat("Y-MM-DD HH:mm:ss"),
                                    'estado' => 0,
                                    'zona_horaria' => $zona_horaria,
                                    'temperatura' => count($linea) > 8 ? $linea[8] : null
                                ];
                                create_dialing($name_bdE, $array);
                            }
                        }
                        break;
                    case "OPLOG":
                        break;
                    case "BIODATA":
                        $contenidoGeneral = explode("\n", $content);
                        $array_data = [];
                        foreach ($contenidoGeneral  as $item) {
                            $contenido = explode("\t", $item);
                            $array_data['fid'] = null;
                            foreach ($contenido as  $value) {
                                if (substr($value, 0, strlen("BIODATA Pin=")) == "BIODATA Pin=") {
                                    $pin = substr($value, strpos($value, 'BIODATA Pin=') + strlen("BIODATA Pin="));
                                    $array_data['pin'] =  $tipoTexto == 1 ? $pin : (is_numeric($pin) ? intval($pin) :  $pin);
                                    continue;
                                }
                                if (substr($value, 0, strlen("No=")) == "No=") {
                                    $No = substr($value, strpos($value, 'No=') + strlen("No="));
                                    $array_data['no'] = $No;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Index=")) == "Index=") {
                                    $iFaceIndex = substr($value, strpos($value, 'Index=') + strlen("Index="));
                                    $array_data['face_index'] = $iFaceIndex;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Valid=")) == "Valid=") {
                                    $valido = substr($value, strpos($value, 'Valid=') + strlen("Valid="));
                                    $array_data['valid'] = $valido;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Duress=")) == "Duress=") {
                                    $Duress = substr($value, strpos($value, 'Duress=') + strlen("Duress="));
                                    $array_data['duress'] = $Duress;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Type=")) == "Type=") {
                                    $Type = substr($value, strpos($value, 'Type=') + strlen("Type="));
                                    $array_data['type'] = $Type;
                                    continue;
                                }
                                if (substr($value, 0, strlen("MajorVer=")) == "MajorVer=") {
                                    $MajorVer = substr($value, strpos($value, 'MajorVer=') + strlen("MajorVer="));
                                    $array_data['major_version'] = $MajorVer;
                                    continue;
                                }
                                if (substr($value, 0, strlen("MinorVer=")) == "MinorVer=") {
                                    $MinorVer = substr($value, strpos($value, 'MinorVer=') + strlen("MinorVer="));
                                    $array_data['minor_version'] = $MinorVer;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Format=")) == "Format=") {
                                    $Format = substr($value, strpos($value, 'Format=') + strlen("Format="));
                                    $array_data['format'] = $Format;
                                    continue;
                                }
                                if (substr($value, 0, strlen("Tmp=")) == "Tmp=") {
                                    $tmp = substr($value, strpos($value, 'Tmp=') + strlen("Tmp="));
                                    $array_data['tmp'] = $tmp;
                                    $array_data['organi_id'] = $organi_id;
                                    $array_data['device_serial'] = $SN;
                                    $array_data['table_name'] = "BIODATA";
                                    $array_data['status'] = true;
                                    $array_main = [
                                        'organi_id' => $organi_id,
                                        'algorithm' => $algoritmo_facial,
                                        'biometric_data' => $array_data
                                    ];
                                    create_template_biometric($array_main);
                                    continue;
                                }
                            }
                        }

                        break;
                    case "OPERLOG":
                        $contenidoGeneral = explode("\t", $content);
                        //dESCRAGAR INFORMACION DEL HUELLA POR USUARIO
                        $opcion = explode(" ",  $contenidoGeneral[0]);
                        $array_data = [];
                        if (count($opcion) > 1 && !is_bool(strpos($opcion[1], 'PIN='))) {
                            $pin = substr($opcion[1], strpos($opcion[1], 'PIN=') + strlen("PIN="));
                            $array_data['pin'] =  $tipoTexto == 1 ? $pin : (is_numeric($pin) ? intval($pin) :  $pin);
                            $array_data['organi_id'] = $organi_id;
                            $array_data['device_serial'] = $SN;

                            switch ($opcion[0]) {
                                case 'FP':
                                    $array_data['type'] = 1;
                                    foreach ($contenidoGeneral as $item) {
                                        if (substr($item, 0, strlen("PIN=")) == "PIN=") {
                                            $pin = substr($item, strpos($item, 'PIN=') + strlen("PIN="));
                                            $array_data['pin'] = $pin;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("FID=")) == "FID=") {
                                            $fid = substr($item, strpos($item, 'FID=') + strlen("FID="));
                                            $array_data['fid'] = $fid;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("Size=")) == "Size=") {
                                            $size = substr($item, strpos($item, 'Size=') + strlen("Size="));
                                            $array_data['size'] = $size;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("Valid=")) == "Valid=") {
                                            $valid = substr($item, strpos($item, 'Valid=') + strlen("Valid="));
                                            $array_data['valid'] = $valid;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("TMP=")) == "TMP=") {
                                            $tmp = substr($item, strpos($item, 'TMP=') + strlen("TMP="));
                                            $array_data['tmp'] = $tmp;
                                            continue;
                                        }
                                    }
                                    $array_data['table_name'] = "FP";
                                    $array_data['status'] = true;
                                    $array_data['face_index'] = null;
                                    $array_main = [
                                        'organi_id' => $organi_id,
                                        'algorithm' => $algoritmo_fingerprint,
                                        'biometric_data' => $array_data
                                    ];
                                    create_template_biometric($array_main);
                                    break;
                                case 'USERPIC':
                                    $type = 2;
                                    foreach ($array_modelo as $value) {
                                        $pos = strpos($modelo, $value);
                                        if ($pos !== false) {
                                            $type = 9;
                                            break;
                                        }
                                    }
                                    //if ($type == 9) break;
                                    $array_data['type'] = $type;
                                    $array_data['fid'] = null;
                                    $array_data['face_index'] = null;
                                    foreach ($contenidoGeneral as $item) {
                                        if (substr($item, 0, strlen("PIN=")) == "PIN=") {
                                            $pin = substr($item, strpos($item, 'PIN=') + strlen("PIN="));
                                            $array_data['pin'] = $pin;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("FileName=")) == "FileName=") {
                                            $FileName = substr($item, strpos($item, 'FileName=') + strlen("FileName="));
                                            $array_data['file_name'] = $FileName;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("Size=")) == "Size=") {
                                            $size = substr($item, strpos($item, 'Size=') + strlen("Size="));
                                            $array_data['size'] = $size;
                                            continue;
                                        }

                                        if (substr($item, 0, strlen("Content=")) == "Content=") {
                                            $content = substr($item, strpos($item, 'Content=') + strlen("Content="));
                                            $array_data['content'] = $content;
                                            continue;
                                        }
                                    }
                                    $array_data['table_name'] = "USERPIC";
                                    $array_data['status'] = false;
                                    $array_main = [
                                        'organi_id' => $organi_id,
                                        'algorithm' => $algoritmo_facial,
                                        'biometric_data' => $array_data
                                    ];
                                    create_template_biometric($array_main);
                                    break;
                                case 'FACE':
                                    $array_data['type'] = 2;
                                    $array_data['device_serial'] = $SN;
                                    $array_data['table_name'] = "FACE";
                                    $array_data['face_index'] = null;
                                    //recorrido por cada uno de los datos enviados al momento de registrar un rostro
                                    foreach ($contenidoGeneral as $facial) {
                                        if (substr($facial, 0, strlen("PIN=")) == "PIN=") {
                                            $pin = substr($facial, strpos($facial, 'PIN=') + strlen("PIN="));
                                            $array_data['pin'] = $pin;
                                            continue;
                                        }
                                        if (substr($facial, 0, strlen("FID=")) == "FID=") {
                                            $fid = substr($facial, strpos($facial, 'FID=') + strlen("FID=")); //obtengo el numero de foto
                                            $array_data['fid'] = $fid;
                                            continue;
                                        }
                                        if (substr($facial, 0, strlen("SIZE=")) == "SIZE=") {
                                            $size = substr($facial, strpos($facial, 'SIZE=') + strlen("SIZE=")); //btengo el tamaño de la cadena
                                            $array_data['size'] = $size;
                                            continue;
                                        }
                                        if (substr($facial, 0, strlen("VALID=")) == "VALID=") {
                                            $valid = substr($facial, strpos($facial, 'VALID=') + strlen("VALID="));
                                            $array_data['valid'] = $valid;
                                            continue;
                                        }
                                        if (substr($facial, 0, strlen("TMP=")) == "TMP=") {
                                            $temp = substr($facial, strpos($facial, 'TMP=') + strlen("TMP=")); //obtengo el valor de la cadeda en base64
                                            $temp = explode("\n",  $temp); //separo la base 64 con el pin del usuario
                                            $array_data['tmp'] = $temp[0];

                                            $array_data['status'] =  $array_data['fid'] == 11 ? true : false;
                                            $array_main = [
                                                'organi_id' => $organi_id,
                                                'algorithm' => $algoritmo_facial,
                                                'biometric_data' => $array_data
                                            ];
                                            create_template_biometric($array_main);
                                        }
                                    }
                                    break;
                                case 'USER':
                                    $password = substr($contenidoGeneral[3], strpos($contenidoGeneral[3], 'Passwd=') + strlen("Passwd="));
                                    $array_data['content'] = $password;
                                    $array_data['type'] = 10;
                                    $array_data['fid'] = null;
                                    $array_data['face_index'] = null;
                                    if (!empty($password)) {
                                        $array_data['table_name'] = "USER_PASSWORD";
                                        $array_data['status'] = false;
                                        $array_main = [
                                            'organi_id' => $organi_id,
                                            'biometric_data' => $array_data
                                        ];
                                        create_template_biometric($array_main);
                                    }
                                    $tarjeta = substr($contenidoGeneral[4], strpos($contenidoGeneral[4], 'Card=') + strlen("Card="));
                                    $array_data['content'] = $tarjeta;
                                    $array_data['type'] = 11;

                                    if (!empty($tarjeta)) {
                                        $array_data['table_name'] = "USER_CARD";
                                        $array_data['status'] = true;
                                        $array_main = [
                                            'organi_id' => $organi_id,
                                            'biometric_data' => $array_data
                                        ];
                                        create_template_biometric($array_main);
                                    }
                                    break;
                                case "BIOPHOTO":
                                    if ($puerto == 9004) {
                                        Log::info($organi_id . "biofoto ");
                                    }
                                    foreach ($contenidoGeneral as $item) {
                                        if (substr($item, 0, strlen("PIN=")) == "PIN=") {
                                            $pin = substr($item, strpos($item, 'PIN=') + strlen("PIN="));
                                            $array_data['pin'] = $pin;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("FileName=")) == "FileName=") {
                                            $FileName = substr($item, strpos($item, 'FileName=') + strlen("FileName="));
                                            $array_data['file_name'] = $FileName;
                                            continue;
                                        }
                                        if (substr($item, 0, strlen("Type=")) == "Type=") {
                                            $type = substr($item, strpos($item, 'Type=') + strlen("Type="));
                                            $array_data['type'] = $type;
                                            continue;
                                        }

                                        if (substr($item, 0, strlen("Size=")) == "Size=") {
                                            $size = substr($item, strpos($item, 'Size=') + strlen("Size="));
                                            $array_data['size'] = $size;
                                            continue;
                                        }

                                        if (substr($item, 0, strlen("Content=")) == "Content=") {
                                            $content = substr($item, strpos($item, 'Content=') + strlen("Content="));
                                            $array_data['content'] = $content;
                                            continue;
                                        }
                                    }
                                    $array_data['table_name'] = "BIOPHOTO";
                                    $array_data['fid'] = null;
                                    $array_data['face_index'] = null;
                                    $array_data['status'] = false;
                                    $array_main = [
                                        'organi_id' => $organi_id,
                                        'algorithm' => $algoritmo_facial,
                                        'biometric_data' => $array_data
                                    ];
                                    create_template_biometric($array_main);
                                    break;
                            }
                        }
                        break;
                    case "ATTPHOTO":
                        $organi_almacFoto = organizacion::where('organi_id', $organi_id)
                            ->where('organi_almacFoto', 1)->exists();
                        if ($organi_almacFoto) {
                            $FileName = "";
                            $size = 0;
                            $SN = "";
                            $codigo_biometrico = 0;
                            $foto = "";
                            $fecha = "";
                            $datosObtenidos = explode("\n", $content, 4);
                            if (substr($datosObtenidos[0], 0, strlen("PIN=")) == "PIN=") {
                                $FileName = substr($datosObtenidos[0], strpos($datosObtenidos[0], 'PIN=') + strlen("PIN="));
                            }
                            if ($FileName) {
                                $empleado = explode("-", $FileName);
                                if (count($empleado) >= 2) {
                                    $marcacion = $empleado[0];
                                    $extraer_codigo = explode(".", $empleado[1])[0];
                                    if ($tipoTexto == 1) {
                                        $codigo_biometrico = $extraer_codigo;
                                    } else {
                                        $codigo_biometrico = is_numeric($extraer_codigo) ? intval($extraer_codigo) :  $extraer_codigo;
                                    }
                                    $fecha = Carbon::parse($marcacion)->isoFormat("YYYY-MM-DD HH:mm:ss");
                                }
                            }
                            if (substr($datosObtenidos[1], 0, strlen("SN=")) == "SN=") {
                                $SN = substr($datosObtenidos[1], strpos($datosObtenidos[1], 'SN=') + strlen("SN="));
                            }
                            if (substr($datosObtenidos[2], 0, strlen("size=")) == "size=") {
                                $size = substr($datosObtenidos[2], strpos($datosObtenidos[2], 'size=') + strlen("size="));
                            }
                            $uploadphoto = "";
                            if (substr($datosObtenidos[3], 0, strlen("CMD=uploadphoto")) == "CMD=uploadphoto") {
                                $uploadphoto = substr($datosObtenidos[3], strpos($datosObtenidos[3], 'CMD=uploadphoto') + strlen("CMD=uploadphoto"));
                            }
                            if ($uploadphoto) {
                                $foto_base64 =  substr($uploadphoto, strlen($uploadphoto) - $size,  $size);
                                $foto = base64_encode($foto_base64);
                            }

                            $searchDialing = $connectionE->table('marcaciones_biometrico')
                                ->where('dni', $codigo_biometrico)
                                ->where('organi_id',  $organi_id)
                                ->where('marcacion',  $fecha)
                                ->first();
                            if ($searchDialing) {
                                $marcacion = marcaciones_biometrico::on($name_bdE)->find($searchDialing->idmarcaciones_biometrico);
                                if (!empty($foto)) {
                                    if (!empty($marcacion->foto)) {
                                        $ruta_imagen = app_path() . $marcacion->foto;
                                        // * VALIDACION SI EXISTE FOTO
                                        if (file_exists($ruta_imagen)) {
                                            unlink($ruta_imagen);
                                        }
                                    }
                                    $img_foto = fotos_asistencia($foto, $organi_id, $fecha, $codigo_biometrico);
                                    $marcacion->update(['foto' => $img_foto]);
                                }
                            }
                        }
                        break;
                    case "options":
                        $environmentE =  (new ConfiguracionDatabase($organi_id))->setBDD_Ex();
                        $connectionE = $environmentE->conexion;
                        $name_bdE = $environmentE->name;
                        //*EXPLODE A TIPO PARA QVER MODELO
                        $tipo = explode(",", $content);
                        $modelo =  ltrim($tipo[0], '~DeviceName=');

                        //*ACTUALIZAR MODELO
                        $connectionE->table('biometrico_estado')->where('serie', $SN)
                            ->update(['modelo' => $modelo]);
                        DB::disconnect($name_bdE);
                        break;
                    case "rtstate":

                        break;
                    case 'rtlog':
                        $contenido = explode("\n", $content);
                        // *-----------  CONEXION BD-------------------------------
                        $environment =  (new ConfiguracionBDController($organi_id))->setBDD();
                        $connection = $environment->conexion;
                        $name_bd = $environment->name;
                        //*BUSCAR DISPOSITIVO SI LO TENEMOS REGISTRADO
                        $dispo_id = $connection->table('dispositivos')
                            ->where('tipoDispositivo', '=', 3)
                            ->where('organi_id', '=', $organi_id)
                            ->where('dispo_estadoActivo', 1)
                            ->where('dispo_codigo', '=', $SN)
                            ->value('idDispositivos');
                        DB::disconnect($name_bd);
                        if (!empty($dispo_id)) {
                            foreach ($contenido as $item) {
                                //Array de tabulaciones
                                $linea = explode("\t", $item);
                                //verifico si el biometrico requiere que el identiicador lleve ceros delante
                                // pin=0
                                $pin = $linea[1];
                                $pin = substr($pin, strpos($pin, 'pin=') + strlen("pin="));
                                $pin = $tipoTexto == 1 ? $linea[0] : (is_numeric($pin) ? intval($pin) : $pin);

                                if ($pin == 0) continue;
                                $time = $linea[0];
                                $marcacion = substr($time, strpos($time, 'time=') + strlen("time="));

                                //armamos array data
                                $array = [
                                    'dni' => $pin,
                                    'organi_id' => $organi_id,
                                    'marcacion' => $marcacion,
                                    'idDispositivos' => $dispo_id,
                                    'serie' => $SN,
                                    'modo' => $tipoProceso == 2 ? 'C' : 'A',
                                    'fechaRegistro' => Carbon::now('America/Lima')->isoFormat("Y-MM-DD HH:mm:ss"),
                                    'estado' => 0,
                                    'zona_horaria' => $zona_horaria,
                                ];
                                create_dialing($name_bdE, $array);
                            }
                        }
                        break;
                }
                DB::disconnect($name_bdE);
            }
            return 'OK';
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            Log::info('API cdataPost : ERROR');
            return 'error';
        }
    }
}
