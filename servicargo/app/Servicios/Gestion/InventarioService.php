<?php

namespace App\Servicios\Gestion;

use App\Models\Inventario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU04 — Gestión de Inventario (módulo gestion).
 * Registra movimientos INGRESO/SALIDA sobre el stock por producto+almacén.
 */
class InventarioService
{
    /** Lista inventario, opcionalmente filtrado por almacén ('*' = todos). */
    public function listar(?string $almacenId = null): Collection
    {
        $q = Inventario::with(['producto', 'almacen']);
        if ($almacenId !== null && $almacenId !== '' && $almacenId !== '*') {
            $q->where('almacen_id', $almacenId);
        }

        return $q->get();
    }

    /**
     * Registra un movimiento de inventario y devuelve la cantidad final.
     * Reglas: no se puede dar SALIDA sin stock, ni dejar el stock en negativo.
     */
    public function registrarMovimiento(array $datos, int $actorId): int
    {
        $cantidadFinal = DB::transaction(function () use ($datos) {
            $inv = Inventario::where('producto_id', $datos['producto_id'])
                ->where('almacen_id', $datos['almacen_id'])
                ->lockForUpdate()
                ->first();

            if (! $inv) {
                if ($datos['tipo'] === 'SALIDA') {
                    throw new ErrorDominio('No hay stock registrado para este producto en el almacén.', 422);
                }
                $inv = Inventario::create([
                    'producto_id' => $datos['producto_id'],
                    'almacen_id' => $datos['almacen_id'],
                    'cantidad' => $datos['cantidad'],
                    'stock_minimo' => 0,
                ]);

                return $inv->cantidad;
            }

            if ($datos['tipo'] === 'INGRESO') {
                $nueva = $inv->cantidad + $datos['cantidad'];
            } else {
                $nueva = $inv->cantidad - $datos['cantidad'];
                if ($nueva < 0) {
                    throw new ErrorDominio('Stock insuficiente para la salida solicitada.', 422);
                }
            }

            Inventario::where('producto_id', $datos['producto_id'])
                ->where('almacen_id', $datos['almacen_id'])
                ->update(['cantidad' => $nueva]);

            return $nueva;
        });

        BitacoraService::registrar(
            $actorId,
            'accion',
            'inventario',
            "{$datos['tipo']} {$datos['cantidad']} (prod {$datos['producto_id']}, alm {$datos['almacen_id']})"
        );

        return $cantidadFinal;
    }
}
