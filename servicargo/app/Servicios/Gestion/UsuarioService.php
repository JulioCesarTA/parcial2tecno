<?php

namespace App\Servicios\Gestion;

use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * CU01 — Gestión de Usuarios (módulo gestion).
 * Capa de negocio: reglas, normalización de rol y hashing. Sin HTTP.
 */
class UsuarioService
{
    /** Lista usuarios, opcionalmente filtrados por rol ('*' = todos). */
    public function listar(?string $rol = null): Collection
    {
        $q = Usuario::query();
        if ($rol && $rol !== '*') {
            $q->where('rol', Usuario::normalizarRol($rol));
        }

        return $q->orderBy('id')->get();
    }

    /** Obtiene un usuario por id o lanza 404. */
    public function obtener(int|string $id): Usuario
    {
        $usuario = Usuario::find($id);
        if (! $usuario) {
            throw new ErrorDominio('Usuario no encontrado.', 404);
        }

        return $usuario;
    }

    /** Crea un usuario (rol normalizado, contraseña hasheada). */
    public function crear(array $datos, int $actorId): Usuario
    {
        $datos['rol'] = Usuario::normalizarRol($datos['rol']);
        $datos['contrasena'] = Hash::make($datos['contrasena']);
        $usuario = Usuario::create($datos);

        BitacoraService::registrar($actorId, 'accion', 'usuarios', "Crear usuario #{$usuario->id}");

        return $usuario;
    }

    /** Actualiza un usuario por id. */
    public function actualizar(int|string $id, array $datos, int $actorId): Usuario
    {
        $usuario = $this->obtener($id);

        if (isset($datos['contrasena'])) {
            $datos['contrasena'] = Hash::make($datos['contrasena']);
        }
        if (isset($datos['rol'])) {
            $datos['rol'] = Usuario::normalizarRol($datos['rol']);
        }

        $usuario->update($datos);
        BitacoraService::registrar($actorId, 'accion', 'usuarios', "Editar usuario #{$usuario->id}");

        return $usuario;
    }

    /** Elimina un usuario identificado por su CI (según especificación). */
    public function eliminarPorCi(string $ci, int $actorId): void
    {
        $usuario = Usuario::where('ci', $ci)->first();
        if (! $usuario) {
            throw new ErrorDominio('Usuario no encontrado.', 404);
        }

        $usuario->delete();
        BitacoraService::registrar($actorId, 'accion', 'usuarios', "Eliminar usuario CI {$ci}");
    }
}
