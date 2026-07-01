<?php

namespace App\Http\Controllers;

use App\Servicios\Gestion\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventarioController extends Controller
{
    public function __construct(private InventarioService $inventario) {}

    public function index(Request $request)
    {
        return response()->json($this->inventario->listar($request->query('almacen_id')));
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

        $cantidadFinal = $this->inventario->registrarMovimiento($datos, $request->user()->id);

        return response()->json([
            'message' => "Movimiento {$datos['tipo']} registrado.",
            'cantidad_final' => $cantidadFinal,
        ]);
    }
}
