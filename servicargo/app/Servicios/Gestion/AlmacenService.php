<?php

namespace App\Servicios\Gestion;

use App\Models\Almacen;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;

/**
 * CU03 — Gestión de Almacenes (módulo gestion).
 */
class AlmacenService
{
    public function listar(): Collection
    {
        return Almacen::with('responsable')->orderBy('nombre')->get();
    }

    public function obtener(int|string $id): Almacen
    {
        $a = Almacen::with('responsable')->find($id);
        if (! $a) {
            throw new ErrorDominio('Almacén no encontrado.', 404);
        }

        return $a;
    }

    public function crear(array $datos, int $actorId): Almacen
    {
        $a = Almacen::create($datos);
        BitacoraService::registrar($actorId, 'accion', 'almacenes', "Crear almacén #{$a->id}");

        return $a;
    }

    public function actualizar(int|string $id, array $datos, int $actorId): Almacen
    {
        $a = Almacen::find($id);
        if (! $a) {
            throw new ErrorDominio('Almacén no encontrado.', 404);
        }

        $a->update($datos);
        BitacoraService::registrar($actorId, 'accion', 'almacenes', "Editar almacén #{$a->id}");

        return $a;
    }

    public function eliminar(int|string $id, int $actorId): void
    {
        $a = Almacen::find($id);
        if (! $a) {
            throw new ErrorDominio('Almacén no encontrado.', 404);
        }

        $a->delete();
        BitacoraService::registrar($actorId, 'accion', 'almacenes', "Eliminar almacén #{$id}");
    }
}
