<?php

namespace App\Servicios\Comercial;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Producto;
use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU05 — Gestión de Cotizaciones (módulo comercial).
 */
class CotizacionService
{
    /** Elimina cotizaciones PENDIENTES vencidas (cabecera + detalle por cascade). */
    public function limpiarVencidas(): void
    {
        Cotizacion::where('estado', 'PENDIENTE')
            ->whereRaw("fecha_emision + (validez_dias || ' days')::interval < now()")
            ->delete();
    }

    /** Lista para el actor (el cliente solo ve las suyas). */
    public function listarPara(Usuario $actor): Collection
    {
        $this->limpiarVencidas();

        $q = Cotizacion::with(['cliente', 'vendedor']);
        if ($actor->esCliente()) {
            $q->where('cliente_id', $actor->id);
        }

        return $q->orderByDesc('id')->get();
    }

    /** Obtiene una cotización validando propiedad del cliente. */
    public function obtenerPara(Usuario $actor, int|string $id): Cotizacion
    {
        $this->limpiarVencidas();

        $cot = Cotizacion::with(['cliente', 'vendedor', 'detalles.producto'])->find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($actor->esCliente() && $cot->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes ver una cotización ajena.', 403);
        }

        return $cot;
    }

    /** Crea una cotización con su detalle; calcula subtotal/total desde los productos. */
    public function crear(array $datos, Usuario $actor): Cotizacion
    {
        $this->limpiarVencidas();

        $cliente = Usuario::find($datos['cliente_id']);
        $vendedor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            throw new ErrorDominio('El cliente indicado no tiene rol cliente.', 422);
        }
        if (! ($vendedor->esVendedor() || $vendedor->esAdmin())) {
            throw new ErrorDominio('El vendedor indicado no tiene rol vendedor/admin.', 422);
        }

        $cot = DB::transaction(function () use ($datos) {
            $impuestos = (float) ($datos['impuestos'] ?? 0);
            $subtotal = 0;
            $lineas = [];
            foreach ($datos['productos'] as $item) {
                $p = Producto::findOrFail($item['producto_id']);
                $sub = round($p->precio_unitario * $item['cantidad'], 2);
                $subtotal += $sub;
                $lineas[] = [
                    'producto_id' => $p->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $p->precio_unitario,
                    'subtotal' => $sub,
                ];
            }
            $subtotal = round($subtotal, 2);

            $cot = Cotizacion::create([
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'],
                'estado' => 'PENDIENTE',
                'fecha_emision' => $datos['fecha_emision'] ?? now(),
                'remitente' => $datos['remitente'],
                'destinatario' => $datos['destinatario'],
                'contenido' => $datos['contenido'],
                'origen' => $datos['origen'],
                'destino' => $datos['destino'],
                'tipo_envio' => $datos['tipo_envio'],
                'peso_kg' => $datos['peso_kg'],
                'volumen_m3' => $datos['volumen_m3'],
                'fecha_entrega_estimada' => $datos['fecha_entrega_estimada'] ?? null,
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'total_estimado' => round($subtotal + $impuestos, 2),
                'validez_dias' => $datos['validez_dias'],
            ]);

            foreach ($lineas as $l) {
                $l['cotizacion_id'] = $cot->id;
                DetalleCotizacion::create($l);
            }

            return $cot;
        });

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Crear cotización #{$cot->id}");

        return $cot;
    }

    /** Edita una cotización PENDIENTE (impuestos, validez o estado). */
    public function actualizar(int|string $id, array $datos, Usuario $actor): Cotizacion
    {
        $this->limpiarVencidas();

        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada o ya vencida.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede editar una cotización en estado PENDIENTE.', 422);
        }

        if (isset($datos['impuestos'])) {
            $cot->impuestos = $datos['impuestos'];
            $cot->total_estimado = round($cot->subtotal + $datos['impuestos'], 2);
        }
        if (isset($datos['validez_dias'])) {
            $cot->validez_dias = $datos['validez_dias'];
        }
        if (isset($datos['estado'])) {
            $cot->estado = $datos['estado'];
        }
        $cot->save();

        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Editar cotización #{$cot->id}");

        return $cot;
    }

    /** Elimina una cotización PENDIENTE (borrado forzado admin/vendedor). */
    public function eliminar(int|string $id, Usuario $actor): void
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('Solo se puede eliminar una cotización PENDIENTE.', 422);
        }

        $cot->delete(); // cascade borra detalle
        BitacoraService::registrar($actor->id, 'accion', 'cotizaciones', "Eliminar cotización #{$id}");
    }

    /** El cliente dueño aprueba (si) o rechaza (no) la cotización. */
    public function decidir(int|string $id, array $datos, Usuario $actor): Cotizacion
    {
        $cot = Cotizacion::find($id);
        if (! $cot) {
            throw new ErrorDominio('Cotización no encontrada.', 404);
        }
        if ($cot->cliente_id !== $actor->id) {
            throw new ErrorDominio('Solo el cliente dueño puede aprobar o rechazar.', 403);
        }
        if ($cot->estado !== 'PENDIENTE') {
            throw new ErrorDominio('La cotización ya no está pendiente.', 422);
        }
        if ($cot->estaVencida()) {
            $cot->delete();
            throw new ErrorDominio('La cotización venció y fue eliminada.', 410);
        }

        if ($datos['decision'] === 'si') {
            $cot->estado = 'APROBADA';
            $cot->fecha_aprobacion = now(); // ventana de 20 min para el vendedor
        } else {
            $cot->estado = 'RECHAZADA';
        }
        $cot->save();

        BitacoraService::registrar(
            $actor->id,
            'accion',
            'cotizaciones',
            "Cotización #{$cot->id} " . ($datos['decision'] === 'si' ? 'APROBADA' : 'RECHAZADA')
        );

        return $cot;
    }
}
