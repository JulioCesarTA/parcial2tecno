<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'venta';
    public $timestamps = false;

    protected $fillable = [
        'codigo', 'cliente_id', 'vendedor_id', 'encomienda_id', 'estado', 'fecha_venta',
        'impuestos', 'subtotal', 'descuento', 'total_final', 'tipo_pago', 'numero_cuotas',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'impuestos' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total_final' => 'decimal:2',
    ];

    public function cliente()    { return $this->belongsTo(Usuario::class, 'cliente_id'); }
    public function vendedor()   { return $this->belongsTo(Usuario::class, 'vendedor_id'); }
    public function encomienda() { return $this->belongsTo(Encomienda::class, 'encomienda_id'); }
    public function pagos()      { return $this->hasMany(Pago::class, 'venta_id'); }
    public function facturas()   { return $this->hasMany(Factura::class, 'venta_id'); }

    public function totalPagado(): float
    {
        return (float) $this->pagos()->sum('monto');
    }
}
