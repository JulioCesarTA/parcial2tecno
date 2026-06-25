<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialEstado extends Model
{
    protected $table = 'historial_estado';
    public $timestamps = false;

    protected $fillable = [
        'encomienda_id', 'estado_anterior', 'estado_nuevo', 'fecha_cambio', 'observaciones',
    ];

    protected $casts = ['fecha_cambio' => 'datetime'];
}
