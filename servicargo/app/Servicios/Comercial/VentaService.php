<?php

namespace App\Servicios\Comercial;

use App\Models\DetalleVenta;
use App\Models\Encomienda;
use App\Models\Usuario;
use App\Models\Venta;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU07 — Gestión de Ventas (módulo comercial).
 */
class VentaService
{
    public function listarPara(Usuario $actor): Collection
    {
        $q = Venta::with(['cliente', 'vendedor', 'encomienda']);
        if ($actor->esCliente()) {
            $q->where('cliente_id', $actor->id);
        }

        return $q->orderByDesc('id')->get();
    }

    public function obtenerPara(Usuario $actor, int|string $id): Venta
    {
        $v = Venta::with(['cliente', 'vendedor', 'encomienda', 'pagos', 'facturas'])->find($id);
        if (! $v) {
            throw new ErrorDominio('Venta no encontrada.', 404);
        }
        if ($actor->esCliente() && $v->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes ver una venta ajena.', 403);
        }

        return $v;
    }

    /**
     * Crea la nota de venta del servicio logístico a partir de una encomienda.
     * El subtotal se toma de la cotización vinculada; soporta CONTADO y CRÉDITO (≥2 cuotas).
     */
    public function crear(array $datos, Usuario $actor): Venta
    {
        $cliente = Usuario::find($datos['cliente_id']);
        $vendedor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            throw new ErrorDominio('El cliente indicado no tiene rol cliente.', 422);
        }
        if (! ($vendedor->esVendedor() || $vendedor->esAdmin())) {
            throw new ErrorDominio('El vendedor indicado no tiene rol vendedor/admin.', 422);
        }

        $enc = Encomienda::with('cotizacion')->find($datos['encomienda_id']);
        if (! $enc->cotizacion) {
            throw new ErrorDominio('La encomienda no tiene cotización asociada.', 422);
        }

        $impuestos = (float) ($datos['impuestos'] ?? 0);
        $descuento = (float) ($datos['descuento'] ?? 0);
        $subtotal = (float) $enc->cotizacion->total_estimado;
        $totalFinal = round($subtotal + $impuestos - $descuento, 2);
        if ($totalFinal < 0) {
            throw new ErrorDominio('El descuento es mayor al total.', 422);
        }

        if ($datos['tipo_pago'] === 'CREDITO') {
            $cuotas = (int) ($datos['numero_cuotas'] ?? 0);
            if ($cuotas < 2) {
                throw new ErrorDominio('Para CRÉDITO el número de cuotas debe ser ≥ 2.', 422);
            }
        } else {
            $cuotas = 1;
        }

        $venta = DB::transaction(function () use ($datos, $enc, $impuestos, $descuento, $subtotal, $totalFinal, $cuotas) {
            $venta = Venta::create([
                'codigo' => 'TEMP',
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'],
                'encomienda_id' => $enc->id,
                'estado' => 'PENDIENTE',
                'fecha_venta' => $datos['fecha_venta'] ?? now(),
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total_final' => $totalFinal,
                'tipo_pago' => $datos['tipo_pago'],
                'numero_cuotas' => $cuotas,
            ]);
            $venta->codigo = 'SaTecno-' . $venta->id;
            $venta->save();

            // Trazabilidad: copiar detalle de la cotización
            foreach ($enc->cotizacion->detalles as $d) {
                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $d->producto_id,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal' => $d->subtotal,
                ]);
            }

            return $venta;
        });

        BitacoraService::registrar($actor->id, 'accion', 'ventas', "Crear venta {$venta->codigo}");

        return $venta;
    }
}
