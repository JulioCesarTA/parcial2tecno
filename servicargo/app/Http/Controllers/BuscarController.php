<?php

namespace App\Http\Controllers;

use App\Servicios\Core\BuscarService;
use Illuminate\Http\Request;

class BuscarController extends Controller
{
    public function __construct(private BuscarService $buscador) {}

    // Búsqueda del negocio (encabezado) — filtra por rol
    public function buscar(Request $request)
    {
        return response()->json(
            $this->buscador->buscar((string) $request->query('q', ''), $request->user())
        );
    }
}
