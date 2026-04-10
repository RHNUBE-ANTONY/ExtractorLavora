<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['imageable_id', 'imageable_type', 'url'];
    public $timestamps = false;
    //el metodo lleva el mismo nombre que el campo de la bd
    public function imageable()
    {
        return $this->morphTo();
    }
}
