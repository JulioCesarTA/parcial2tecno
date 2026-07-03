<?php

namespace App\Servicios\Core;

use App\Models\Visita;
use Illuminate\Support\Facades\DB;

/**
 * Requisito 7 — Contador de visitas por página (módulo core).
 */
class VisitaService
{
    /**
     * Incrementa (upsert) el contador de la página para el usuario dado y
     * devuelve su total. $usuarioId es null en páginas públicas visitadas sin
     * sesión (ej. landing): ese caso no se deduplica (cada visita anónima
     * suma una fila con contador=1), porque Postgres no trata dos NULL como
     * iguales para el ON CONFLICT del upsert; no afecta las estadísticas
     * agregadas, que suman por página sin importar cuántas filas haya.
     */
    public function incrementar(string $pagina, ?int $usuarioId = null): array
    {
        $pagina = substr(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $pagina), 0, 100) ?: 'home';

        DB::table('visitas')->upsert(
            ['usuario_id' => $usuarioId, 'pagina' => $pagina, 'contador' => 1],
            ['usuario_id', 'pagina'],
            ['contador' => DB::raw('visitas.contador + 1')]
        );

        $contador = (int) Visita::where('pagina', $pagina)
            ->when(
                $usuarioId,
                fn ($q) => $q->where('usuario_id', $usuarioId),
                fn ($q) => $q->whereNull('usuario_id'),
            )
            ->value('contador');

        return ['pagina' => $pagina, 'contador' => $contador];
    }

    /** Estadística de visitas por página (para saber lo más usado), sumada entre todos los usuarios. */
    public function estadisticas(): array
    {
        return [
            'paginas' => Visita::select('pagina', DB::raw('SUM(contador) as contador'))
                ->groupBy('pagina')
                ->orderByDesc('contador')
                ->get(),
            'total' => (int) Visita::sum('contador'),
        ];
    }
}
