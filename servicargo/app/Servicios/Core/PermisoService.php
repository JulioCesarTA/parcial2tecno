<?php

namespace App\Servicios\Core;

use App\Models\Permiso;
use App\Models\Recurso;
use App\Support\BitacoraService;

/**
 * Requisitos 2 y 4 — Matriz de acceso (rol × recurso) (módulo core).
 */
class PermisoService
{
    /** Matriz completa para el dashboard de administración. */
    public function matriz(): array
    {
        return [
            'recursos' => Recurso::orderBy('orden')->get(),
            'roles' => ['admin', 'vendedor', 'cliente'],
            'permisos' => Permiso::all(),
        ];
    }

    /** Actualiza (o crea) una celda de la matriz. */
    public function actualizar(array $datos, int $actorId): Permiso
    {
        $permiso = Permiso::updateOrCreate(
            ['rol' => $datos['rol'], 'recurso_id' => $datos['recurso_id']],
            $datos
        );

        BitacoraService::registrar($actorId, 'accion', 'permisos', "Actualizar matriz {$datos['rol']}/{$datos['recurso_id']}");

        return $permiso;
    }
}
