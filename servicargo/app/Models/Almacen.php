<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacen extends Model
{
    use SoftDeletes;

    protected $table = 'almacen';
    public $timestamps = false;

    protected $fillable = ['nombre', 'direccion', 'capacidad', 'responsable_id'];

    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }
}
