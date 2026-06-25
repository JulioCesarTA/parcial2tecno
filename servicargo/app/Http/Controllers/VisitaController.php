<?php

namespace App\Http\Controllers;

use App\Models\Visita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitaController extends Controller
{
    // Incrementa el contador de la página (upsert) — público
    public function incrementar(Request $request, $pagina)
    {
        $pagina = substr(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $pagina), 0, 100) ?: 'home';

        DB::table('visitas')->upsert(
            ['pagina' => $pagina, 'contador' => 1],
            ['pagina'],
            ['contador' => DB::raw('visitas.contador + 1')]
        );

        $contador = (int) Visita::where('pagina', $pagina)->value('contador');

        return response()->json(['pagina' => $pagina, 'contador' => $contador]);
    }

    public function index()
    {
        return response()->json([
            'paginas' => Visita::orderByDesc('contador')->get(),
            'total' => (int) Visita::sum('contador'),
        ]);
    }
}
