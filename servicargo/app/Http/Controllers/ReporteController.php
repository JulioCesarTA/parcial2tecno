<?php

namespace App\Http\Controllers;

use App\Servicios\Reporte\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes) {}

    /** Parseo del rango de fechas desde la query ('*' o vacío = sin filtro). */
    private function rango(Request $request): array
    {
        $ini = $request->query('fecha_inicio');
        $fin = $request->query('fecha_fin');
        if ($ini === '*' || $ini === null) {
            return [null, null];
        }

        return [$ini, $fin ?: now()->toDateString()];
    }

    private function params(Request $request): array
    {
        return $request->only(['fecha_inicio', 'fecha_fin']);
    }

    public function ventas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);

        return response()->json($this->reportes->ventas($ini, $fin, $request->user()->id, $this->params($request)));
    }

    public function encomiendas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);

        return response()->json($this->reportes->encomiendas($ini, $fin, $request->user()->id, $this->params($request)));
    }

    public function inventario(Request $request)
    {
        return response()->json($this->reportes->inventario($request->user()->id, $this->params($request)));
    }

    public function cotizaciones(Request $request)
    {
        [$ini, $fin] = $this->rango($request);

        return response()->json($this->reportes->cotizaciones($ini, $fin, $request->user()->id, $this->params($request)));
    }

    public function facturas(Request $request)
    {
        [$ini, $fin] = $this->rango($request);

        return response()->json($this->reportes->facturas($ini, $fin, $request->user()->id, $this->params($request)));
    }

    // Estadísticas de acceso (de la bitácora)
    public function acceso(Request $request)
    {
        return response()->json($this->reportes->acceso());
    }

    // Export PDF (dompdf)
    public function pdf(Request $request, $tipo)
    {
        $tiposValidos = ['ventas', 'encomiendas', 'inventario', 'cotizaciones', 'facturas'];
        if (! in_array($tipo, $tiposValidos, true)) {
            return response()->json(['message' => 'Tipo de reporte inválido.'], 422);
        }

        [$ini, $fin] = $this->rango($request);
        $actorId = $request->user()->id;
        $params = $this->params($request);

        $datos = match ($tipo) {
            'ventas' => $this->reportes->ventas($ini, $fin, $actorId, $params),
            'encomiendas' => $this->reportes->encomiendas($ini, $fin, $actorId, $params),
            'inventario' => $this->reportes->inventario($actorId, $params),
            'cotizaciones' => $this->reportes->cotizaciones($ini, $fin, $actorId, $params),
            'facturas' => $this->reportes->facturas($ini, $fin, $actorId, $params),
        };

        $pdf = Pdf::loadView('reportes.generico', [
            'titulo' => 'Reporte de ' . ucfirst($tipo),
            'tipo' => $tipo,
            'datos' => $datos,
            'desde' => $ini,
            'hasta' => $fin,
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("reporte-{$tipo}.pdf");
    }
}
