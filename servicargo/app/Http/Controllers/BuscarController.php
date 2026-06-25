<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Encomienda;
use App\Models\Producto;
use Illuminate\Http\Request;

class BuscarController extends Controller
{
    // Búsqueda del negocio (encabezado) — filtra por rol
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $usuario = $request->user();
        if (strlen($q) < 1) {
            return response()->json(['encomiendas' => [], 'cotizaciones' => [], 'productos' => []]);
        }

        // Encomiendas por guía / destino
        $enc = Encomienda::query()
            ->where(fn ($w) => $w->where('guia_rastreo', 'ilike', "%{$q}%")
                ->orWhere('destino', 'ilike', "%{$q}%")
                ->orWhere('destinatario', 'ilike', "%{$q}%"));
        if ($usuario->esCliente()) {
            $enc->where('cliente_id', $usuario->id);
        }

        // Cotizaciones por id / destino
        $cot = Cotizacion::query()
            ->where(fn ($w) => $w->where('destino', 'ilike', "%{$q}%")
                ->orWhere('remitente', 'ilike', "%{$q}%")
                ->when(is_numeric($q), fn ($x) => $x->orWhere('id', (int) $q)));
        if ($usuario->esCliente()) {
            $cot->where('cliente_id', $usuario->id);
        }

        $resultado = [
            'encomiendas' => $enc->orderByDesc('id')->limit(10)->get(),
            'cotizaciones' => $cot->orderByDesc('id')->limit(10)->get(),
            'productos' => [],
        ];

        // Productos solo para admin/vendedor
        if (! $usuario->esCliente()) {
            $resultado['productos'] = Producto::where('nombre', 'ilike', "%{$q}%")
                ->orWhere('codigo', 'ilike', "%{$q}%")
                ->limit(10)->get();
        }

        return response()->json($resultado);
    }
}
