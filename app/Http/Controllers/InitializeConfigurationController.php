<?php

namespace App\Http\Controllers;

use App\Models\biometrico_estado;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InitializeConfigurationController extends Controller
{
    public function initialize_configuration(Request $request)
    {
        $response = 'error';
        try {
            // Lectura de parametros de entrada
            $SN = $request->SN;
            $options = $request->options;
            $languaje = $request->languaje;
            $pushver = $request->pushver;
            $pushcommkey = $request->pushcommkey;
            $timezone = "-5";
            $session_id = null;
            $registry_code = null;
            $puerto = $_SERVER['SERVER_PORT'];
            // Filtramos el puerto en la tabla 'organizacion_puerto' para obtener las organizaciones con las cuales trabaja el dispositivo biometrico
            $organizaciones_id = ObtenerOrganiIdXPuerto($puerto);
            $estado = true;
            if (!empty($organizaciones_id)) {
                foreach ($organizaciones_id as $organi_id) {
                    $enviromentEX = ObtenerConexionBDExtractor($organi_id);
                    $connection = $enviromentEX->setBDD_Ex()->conexion;
                    $dispositivo = $connection->table('biometrico_estado')
                        ->select('timezone', 'session_id', 'registry_code')
                        ->where('organi_id', '=', $organi_id)
                        ->where('serie', '=', $SN)
                        ->first();
                    if (!empty($dispositivo)) {
                        $timezone = $dispositivo->timezone;
                        $session_id = $dispositivo->session_id;
                        $registry_code = $dispositivo->registry_code;
                        $estado = false;
                        DB::disconnect($enviromentEX->setBDD_Ex()->name);
                        break;
                    }
                    DB::disconnect($enviromentEX->setBDD_Ex()->name);
                }
                if ($estado) {
                    return "OK";
                }
                if (!empty($session_id) && !empty($registry_code)) {
                    return response(
                        "Registry=OK" . "\n" .
                            "RegistryCode={$registry_code}" . "\n" .
                            "ServerVersion=3.0.1" . "\n" .
                            "ServerName=ADMS" . "\n" .
                            "PushProtVer={$pushver}" . "\n" .
                            "ErrorDelay=60" . "\n" .
                            "RequestDelay=30" . "\n" .
                            "TransTimes=00:00;14:05" . "\n" .
                            "TransInterval=2" . "\n" .
                            "TransTables=User Transaction" . "\n" .
                            "Realtime=1" . "\n" .
                            "SessionID={$session_id}" . "\n" .
                            "TimeoutSec=10",
                        200
                    )->header('Content-Type', 'text/plain; charset=utf-8')
                        ->header('Set-Cookie', "session={$session_id};");
                } else {
                    $response = 'GET OPTION FROM: ' . $SN . "\n" .
                        'Stamp=0' . "\n" .
                        'OpStamp=0' . "\n" .
                        'PhotoStamp=0' . "\n" .
                        'ErrorDeLay=300' . "\n" .
                        'DeLay=60' . "\n" .
                        'ServerVer= ' . $pushver . "\n" .
                        'PushProtVer=2.4.1' . "\n" .
                        'EncryptFlag=1000000000' . "\n" .
                        'PushOptionsFlag=1' . "\n" .
                        'SupportPing=1' . "\n" .
                        'PushOptions=UserCount,TransactionCount,FingerFunOn,FPVersion,FPCount,FaceFunOn,FaceVersion,FaceCount,FvFunOn,FvVersion,FvCount,PvFunOn,PvVersion,PvCount,BioPhotoFun,BioDataFun,PhotoFunOn,LockFunOn' . "\n" .
                        'TransTimes=00:00;14:05' . "\n" .
                        'TransInterval=1' . "\n" .
                        'TransFlag=TransData AttLog	OpLog	AttPhoto	EnrollFP	EnrollUser	FPImag	ChgUser	ChgFP	FACE	UserPic	FVEIN	BioPhoto' . "\n" .
                        'TimeZone=' . $timezone . "\n" .
                        'Realtime=1' . "\n" .
                        'Encrypt=0';
                }
            } else {
                create_list_organizacion($puerto, $SN);
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            Log::channel("daily")->error("apiCdataController - ComunicacionInicial", ["message" => $e->getMessage()]);
        }
        return $response;
    }
    public function registry(Request $request)
    {
        $content = $request->getContent();
        $partes = explode(',', $content); // Se separa el contenido en partes
        //LockCount
        $LockCount = explode('=', $partes[6])[1];
        //        $AccSupportFunList=
        $AccSupportFunList = explode('=', $partes[51])[1];
        $serie = $request->SN;
        $puerto = $_SERVER['SERVER_PORT'];
        //00000000000000e0-000011b4-00000067-0de033b344051e32-5115b5d6
        $session_first = Str::random(16);
        $session_second = Str::random(8);
        $session_third = Str::random(8);
        $session_fourth = Str::random(16);
        $session_fivth = Str::random(8);
        $sessionId = "{$session_first}-{$session_second}-{$session_third}-{$session_fourth}-{$session_fivth}";

        $registryCode = Str::random(10);
        $qr_code_key = MD5("{$registryCode}{$serie}{$sessionId}");
        $organizaciones_id = ObtenerOrganiIdXPuerto($puerto);
        if (!empty($organizaciones_id)) {
            foreach ($organizaciones_id as $organi_id) {
                $enviromentEX = ObtenerConexionBDExtractor($organi_id);
                $connection = $enviromentEX->setBDD_Ex()->conexion;
                $name_bd = $enviromentEX->setBDD_Ex()->name;
                $dispositivo = $connection->table('biometrico_estado')
                    ->select('registry_code', 'session_id')
                    ->where('organi_id', '=', $organi_id)
                    ->where('serie', '=', $serie)
                    ->first();
                if (empty($dispositivo)) {
                    $detalle_biometrico = new  biometrico_estado();
                    $detalle_biometrico->setConnection($name_bd);
                    $detalle_biometrico->serie = $serie;
                    $detalle_biometrico->organi_id = $organi_id;
                    $detalle_biometrico->tipo_dispositivo = 3;
                    $detalle_biometrico->session_id =  $sessionId;
                    $detalle_biometrico->registry_code = $registryCode;
                    $detalle_biometrico->qr_code_key = $qr_code_key;
                    $detalle_biometrico->n_puertas = $LockCount;
                    $detalle_biometrico->tipoProceso = 3;
                    //$detalle_biometrico->acc_support_fun_list = $AccSupportFunList;
                    $detalle_biometrico->ultima_conexion = Carbon::now('America/Lima')->isoFormat('YYYY-MM-DD HH:mm:ss');
                    $detalle_biometrico->save();
                } else {
                    $registryCode = $dispositivo->registry_code;
                    $sessionId = $dispositivo->session_id;
                }
                DB::disconnect($name_bd);
                break;
            }
        }
        return response("RegistryCode={$registryCode}", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Set-Cookie', "session={$sessionId};");
    }
    public function push(Request $request)
    {
        $cookieHeader = $request->header('cookie');

        $cookieParts = explode(',', $cookieHeader);
        $token = null;
        $timestamp = null;
        foreach ($cookieParts as $part) {
            $kv = explode('=', $part);
            if (count($kv) == 2) {
                if (trim($kv[0]) === 'token') {
                    $token = trim($kv[1]);
                } elseif (trim($kv[0]) === 'timestamp') {
                    $timestamp = trim($kv[1]);
                }
            }
        }

        $puerto = $_SERVER['SERVER_PORT'];
        $SN = $request->SN;
        $session_id = null;
        $registry_code = null;
        $qr_code_key = null;
        // Filtramos el puerto en la tabla 'organizacion_puerto' para obtener las organizaciones con las cuales trabaja el dispositivo biometrico
        $organizaciones_id = ObtenerOrganiIdXPuerto($puerto);
        if (!empty($organizaciones_id)) {
            foreach ($organizaciones_id as $organi_id) {
                $enviromentEX = ObtenerConexionBDExtractor($organi_id);
                $connection = $enviromentEX->setBDD_Ex()->conexion;
                $dispositivo = $connection->table('biometrico_estado')
                    ->select('timezone', 'session_id', 'registry_code', 'qr_code_key')
                    ->where('organi_id', '=', $organi_id)
                    ->where('serie', '=', $SN)
                    ->first();
                if (!empty($dispositivo)) {
                    $session_id = $dispositivo->session_id;
                    $registry_code = $dispositivo->registry_code;
                    $qr_code_key = $dispositivo->qr_code_key;
                    DB::disconnect($enviromentEX->setBDD_Ex()->name);
                    break;
                }
                DB::disconnect($enviromentEX->setBDD_Ex()->name);
            }
        }

        $expectedToken = md5($registry_code . $SN . $timestamp);

        /* if ($token !== $expectedToken) {
        Log::warning($expectedToken);
        return response("Invalid token", 403);
    } */

        $content = "ServerVersion=3.0.1" . "\n" .
            "ServerName=ADMS" . "\n" .
            "PushVersion=3.0.1" . "\n" .
            "ErrorDelay=60" . "\n" .
            "RequestDelay=2" . "\n" .
            "TransTimes=00:00;14:00" . "\n" .
            "TransInterval=1" . "\n" .
            "TransTables=User" . "\t" .    "Transaction" . "\n" .
            "Realtime=1" . "\n" .
            "SessionID={$session_id}" . "\n" .
            "TimeoutSec=10" . "\n" .
            "QRCodeDecryptType=2" . "\n" .
            "QRCodeDecryptKey={$qr_code_key}";

        return    response($content, 200)->header('Content-Type', 'text/plain; charset=utf-8;')
            ->header('Set-Cookie', "session={$session_id}; Path=/; HttpOnly;");;
    }
}
