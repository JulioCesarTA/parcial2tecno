<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    protected $table = 'usuario';
    public $timestamps = false;

    protected $fillable = [
        'ci', 'nombre', 'apellido', 'correo', 'contrasena', 'rol', 'telefono',
    ];

    protected $hidden = ['contrasena'];

    // La contraseña vive en la columna 'contrasena' (no 'password')
    public function getAuthPassword(): string
    {
        return $this->contrasena;
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    // La tabla usuario no tiene columna remember_token
    public function getRememberTokenName(): string
    {
        return '';
    }

    // Normaliza sinónimos de rol a la forma canónica
    public static function normalizarRol(string $rol): string
    {
        $r = strtolower(trim($rol));
        return match ($r) {
            'admin', 'administrador', 'propietario' => 'admin',
            'vendedor', 'operador' => 'vendedor',
            'cliente' => 'cliente',
            default => $r,
        };
    }

    public function esAdmin(): bool   { return $this->rol === 'admin'; }
    public function esVendedor(): bool { return $this->rol === 'vendedor'; }
    public function esCliente(): bool  { return $this->rol === 'cliente'; }

    public function cotizacionesComoCliente()
    {
        return $this->hasMany(Cotizacion::class, 'cliente_id');
    }
}
