<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Encomienda extends Model
{
    protected $table = 'encomienda';
    public $timestamps = false;

    protected $fillable = [
        'cliente_id', 'cotizacion_id', 'guia_rastreo', 'remitente', 'destinatario',
        'contenido', 'origen', 'destino', 'tipo_envio', 'peso_kg', 'volumen_m3',
        'estado', 'fecha_registro', 'fecha_entrega_estimada',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'fecha_entrega_estimada' => 'datetime',
    ];

    public function cliente()    { return $this->belongsTo(Usuario::class, 'cliente_id'); }
    public function cotizacion() { return $this->belongsTo(Cotizacion::class, 'cotizacion_id'); }
    public function historial()  { return $this->hasMany(HistorialEstado::class, 'encomienda_id'); }
}
