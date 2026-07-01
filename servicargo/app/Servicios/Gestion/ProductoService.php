<?php

namespace App\Servicios\Gestion;

use App\Models\Producto;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;

/**
 * CU02 — Productos / tipos de mercancía (módulo gestion).
 */
class ProductoService
{
    /** Lista productos, opcionalmente filtrados por categoría ('*' = todas). */
    public function listar(?string $categoriaId = null): Collection
    {
        $q = Producto::with('categoria');
        if ($categoriaId !== null && $categoriaId !== '' && $categoriaId !== '*') {
            $q->where('categoria_id', $categoriaId);
        }

        return $q->orderBy('nombre')->get();
    }

    public function obtenerPorCodigo(string $codigo): Producto
    {
        $p = Producto::with('categoria')->where('codigo', $codigo)->first();
        if (! $p) {
            throw new ErrorDominio('Producto no encontrado.', 404);
        }

        return $p;
    }

    public function crear(array $datos, int $actorId): Producto
    {
        $p = Producto::create($datos);
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Crear producto {$p->codigo}");

        return $p;
    }

    public function actualizarPorCodigo(string $codigo, array $datos, int $actorId): Producto
    {
        $p = $this->obtenerPorCodigo($codigo);
        $p->update($datos);
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Editar producto {$p->codigo}");

        return $p;
    }

    public function eliminarPorCodigo(string $codigo, int $actorId): void
    {
        $p = $this->obtenerPorCodigo($codigo);
        $p->delete(); // soft delete
        BitacoraService::registrar($actorId, 'accion', 'catalogo', "Eliminar producto {$codigo}");
    }
}
