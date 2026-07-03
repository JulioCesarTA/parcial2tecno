<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $table = 'visitas';
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'pagina', 'contador'];

    // Null = visita anónima (páginas públicas, antes de iniciar sesión).
    public function usuario() { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}
