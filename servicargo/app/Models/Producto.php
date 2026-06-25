<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'producto';
    public $timestamps = false;

    protected $fillable = [
        'categoria_id', 'codigo', 'nombre', 'descripcion', 'precio_unitario', 'tipo',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
