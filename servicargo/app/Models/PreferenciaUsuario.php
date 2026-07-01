<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenciaUsuario extends Model
{
    protected $table = 'preferencia_usuario';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'tema', 'fuente', 'contraste'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
