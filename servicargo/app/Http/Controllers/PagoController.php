<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\Pago;
use App\Models\Venta;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function index(Request $request)
    {
        $q = Pago::with('venta');
        if ($request->user()->esCliente()) {
            $q->whereHas('venta', fn ($v) => $v->where('cliente_id', $request->user()->id));
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'venta_id' => ['required', 'integer', 'exists:venta,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', Rule::in(['EFECTIVO', 'QR'])],
            'metodo_pago_id' => ['nullable', 'integer', 'exists:metodo_pago,id'],
            'numero_cuota' => ['nullable', 'integer', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:60'],
        ]);

        $usuario = $request->user();
        $venta = Venta::find($datos['venta_id']);

        // Si paga con un método de pago registrado, debe ser suyo y define tipo/referencia
        if (! empty($datos['metodo_pago_id'])) {
            $metodo = \App\Models\MetodoPago::where('usuario_id', $usuario->id)
                ->where('activo', true)->find($datos['metodo_pago_id']);
            if (! $metodo) {
                return response()->json(['message' => 'Método de pago no válido o no te pertenece.'], 422);
            }
            $datos['metodo_pago'] = $metodo->tipo;
            $datos['referencia'] = $datos['referencia'] ?? $metodo->alias;
        }

        // El cliente solo paga lo suyo y solo por QR
        if ($usuario->esCliente()) {
            if ($venta->cliente_id !== $usuario->id) {
                return response()->json(['message' => 'No puedes pagar una venta ajena.'], 403);
            }
            if ($datos['metodo_pago'] !== 'QR') {
                return response()->json(['message' => 'El cliente solo puede pagar con QR. Para EFECTIVO acude a un vendedor.'], 422);
            }
        }

        if ($venta->estado === 'PAGADA') {
            return response()->json(['message' => 'La venta ya está pagada.'], 422);
        }
        if (isset($datos['numero_cuota']) && $datos['numero_cuota'] > $venta->numero_cuotas) {
            return response()->json(['message' => 'El número de cuota excede las cuotas de la venta.'], 422);
        }

        // QR: se registra el pago como PENDIENTE y se devuelve el QR (PagoFácil simulado)
        if ($datos['metodo_pago'] === 'QR') {
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'estado' => 'PENDIENTE',
                'fecha_pago' => now(),
                'metodo_pago' => 'QR',
                'monto' => $datos['monto'],
                'numero_cuota' => $datos['numero_cuota'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
            ]);

            return response()->json([
                'message' => 'QR generado. Confirma el pago para registrarlo.',
                'pago_id' => $pago->id,
                'qr' => 'SERVICARGO|venta:' . $venta->id . '|monto:' . $datos['monto'] . '|pago:' . $pago->id,
                'simulado' => true,
            ], 201);
        }

        // EFECTIVO: se confirma de inmediato
        $resultado = DB::transaction(function () use ($venta, $datos, $usuario) {
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'estado' => 'REGISTRADO',
                'fecha_pago' => now(),
                'metodo_pago' => 'EFECTIVO',
                'monto' => $datos['monto'],
                'numero_cuota' => $datos['numero_cuota'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
            ]);

            return $this->confirmar($venta, $pago);
        });

        BitacoraService::registrar($usuario->id, 'accion', 'pagos', "Pago EFECTIVO venta #{$venta->id}", $request);

        return response()->json([
            'message' => 'Pago registrado.',
            'pago' => $resultado['pago'],
            'factura' => $resultado['factura'],
            'estado_venta' => $resultado['estado_venta'],
        ], 201);
    }

    // Confirmación del QR (callback de PagoFácil — simulado)
    public function simularConfirmacion(Request $request, $pagoId)
    {
        $pago = Pago::find($pagoId);
        if (! $pago) {
            return response()->json(['message' => 'Pago no encontrado.'], 404);
        }
        if ($pago->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'El pago no está pendiente de confirmación.'], 422);
        }

        $venta = Venta::find($pago->venta_id);
        $resultado = DB::transaction(function () use ($venta, $pago) {
            $pago->estado = 'REGISTRADO';
            $pago->save();

            return $this->confirmar($venta, $pago);
        });

        BitacoraService::registrar($request->user()->id ?? null, 'accion', 'pagos', "Confirmación QR venta #{$venta->id}", $request);

        return response()->json([
            'message' => 'Pago QR confirmado.',
            'pago' => $resultado['pago'],
            'factura' => $resultado['factura'],
            'estado_venta' => $resultado['estado_venta'],
        ]);
    }

    /** Genera la factura del pago y actualiza el estado de la venta. */
    private function confirmar(Venta $venta, Pago $pago): array
    {
        $factura = Factura::create([
            'venta_id' => $venta->id,
            'estado' => 'EMITIDA',
            'fecha_emision' => now(),
            'impuestos' => 0,
            'numero_factura' => "FAC-{$venta->id}-{$pago->id}",
            'subtotal' => $pago->monto,
            'total' => $pago->monto,
            'metodo_pago' => $pago->metodo_pago,
            'numero_cuota' => $pago->numero_cuota,
        ]);

        $totalPagado = (float) $venta->pagos()->where('estado', 'REGISTRADO')->sum('monto');
        $venta->estado = $totalPagado >= (float) $venta->total_final ? 'PAGADA' : 'PARCIAL';
        $venta->save();

        return [
            'pago' => $pago,
            'factura' => $factura,
            'estado_venta' => $venta->estado,
        ];
    }
}
