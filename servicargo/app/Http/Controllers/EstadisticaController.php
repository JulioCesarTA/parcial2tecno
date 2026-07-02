<?php

namespace App\Http\Controllers;

use App\Servicios\Reporte\EstadisticaService;
use App\Servicios\Reporte\GraficoService;

/**
 * Requisito 8 — Estadísticas del negocio (módulo reporte). Solo lectura, admin.
 */
class EstadisticaController extends Controller
{
    public function __construct(private EstadisticaService $estadisticas) {}

    public function index()
    {
        return response()->json($this->estadisticas->panel());
    }

    /** Gráfico estadístico (JpGraph) como imagen PNG. */
    public function grafico(string $tipo, GraficoService $graficos)
    {
        $png = $graficos->png($tipo);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }
}
