<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class organizacion extends Model
{
    protected $table = 'organizacion';
    protected $primaryKey = 'organi_id';
    protected $guarded = ['organi_id'];
}
