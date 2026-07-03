<?php

namespace App\Servicios\Core;

use App\Models\Cotizacion;
use App\Models\Encomienda;
use App\Models\Producto;
use App\Models\Usuario;

/**
 * Requisito 9 — Búsqueda del negocio (módulo core). Filtra por rol del actor.
 */
class BuscarService
{
    public function buscar(string $termino, Usuario $actor): array
    {
        $q = trim($termino);
        if (strlen($q) < 1) {
            return ['encomiendas' => [], 'cotizaciones' => [], 'productos' => []];
        }

        // Encomiendas por guía / destino / destinatario
        $enc = Encomienda::query()
            ->where(fn ($w) => $w->where('guia_rastreo', 'ilike', "%{$q}%")
                ->orWhere('destino', 'ilike', "%{$q}%")
                ->orWhere('destinatario', 'ilike', "%{$q}%"));
        if ($actor->esCliente()) {
            $enc->where('cliente_id', $actor->id);
        }

        // Cotizaciones por id / destino / remitente
        $cot = Cotizacion::query()
            ->where(fn ($w) => $w->where('destino', 'ilike', "%{$q}%")
                ->orWhere('remitente', 'ilike', "%{$q}%")
                ->when(is_numeric($q), fn ($x) => $x->orWhere('id', (int) $q)));
        if ($actor->esCliente()) {
            $cot->where('cliente_id', $actor->id);
        }

        $resultado = [
            'encomiendas' => $enc->orderByDesc('id')->limit(10)->get(),
            'cotizaciones' => $cot->orderByDesc('id')->limit(10)->get(),
            'productos' => [],
        ];

        // Productos solo para admin/asesor
        if (! $actor->esCliente()) {
            $resultado['productos'] = Producto::where('nombre', 'ilike', "%{$q}%")
                ->orWhere('codigo', 'ilike', "%{$q}%")
                ->limit(10)->get();
        }

        return $resultado;
    }
}
