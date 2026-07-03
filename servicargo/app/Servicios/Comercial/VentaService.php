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
        $asesor = Usuario::find($datos['vendedor_id']);
        if (! $cliente->esCliente()) {
            throw new ErrorDominio('El cliente indicado no tiene rol cliente.', 422);
        }
        if (! ($asesor->esAsesor() || $asesor->esAdmin())) {
            throw new ErrorDominio('El asesor indicado no tiene rol asesor/admin.', 422);
        }

        $enc = Encomienda::with('cotizacion')->find($datos['encomienda_id']);
        if (! $enc->cotizacion) {
            throw new ErrorDominio('La encomienda no tiene cotización asociada.', 422);
        }
        if (Venta::where('encomienda_id', $enc->id)->exists()) {
            throw new ErrorDominio('Esta encomienda ya tiene una nota de venta.', 422);
        }

        $impuestos = (float) ($datos['impuestos'] ?? 0);
        $descuento = (float) ($datos['descuento'] ?? 0);
        $subtotal = (float) $enc->cotizacion->total_estimado;
        $totalFinal = round($subtotal + $impuestos - $descuento, 2);
        if ($totalFinal < 0) {
            throw new ErrorDominio('El descuento es mayor al total.', 422);
        }

        // Política de negocio: crédito solo hasta 3 cuotas.
        if ($datos['tipo_pago'] === 'CREDITO') {
            $cuotas = (int) ($datos['numero_cuotas'] ?? 0);
            if ($cuotas < 2 || $cuotas > 3) {
                throw new ErrorDominio('Para CRÉDITO el número de cuotas debe ser 2 o 3.', 422);
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

    /**
     * El cliente, con su encomienda ya en curso, pide su propia nota de venta
     * (contado o crédito) sin depender de que un asesor la genere. El sistema
     * la arma solo: toma el total ya cotizado y queda lista para pagar. Se le
     * asigna el mismo asesor que atendió la cotización de origen, para no
     * perder la trazabilidad de quién llevó el caso.
     */
    public function solicitar(array $datos, Usuario $cliente): Venta
    {
        $enc = Encomienda::with('cotizacion')->find($datos['encomienda_id']);
        if (! $enc) {
            throw new ErrorDominio('Encomienda no encontrada.', 404);
        }
        if ($enc->cliente_id !== $cliente->id) {
            throw new ErrorDominio('No puedes pedir la nota de venta de una encomienda ajena.', 403);
        }
        if (! $enc->cotizacion || ! $enc->cotizacion->vendedor_id) {
            throw new ErrorDominio('Esta encomienda todavía no tiene un asesor asignado.', 422);
        }

        return $this->crear([
            'cliente_id' => $cliente->id,
            'vendedor_id' => $enc->cotizacion->vendedor_id,
            'encomienda_id' => $enc->id,
            'impuestos' => 0,
            'descuento' => 0,
            'tipo_pago' => $datos['tipo_pago'],
            'numero_cuotas' => $datos['numero_cuotas'] ?? null,
        ], $cliente);
    }
}
