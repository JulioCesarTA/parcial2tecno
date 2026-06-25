<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'factura';
    public $timestamps = false;

    protected $fillable = [
        'venta_id', 'estado', 'fecha_emision', 'impuestos', 'numero_factura',
        'subtotal', 'total', 'metodo_pago', 'numero_cuota',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'impuestos' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function venta() { return $this->belongsTo(Venta::class, 'venta_id'); }
}
