<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    protected $table = 'recurso';
    public $timestamps = false;

    protected $fillable = ['clave', 'nombre', 'icono', 'ruta', 'orden'];

    public function permisos() { return $this->hasMany(Permiso::class, 'recurso_id'); }
}
