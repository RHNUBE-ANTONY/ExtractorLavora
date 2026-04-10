<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class biometrico_estado extends Model
{
    protected $table = 'biometrico_estado';
    protected $primaryKey = 'id';
    protected $guarded = ['id'];
    public $timestamps = false;
}
