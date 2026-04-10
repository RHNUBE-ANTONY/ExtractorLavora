<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class tareas_programadas extends Model
{
    protected $table = 'tareas_programadas';
    protected $primaryKey = 'id';
    protected $fillable = ['id'];
    public $timestamps = false;
}
