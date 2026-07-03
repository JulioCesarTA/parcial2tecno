<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permiso';
    public $timestamps = false;

    protected $fillable = ['rol', 'recurso_id', 'ver', 'crear', 'editar', 'eliminar'];

    protected $casts = [
        'ver' => 'boolean',
        'crear' => 'boolean',
        'editar' => 'boolean',
        'eliminar' => 'boolean',
    ];

    public function recurso() { return $this->belongsTo(Recurso::class, 'recurso_id'); }
    public function rolCatalogo() { return $this->belongsTo(Rol::class, 'rol', 'clave'); }
}
