<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wilayah extends Model
{
    protected $table = 'wilayah';
    protected $fillable = ['name', 'province', 'latitude', 'longitude'];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];
}
