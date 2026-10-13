<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komoditas extends Model
{
    protected $table = 'komoditas';
    protected $fillable = ['name', 'unit', 'category', 'safe_stock'];
    protected $casts = ['safe_stock' => 'float'];
}
