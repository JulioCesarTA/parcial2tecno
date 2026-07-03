<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\CotizacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CotizacionController extends Controller
{
    public function __construct(private CotizacionService $cotizaciones) {}

    public function index(Request $request)
    {
        return response()->json($this->cotizaciones->listarPara($request->user()));
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->cotizaciones->obtenerPara($request->user(), $id));
    }

    public function pdf(Request $request, $id)
    {
        $cot = $this->cotizaciones->obtenerPara($request->user(), $id);

        $pdf = Pdf::loadView('reportes.cotizacion', [
            'cotizacion' => $cot,
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("cotizacion-{$cot->id}.pdf");
    }

    // El cliente pide su propia cotización (sin ir a la tienda), con fotos del producto/paquete.
    public function solicitar(Request $request)
    {
        $datos = $request->validate([
            'remitente' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'destinatario' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'contenido' => ['required', 'string', 'regex:/^[\pL\s]+$/u'],
            'origen' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'destino' => ['required', 'string', 'max:120', 'regex:/^[\pL\pN\s]+$/u'],
            'tipo_envio' => ['required', Rule::in(['aereo', 'maritimo', 'terrestre'])],
            'peso_kg' => ['required', 'numeric', 'gt:0'],
            'volumen_m3' => ['required', 'numeric', 'gt:0'],
            'fecha_entrega_estimada' => ['nullable', 'date'],
            'validez_dias' => ['required', 'integer', 'between:1,7'],
            'fotos' => ['nullable', 'array', 'max:5'],
            'fotos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $cot = $this->cotizaciones->solicitar($datos, $request->file('fotos', []), $request->user());

        return response()->json([
            'message' => 'Solicitud enviada. Un asesor la va a revisar y completar.',
            'id' => $cot->id,
        ], 201);
    }

    // Bandeja para admin/asesor: solicitudes del cliente sin asesor asignado todavía.
    public function solicitudesPendientes(Request $request)
    {
        return response()->json($this->cotizaciones->listarSolicitudesPendientes());
    }

    public function agregarProducto(Request $request, $id)
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:producto,id'],
            'cantidad' => ['required', 'integer', 'gt:0'],
        ]);

        $cot = $this->cotizaciones->agregarProducto($id, $datos, $request->user());

        return response()->json(['message' => 'Producto agregado.', 'cotizacion' => $cot]);
    }

    public function actualizarProducto(Request $request, $id, $productoId)
    {
        $datos = $request->validate([
            'cantidad' => ['required', 'integer', 'gt:0'],
        ]);

        $cot = $this->cotizaciones->actualizarProducto($id, $productoId, $datos, $request->user());

        return response()->json(['message' => 'Producto actualizado.', 'cotizacion' => $cot]);
    }

    public function eliminarProducto(Request $request, $id, $productoId)
    {
        $cot = $this->cotizaciones->eliminarProducto($id, $productoId, $request->user());

        return response()->json(['message' => 'Producto quitado.', 'cotizacion' => $cot]);
    }

    public function store(Request $request)
    {
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

        $cot = $this->cotizaciones->crear($datos, $request->user());

        return response()->json([
            'message' => 'Cotización creada.',
            'id' => $cot->id,
            'total_estimado' => $cot->total_estimado,
            'validez_dias' => $cot->validez_dias,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $datos = $request->validate([
            'estado' => ['sometimes', Rule::in(['PENDIENTE', 'APROBADA', 'RECHAZADA'])],
            'impuestos' => ['sometimes', 'numeric', 'gte:0'],
            'validez_dias' => ['sometimes', 'integer', 'between:1,7'],
        ]);

        $cot = $this->cotizaciones->actualizar($id, $datos, $request->user());

        return response()->json(['message' => 'Cotización actualizada.', 'cotizacion' => $cot]);
    }

    public function destroy(Request $request, $id)
    {
        $this->cotizaciones->eliminar($id, $request->user());

        return response()->json(['message' => 'Cotización eliminada.']);
    }

    // Aprobar / Rechazar (CLIENTE dueño)
    public function aprobar(Request $request, $id)
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in(['si', 'no'])],
            'observaciones' => ['nullable', 'string'],
        ]);

        $cot = $this->cotizaciones->decidir($id, $datos, $request->user());

        return response()->json(['message' => 'Decisión registrada.', 'cotizacion' => $cot]);
    }
}
