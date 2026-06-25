<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    protected $table = 'reporte';
    public $timestamps = false;

    protected $fillable = ['propietario_id', 'fecha_generacion', 'parametros', 'tipo_reporte'];

    protected $casts = ['fecha_generacion' => 'datetime'];
}
