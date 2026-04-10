<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class organizacion_puerto extends Model
{
    protected $table = 'organizacion_puerto';
    protected $primaryKey = 'idorganizacion_puerto';
    protected $guarded  = ['idorganizacion_puerto'];
    public $timestamps = false;
}
