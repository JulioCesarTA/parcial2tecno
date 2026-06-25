<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Producto;
use App\Models\Usuario;
use App\Support\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CotizacionController extends Controller
{
    // Elimina cotizaciones PENDIENTES vencidas (cabecera + detalle por cascade)
    private function cleanupVencidas(): void
    {
        Cotizacion::where('estado', 'PENDIENTE')
            ->whereRaw("fecha_emision + (validez_dias || ' days')::interval < now()")
            ->delete();
    }

    public function index(Request $request)
    {
        $this->cleanupVencidas();
        $usuario = $request->user();

        $q = Cotizacion::with(['cliente', 'vendedor']);
        if ($usuario->esCliente()) {
            $q->where('cliente_id', $usuario->id);
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    public function show(Request $request, $id)
    {
        $this->cleanupVencidas();
        $cot = Cotizacion::with(['cliente', 'vendedor', 'detalles.producto'])->find($id);
        if (! $cot) {
            return response()->json(['message' => 'Cotización no encontrada.'], 404);
        }
        if ($request->user()->esCliente() && $cot->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No puedes ver una cotización ajena.'], 403);
        }

        return response()->json($cot);
    }

    public function store(Request $request)
    {
        $this->cleanupVencidas();

        $datos = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:usuario,id'],
            'vendedor_id' => ['required', 'integer', 'exists:usuario,id'],
            'fecha_emision' => ['nullable', 'date'],
            'remitente' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'destinatario' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'contenido' => ['required', 'string', 'regex:/^[\pL\s]+$/u'],
            'origen' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'destino' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'tipo_envio' => ['required', Rule::in(['aereo', 'maritimo', 'terrestre'])],
            'peso_kg' => ['required', 'numeric', 'gt:0'],
            'volumen_m3' => ['required', 'numeric', 'gt:0'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'impuestos' => ['nullable', 'numeric', 'gte:0'],
            'validez_dias' => ['required', 'integer', 'between:1,7'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:producto,id'],
            'productos.*.cantidad' => ['required', 'integer', 'gt:0'],
        ]);

        // Validar roles
        $cliente = Usuario::find($datos['cliente_id']);
        $vendedor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            return response()->json(['message' => 'El cliente indicado no tiene rol cliente.'], 422);
        }
        if (! ($vendedor->esVendedor() || $vendedor->esAdmin())) {
            return response()->json(['message' => 'El vendedor indicado no tiene rol vendedor/admin.'], 422);
        }

        $cot = DB::transaction(function () use ($datos) {
            $impuestos = (float) ($datos['impuestos'] ?? 0);
            $subtotal = 0;
            $lineas = [];
            foreach ($datos['productos'] as $item) {
                $p = Producto::findOrFail($item['producto_id']);
                $sub = round($p->precio_unitario * $item['cantidad'], 2);
                $subtotal += $sub;
                $lineas[] = [
                    'producto_id' => $p->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $p->precio_unitario,
                    'subtotal' => $sub,
                ];
            }
            $subtotal = round($subtotal, 2);

            $cot = Cotizacion::create([
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'],
                'estado' => 'PENDIENTE',
                'fecha_emision' => $datos['fecha_emision'] ?? now(),
                'remitente' => $datos['remitente'],
                'destinatario' => $datos['destinatario'],
                'contenido' => $datos['contenido'],
                'origen' => $datos['origen'],
                'destino' => $datos['destino'],
                'tipo_envio' => $datos['tipo_envio'],
                'peso_kg' => $datos['peso_kg'],
                'volumen_m3' => $datos['volumen_m3'],
                'fecha_entrega_estimada' => $datos['fecha_entrega_estimada'] ?? null,
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'total_estimado' => round($subtotal + $impuestos, 2),
                'validez_dias' => $datos['validez_dias'],
            ]);

            foreach ($lineas as $l) {
                $l['cotizacion_id'] = $cot->id;
                DetalleCotizacion::create($l);
            }

            return $cot;
        });

        BitacoraService::registrar($request->user()->id, 'accion', 'cotizaciones', "Crear cotización #{$cot->id}", $request);

        return response()->json([
            'message' => 'Cotización creada.',
            'id' => $cot->id,
            'total_estimado' => $cot->total_estimado,
            'validez_dias' => $cot->validez_dias,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->cleanupVencidas();
        $cot = Cotizacion::find($id);
        if (! $cot) {
            return response()->json(['message' => 'Cotización no encontrada o ya vencida.'], 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se puede editar una cotización en estado PENDIENTE.'], 422);
        }

        $datos = $request->validate([
            'estado' => ['sometimes', Rule::in(['PENDIENTE', 'APROBADA', 'RECHAZADA'])],
            'impuestos' => ['sometimes', 'numeric', 'gte:0'],
            'validez_dias' => ['sometimes', 'integer', 'between:1,7'],
        ]);

        if (isset($datos['impuestos'])) {
            $cot->impuestos = $datos['impuestos'];
            $cot->total_estimado = round($cot->subtotal + $datos['impuestos'], 2);
        }
        if (isset($datos['validez_dias'])) {
            $cot->validez_dias = $datos['validez_dias'];
        }
        if (isset($datos['estado'])) {
            $cot->estado = $datos['estado'];
        }
        $cot->save();

        BitacoraService::registrar($request->user()->id, 'accion', 'cotizaciones', "Editar cotización #{$cot->id}", $request);

        return response()->json(['message' => 'Cotización actualizada.', 'cotizacion' => $cot]);
    }

    public function destroy(Request $request, $id)
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            return response()->json(['message' => 'Cotización no encontrada.'], 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se puede eliminar una cotización PENDIENTE.'], 422);
        }

        $cot->delete(); // cascade borra detalle
        BitacoraService::registrar($request->user()->id, 'accion', 'cotizaciones', "Eliminar cotización #{$id}", $request);

        return response()->json(['message' => 'Cotización eliminada.']);
    }

    // Aprobar / Rechazar (CLIENTE dueño)
    public function aprobar(Request $request, $id)
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in(['si', 'no'])],
            'observaciones' => ['nullable', 'string'],
        ]);

        $cot = Cotizacion::find($id);
        if (! $cot) {
            return response()->json(['message' => 'Cotización no encontrada.'], 404);
        }
        if ($cot->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el cliente dueño puede aprobar o rechazar.'], 403);
        }
        if ($cot->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'La cotización ya no está pendiente.'], 422);
        }
        if ($cot->estaVencida()) {
            $cot->delete();

            return response()->json(['message' => 'La cotización venció y fue eliminada.'], 410);
        }

        if ($datos['decision'] === 'si') {
            $cot->estado = 'APROBADA';
            $cot->fecha_aprobacion = now(); // ventana de 20 min para el vendedor
        } else {
            $cot->estado = 'RECHAZADA';
        }
        $cot->save();

        BitacoraService::registrar(
            $request->user()->id,
            'accion',
            'cotizaciones',
            "Cotización #{$cot->id} " . ($datos['decision'] === 'si' ? 'APROBADA' : 'RECHAZADA'),
            $request
        );

        return response()->json(['message' => 'Decisión registrada.', 'cotizacion' => $cot]);
    }
}
