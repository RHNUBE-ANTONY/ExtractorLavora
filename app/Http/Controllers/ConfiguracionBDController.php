<?php

namespace App\Http\Controllers;

use App\Models\conexion;
use App\Models\organizacion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;

class ConfiguracionBDController extends Controller
{
    // * Deaclaramos variable global $request
    public $request;

    public function __construct($request)
    {
        $this->request = $request;
    }


    // * Obtener la conexicion de la base datos
    public function setBDD()
    {
        // * Comprobar que no envia organizacion
        $id = $this->request;
        if (!empty($id)) {
            // * Obtener la conexion de organizacion
            $conexion = organizacion::where("organi_id", $id)->pluck("id_conexion")->first();
            if (!empty($conexion)) {
                // * datos de la conexion
                $conexion_organizacion = conexion::where('id', $conexion)->first();
                if (!empty($conexion_organizacion)) {
                    Config::set('database.connections.' . $conexion_organizacion->name_connection, [
                        "driver" => "mysql",
                        "host" => $conexion_organizacion->host,
                        "port" => $conexion_organizacion->port,
                        "database" => $conexion_organizacion->base,
                        "username" => $conexion_organizacion->username,
                        "password" => $conexion_organizacion->password,
                        "charset" => "utf8mb4",
                        "collation" => "utf8mb4_unicode_ci",
                        "prefix" => "",
                        "prefix_indexes" => true,
                        "strict" => false,
                        "engine" => null,
                        'options' => extension_loaded('pdo_mysql') ? array_filter([
                            PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                            PDO::ATTR_PERSISTENT => true,
                            PDO::ATTR_TIMEOUT => 3
                        ]) : [],
                    ]);
                    return (object)array("conexion" => DB::connection($conexion_organizacion->name_connection), "name" => $conexion_organizacion->name_connection);
                }
            }
        }
        $conexion_rhnube = conexion::where('base', 'rh_bd')->first();
        if ($conexion_rhnube) {
            Config::set('database.connections.' . $conexion_rhnube->name_connection, [
                "driver" => "mysql",
                "host" => $conexion_rhnube->host,
                "port" => $conexion_rhnube->port,
                "database" => $conexion_rhnube->base,
                "username" => $conexion_rhnube->username,
                "password" => $conexion_rhnube->password,
                "charset" => "utf8mb4",
                "collation" => "utf8mb4_unicode_ci",
                "prefix" => "",
                "prefix_indexes" => true,
                "strict" => false,
                "engine" => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_TIMEOUT => 3
                ]) : [],
            ]);
            return (object)array("conexion" => DB::connection($conexion_rhnube->name_connection), "name" => $conexion_rhnube->name_connection);
        }
    }
}
