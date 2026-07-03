<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catálogo de roles (admin | asesor | cliente). Clave = mismo valor que usuario.rol / permiso.rol. */
class Rol extends Model
{
    protected $table = 'rol';
    protected $primaryKey = 'clave';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['clave', 'nombre', 'orden'];

    public function usuarios() { return $this->hasMany(Usuario::class, 'rol', 'clave'); }
    public function permisos() { return $this->hasMany(Permiso::class, 'rol', 'clave'); }
}
