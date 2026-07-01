<?php

namespace App\Http\Controllers;

use App\Servicios\Core\VisitaService;

class VisitaController extends Controller
{
    public function __construct(private VisitaService $visitas) {}

    // Incrementa el contador de la página (upsert) — público
    public function incrementar($pagina)
    {
        return response()->json($this->visitas->incrementar($pagina));
    }

    public function index()
    {
        return response()->json($this->visitas->estadisticas());
    }
}
