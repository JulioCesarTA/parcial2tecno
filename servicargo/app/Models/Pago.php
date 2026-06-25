<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table = 'pago';
    public $timestamps = false;

    protected $fillable = [
        'venta_id', 'estado', 'fecha_pago', 'metodo_pago', 'monto', 'numero_cuota', 'referencia',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function venta()   { return $this->belongsTo(Venta::class, 'venta_id'); }
    public function factura() { return $this->hasOne(Factura::class, 'venta_id', 'venta_id'); }
}
