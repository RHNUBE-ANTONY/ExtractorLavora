<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class conexion extends Model
{
    protected $table = 'conexion';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id',
        'host',
        'port',
        'base',
        'username',
        'password',
        'name_connection'
    ];
    public $timestamps = false;
}
