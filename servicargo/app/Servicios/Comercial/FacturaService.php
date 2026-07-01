<?php

namespace App\Servicios\Comercial;

use App\Models\Factura;
use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use Illuminate\Database\Eloquent\Collection;

/**
 * CU07 — Facturas (módulo comercial). Solo lectura; se generan desde los pagos.
 */
class FacturaService
{
    public function listarPara(Usuario $actor): Collection
    {
        $q = Factura::with('venta');
        if ($actor->esCliente()) {
            $q->whereHas('venta', fn ($v) => $v->where('cliente_id', $actor->id));
        }

        return $q->orderByDesc('id')->get();
    }

    public function obtenerPara(Usuario $actor, int|string $id): Factura
    {
        $f = Factura::with('venta')->find($id);
        if (! $f) {
            throw new ErrorDominio('Factura no encontrada.', 404);
        }
        if ($actor->esCliente() && $f->venta->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes ver una factura ajena.', 403);
        }

        return $f;
    }
}
