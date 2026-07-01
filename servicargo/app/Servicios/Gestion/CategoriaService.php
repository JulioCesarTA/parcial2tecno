<?php

namespace App\Servicios\Gestion;

use App\Models\Categoria;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;

/**
 * CU02 — Categorías de mercancía (módulo gestion).
 */
class CategoriaService
{
    public function listar(): Collection
    {
        return Categoria::orderBy('nombre')->get();
    }

    public function obtener(int|string $id): Categoria
    {
        $c = Categoria::find($id);
        if (! $c) {
            throw new ErrorDominio('Categoría no encontrada.', 404);
        }

        return $c;
    }

    public function crear(array $datos, int $actorId): Categoria
    {
        $c = Categoria::create($datos);
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Crear categoría #{$c->id}");

        return $c;
    }

    public function actualizar(int|string $id, array $datos, int $actorId): Categoria
    {
        $c = $this->obtener($id);
        $c->update($datos);
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Editar categoría #{$c->id}");

        return $c;
    }

    public function eliminar(int|string $id, int $actorId): void
    {
        $c = $this->obtener($id);
        $c->delete(); // soft delete
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Eliminar categoría #{$id}");
    }
}
