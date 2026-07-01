<?php

namespace App\Servicios\Core;

use App\Models\Usuario;
use App\Support\BitacoraService;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación por sesión (módulo core).
 * Solo verifica credenciales y crea clientes; el manejo de la sesión (Auth::login)
 * queda en el controlador, que es la capa HTTP.
 */
class AuthService
{
    /** Devuelve el usuario si las credenciales son válidas, o null. */
    public function verificar(string $correo, string $contrasena): ?Usuario
    {
        $usuario = Usuario::where('correo', $correo)->first();
        if (! $usuario || ! Hash::check($contrasena, $usuario->contrasena)) {
            return null;
        }

        return $usuario;
    }

    /** Registra un nuevo cliente (auto-registro público). */
    public function registrarCliente(array $datos): Usuario
    {
        $datos['rol'] = 'cliente';
        $datos['contrasena'] = Hash::make($datos['contrasena']);
        $usuario = Usuario::create($datos);

        BitacoraService::registrar($usuario->id, 'accion', 'usuarios', 'Auto-registro de cliente');

        return $usuario;
    }
}
