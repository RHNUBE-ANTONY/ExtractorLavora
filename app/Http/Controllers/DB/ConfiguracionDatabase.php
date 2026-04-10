<?php

namespace App\Http\Controllers\DB;

use App\Http\Controllers\Controller;
use App\Models\conexion_extractor;
use App\Models\organizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionDatabase extends Controller
{
    private  $organi_id;

    public function __construct($organi_id)
    {
        $this->organi_id = $organi_id;
    }

    public function setBDD_Ex()
    {
        // * Comprobar que no envia organizacion
        $organi_id = $this->organi_id ?? 'extractor';
        $connection_name = "mysql_{$organi_id}_ex";

        $conexion =  $this->organi_id ? organizacion::where('organi_id', $organi_id)->value('id_conexion_extractor') : null;
        $name_bd = $this->organi_id ? conexion_extractor::where('id', $conexion)
            ->value('base') : 'rh_bd_extractor';
        config(["database.connections.{$connection_name}" => array_merge(
            config('database.connections.mysql'),
            ['database' => $name_bd]
        )]);

        return (object)array("conexion" => DB::connection($connection_name), "name" => $connection_name);
    }
}
