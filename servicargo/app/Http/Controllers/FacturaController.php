<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Servicios\Comercial\FacturaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function __construct(private FacturaService $facturas) {}

    public function index(Request $request)
    {
        return response()->json($this->facturas->listarPara($request->user()));
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->facturas->obtenerPara($request->user(), $id));
    }

    public function pdf(Request $request, $id)
    {
        $factura = $this->facturas->obtenerPara($request->user(), $id);
        $factura->load(['venta.cliente', 'venta.vendedor']);
        $items = DetalleVenta::where('venta_id', $factura->venta_id)->with('producto')->get();

        $pdf = Pdf::loadView('reportes.factura', [
            'factura' => $factura,
            'items' => $items,
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("factura-{$factura->numero_factura}.pdf");
    }
}
