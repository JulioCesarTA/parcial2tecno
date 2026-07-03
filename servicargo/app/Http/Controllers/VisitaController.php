<?php

namespace App\Http\Controllers;

use App\Servicios\Core\VisitaService;
use App\Support\JwtService;
use Illuminate\Http\Request;

class VisitaController extends Controller
{
    public function __construct(private VisitaService $visitas) {}

    // Incrementa el contador de la página (upsert) — ruta pública (landing
    // incluida), pero si viene con token válido la visita queda ligada al usuario.
    public function incrementar(Request $request, $pagina)
    {
        $usuario = JwtService::usuarioDesdeToken($request->bearerToken());

        return response()->json($this->visitas->incrementar($pagina, $usuario?->id));
    }

    public function index()
    {
        return response()->json($this->visitas->estadisticas());
    }
}
