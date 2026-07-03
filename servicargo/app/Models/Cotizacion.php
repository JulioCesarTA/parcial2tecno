<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizacion';
    public $timestamps = false;

    protected $fillable = [
        'cliente_id', 'vendedor_id', 'estado', 'fecha_emision', 'remitente', 'destinatario',
        'contenido', 'origen', 'destino', 'tipo_envio', 'peso_kg', 'volumen_m3',
        'fecha_entrega_estimada', 'impuestos', 'subtotal', 'total_estimado', 'validez_dias',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_entrega_estimada' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'impuestos' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_estimado' => 'decimal:2',
    ];

    public function cliente()    { return $this->belongsTo(Usuario::class, 'cliente_id'); }
    public function vendedor()   { return $this->belongsTo(Usuario::class, 'vendedor_id'); }
    public function detalles()   { return $this->hasMany(DetalleCotizacion::class, 'cotizacion_id'); }
    public function encomienda() { return $this->hasOne(Encomienda::class, 'cotizacion_id'); }

    public function estaVencida(): bool
    {
        return $this->estado === 'PENDIENTE'
            && $this->fecha_emision->copy()->addDays((int) $this->validez_dias)->isPast();
    }
}
