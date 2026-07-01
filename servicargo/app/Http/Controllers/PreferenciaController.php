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
            'tema' => ['required', Rule::in(['auto', 'dia', 'noche', 'ninos', 'jovenes'])],
            'fuente' => ['required', Rule::in(['sm', 'md', 'lg'])],
            'contraste' => ['required', Rule::in(['normal', 'alto'])],
        ]);

        return response()->json([
            'message' => 'Preferencias guardadas.',
            'preferencias' => $this->preferencias->guardar($datos, $request->user()),
        ]);
    }
}
