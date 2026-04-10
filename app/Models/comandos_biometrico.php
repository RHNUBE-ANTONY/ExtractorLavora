<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class comandos_biometrico extends Model
{
    protected $table = 'comandos_biometrico';
    protected $primaryKey = 'idcomandos_biometrico';
    protected $guarded = ['idcomandos_biometrico'];
    public $timestamps = false;
}
