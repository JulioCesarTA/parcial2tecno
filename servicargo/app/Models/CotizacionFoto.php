<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionFoto extends Model
{
    protected $table = 'cotizacion_foto';
    public $timestamps = false;

    protected $fillable = ['cotizacion_id', 'ruta'];

    public function cotizacion() { return $this->belongsTo(Cotizacion::class, 'cotizacion_id'); }
}
