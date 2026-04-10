<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class plantilla_empleadobio extends Model
{
    protected $table = 'plantilla_empleadobio';
    protected $primaryKey = 'id';
    protected $fillable = [
        'idempleado',
        'posicion_huella',
        'tipo_registro',
        'path',
        'fileName',
        'iFlag',
        'iFaceIndex',
        'iLength',
        'estado',
        'organi_id',
        'idDispositivos',
        'orden',
        'creado',
        'baja',
        'MajorVer',
        'MinorVer'
    ];
    public $timestamps = false;
    //relacion uno  auno polimorfica
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}
