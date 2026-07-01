<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\VentaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VentaController extends Controller
{
    public function __construct(private VentaService $ventas) {}

    public function index(Request $request)
    {
        return response()->json($this->ventas->listarPara($request->user()));
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->ventas->obtenerPara($request->user(), $id));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:usuario,id'],
            'vendedor_id' => ['required', 'integer', 'exists:usuario,id'],
            'encomienda_id' => ['required', 'integer', 'exists:encomienda,id'],
            'fecha_venta' => ['nullable', 'date'],
            'impuestos' => ['nullable', 'numeric', 'gte:0'],
            'descuento' => ['nullable', 'numeric', 'gte:0'],
            'tipo_pago' => ['required', Rule::in(['CONTADO', 'CREDITO'])],
            'numero_cuotas' => ['nullable', 'integer'],
        ]);

        $venta = $this->ventas->crear($datos, $request->user());

        return response()->json([
            'message' => 'Nota de venta creada.',
            'id' => $venta->id,
            'codigo' => $venta->codigo,
            'subtotal' => $venta->subtotal,
            'total_final' => $venta->total_final,
            'numero_cuotas' => $venta->numero_cuotas,
        ], 201);
    }
}
