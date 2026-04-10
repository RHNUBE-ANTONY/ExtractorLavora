<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class jobs_logs extends Model
{
       protected $table = 'jobs_logs';
    protected $guarded = ['id']; 
    public $timestamps = false;
}
