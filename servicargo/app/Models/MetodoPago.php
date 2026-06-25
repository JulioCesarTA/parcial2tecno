<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodo_pago';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'tipo', 'alias', 'referencia', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function usuario() { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}
