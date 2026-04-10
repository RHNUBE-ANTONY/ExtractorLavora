<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class marcaciones_biometrico extends Model
{
    protected $table = 'marcaciones_biometrico';
    protected $primaryKey = 'idmarcaciones_biometrico';
    protected $guarded = ['idmarcaciones_biometrico'];

    public $timestamps = false;
}
