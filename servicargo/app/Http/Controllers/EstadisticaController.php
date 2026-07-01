<?php

namespace App\Http\Controllers;

use App\Servicios\Reporte\EstadisticaService;

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
}
