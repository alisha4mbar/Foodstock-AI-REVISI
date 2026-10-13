<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Konsumsi extends Model
{
    protected $table = 'konsumsi';
    protected $fillable = ['wilayah_id', 'komoditas_id', 'quantity', 'recorded_at', 'source', 'recorded_by'];
    protected $casts = ['quantity' => 'float', 'recorded_at' => 'date:Y-m-d'];
}
