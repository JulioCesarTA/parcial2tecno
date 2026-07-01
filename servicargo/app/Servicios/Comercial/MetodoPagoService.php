<?php

namespace App\Servicios\Comercial;

use App\Models\MetodoPago;
use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Requisito 10 — Registro de métodos de pago (módulo comercial).
 * Cada usuario gestiona SUS propios métodos.
 */
class MetodoPagoService
{
    public function listarDe(Usuario $actor): Collection
    {
        return MetodoPago::where('usuario_id', $actor->id)->orderByDesc('id')->get();
    }

    public function crear(array $datos, Usuario $actor): MetodoPago
    {
        $datos['usuario_id'] = $actor->id;
        $datos['activo'] = true;

        $m = MetodoPago::create($datos);
        BitacoraService::registrar($actor->id, 'accion', 'metodos_pago', "Registrar método de pago #{$m->id}");

        return $m;
    }

    public function actualizar(int|string $id, array $datos, Usuario $actor): MetodoPago
    {
        $m = MetodoPago::where('usuario_id', $actor->id)->find($id);
        if (! $m) {
            throw new ErrorDominio('Método de pago no encontrado.', 404);
        }

        $m->update($datos);

        return $m;
    }

    public function eliminar(int|string $id, Usuario $actor): void
    {
        $m = MetodoPago::where('usuario_id', $actor->id)->find($id);
        if (! $m) {
            throw new ErrorDominio('Método de pago no encontrado.', 404);
        }

        $m->delete();
        BitacoraService::registrar($actor->id, 'accion', 'metodos_pago', "Eliminar método de pago #{$id}");
    }
}
