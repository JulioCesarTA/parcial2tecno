<?php

namespace App\Servicios\Core;

use App\Models\Visita;
use Illuminate\Support\Facades\DB;

/**
 * Requisito 7 — Contador de visitas por página (módulo core).
 */
class VisitaService
{
    /** Incrementa (upsert) el contador de la página y devuelve el total. */
    public function incrementar(string $pagina): array
    {
        $pagina = substr(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $pagina), 0, 100) ?: 'home';

        DB::table('visitas')->upsert(
            ['pagina' => $pagina, 'contador' => 1],
            ['pagina'],
            ['contador' => DB::raw('visitas.contador + 1')]
        );

        $contador = (int) Visita::where('pagina', $pagina)->value('contador');

        return ['pagina' => $pagina, 'contador' => $contador];
    }

    /** Estadística de visitas por página (para saber lo más usado). */
    public function estadisticas(): array
    {
        return [
            'paginas' => Visita::orderByDesc('contador')->get(),
            'total' => (int) Visita::sum('contador'),
        ];
    }
}
