<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Encomienda;
use App\Models\Usuario;
use App\Models\Venta;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $q = Venta::with(['cliente', 'vendedor', 'encomienda']);
        if ($request->user()->esCliente()) {
            $q->where('cliente_id', $request->user()->id);
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function show(Request $request, $id)
    {
        $v = Venta::with(['cliente', 'vendedor', 'encomienda', 'pagos', 'facturas'])->find($id);
        if (! $v) {
            return response()->json(['message' => 'Venta no encontrada.'], 404);
        }
        if ($request->user()->esCliente() && $v->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No puedes ver una venta ajena.'], 403);
        }

        return response()->json($v);
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

        $cliente = Usuario::find($datos['cliente_id']);
        $vendedor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            return response()->json(['message' => 'El cliente indicado no tiene rol cliente.'], 422);
        }
        if (! ($vendedor->esVendedor() || $vendedor->esAdmin())) {
            return response()->json(['message' => 'El vendedor indicado no tiene rol vendedor/admin.'], 422);
        }

        $enc = Encomienda::with('cotizacion')->find($datos['encomienda_id']);
        if (! $enc->cotizacion) {
            return response()->json(['message' => 'La encomienda no tiene cotización asociada.'], 422);
        }

        $impuestos = (float) ($datos['impuestos'] ?? 0);
        $descuento = (float) ($datos['descuento'] ?? 0);
        $subtotal = (float) $enc->cotizacion->total_estimado;
        $totalFinal = round($subtotal + $impuestos - $descuento, 2);
        if ($totalFinal < 0) {
            return response()->json(['message' => 'El descuento es mayor al total.'], 422);
        }

        if ($datos['tipo_pago'] === 'CREDITO') {
            $cuotas = (int) ($datos['numero_cuotas'] ?? 0);
            if ($cuotas < 2) {
                return response()->json(['message' => 'Para CRÉDITO el número de cuotas debe ser ≥ 2.'], 422);
            }
        } else {
            $cuotas = 1;
        }

        $venta = DB::transaction(function () use ($datos, $enc, $impuestos, $descuento, $subtotal, $totalFinal, $cuotas) {
            $venta = Venta::create([
                'codigo' => 'TEMP',
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'],
                'encomienda_id' => $enc->id,
                'estado' => 'PENDIENTE',
                'fecha_venta' => $datos['fecha_venta'] ?? now(),
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total_final' => $totalFinal,
                'tipo_pago' => $datos['tipo_pago'],
                'numero_cuotas' => $cuotas,
            ]);
            $venta->codigo = 'tecnoSa-' . $venta->id;
            $venta->save();

            // Trazabilidad: copiar detalle de la cotización
            foreach ($enc->cotizacion->detalles as $d) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $d->producto_id,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal' => $d->subtotal,
                ]);
            }

            return $venta;
        });

        BitacoraService::registrar($request->user()->id, 'accion', 'ventas', "Crear venta {$venta->codigo}", $request);

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
