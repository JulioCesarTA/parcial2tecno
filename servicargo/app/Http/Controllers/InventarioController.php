<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventarioController extends Controller
{
    public function index(Request $request)
    {
        $q = Inventario::with(['producto', 'almacen']);
        if ($request->filled('almacen_id') && $request->query('almacen_id') !== '*') {
            $q->where('almacen_id', $request->query('almacen_id'));
        }

        return response()->json($q->get());
    }

    // Registrar movimiento INGRESO / SALIDA
    public function movimiento(Request $request)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:producto,id'],
            'almacen_id' => ['required', 'integer', 'exists:almacen,id'],
            'cantidad' => ['required', 'integer', 'gt:0'],
            'tipo' => ['required', Rule::in(['INGRESO', 'SALIDA'])],
        ]);

        $resultado = DB::transaction(function () use ($datos) {
            $inv = Inventario::where('producto_id', $datos['producto_id'])
                ->where('almacen_id', $datos['almacen_id'])
                ->lockForUpdate()
                ->first();

            if (! $inv) {
                if ($datos['tipo'] === 'SALIDA') {
                    throw new \RuntimeException('No hay stock registrado para este producto en el almacén.');
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
                    throw new \RuntimeException('Stock insuficiente para la salida solicitada.');
                }
            }

            Inventario::where('producto_id', $datos['producto_id'])
                ->where('almacen_id', $datos['almacen_id'])
                ->update(['cantidad' => $nueva]);

            return $nueva;
        });

        BitacoraService::registrar(
            $request->user()->id,
            'accion',
            'inventario',
            "{$datos['tipo']} {$datos['cantidad']} (prod {$datos['producto_id']}, alm {$datos['almacen_id']})",
            $request
        );

        return response()->json([
            'message' => "Movimiento {$datos['tipo']} registrado.",
            'cantidad_final' => $resultado,
        ]);
    }
}
