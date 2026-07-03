<?php

namespace App\Http\Controllers;

use App\Servicios\Core\PreferenciaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenciaController extends Controller
{
    public function __construct(private PreferenciaService $preferencias) {}

    public function show(Request $request)
    {
        return response()->json($this->preferencias->obtenerDe($request->user()));
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            // Formato "persona:modo" (navy|nino|joven|adulto : auto|light|dark).
            // Se toleran los valores antiguos (auto|dia|noche|ninos|jovenes) por compatibilidad.
            'tema' => ['required', 'string', 'max:20', 'regex:/^((navy|nino|joven|adulto):(auto|light|dark)|auto|dia|noche|ninos|jovenes)$/'],
            'fuente' => ['required', Rule::in(['sm', 'md', 'lg'])],
            'contraste' => ['required', Rule::in(['normal', 'alto'])],
        ]);

        return response()->json([
            'message' => 'Preferencias guardadas.',
            'preferencias' => $this->preferencias->guardar($datos, $request->user()),
        ]);
    }
}
