<?php

namespace App\Servicios\Comercial;

use App\Models\Factura;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Venta;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU07 — Pagos y facturación (módulo comercial).
 *
 * Formas de pago (en la venta): CONTADO | CREDITO (cuotas).
 * Métodos de pago (del pago):   EFECTIVO | QR.
 *
 * Quién registra el pago:
 *  - admin/vendedor: el cliente está físicamente en tienda → cualquier método
 *    (EFECTIVO o QR), sea contado o crédito.
 *  - cliente (desde casa): solo QR, sea contado o crédito.
 *
 * El QR es real (PagoFácil): se genera con la pasarela, queda PENDIENTE y se
 * confirma al pagar (por callback de PagoFácil o por consulta de estado).
 * Cada pago confirmado emite una factura.
 */
class PagoService
{
    public function __construct(private PagoFacilService $pagoFacil) {}

    public function listarPara(Usuario $actor): Collection
    {
        $q = Pago::with('venta');
        if ($actor->esCliente()) {
            $q->whereHas('venta', fn ($v) => $v->where('cliente_id', $actor->id));
        }

        return $q->orderByDesc('id')->get();
    }

    /**
     * Registra un pago. EFECTIVO se confirma al instante y factura;
     * QR genera el código de PagoFácil y queda PENDIENTE hasta la confirmación.
     * Devuelve el payload listo para responder (con su 'message').
     */
    public function registrar(array $datos, Usuario $actor): array
    {
        $venta = Venta::with('cliente')->find($datos['venta_id']);

        // Si paga con un método registrado, debe ser suyo y define tipo/referencia
        if (! empty($datos['metodo_pago_id'])) {
            $metodo = MetodoPago::where('usuario_id', $actor->id)
                ->where('activo', true)->find($datos['metodo_pago_id']);
            if (! $metodo) {
                throw new ErrorDominio('Método de pago no válido o no te pertenece.', 422);
            }
            $datos['metodo_pago'] = $metodo->tipo;
            $datos['referencia'] = $datos['referencia'] ?? $metodo->alias;
        }

        // El cliente solo paga lo suyo y solo por QR (desde casa)
        if ($actor->esCliente()) {
            if ($venta->cliente_id !== $actor->id) {
                throw new ErrorDominio('No puedes pagar una venta ajena.', 403);
            }
            if ($datos['metodo_pago'] !== 'QR') {
                throw new ErrorDominio('El cliente solo puede pagar con QR. Para EFECTIVO acude a un vendedor.', 422);
            }
        }

        if ($venta->estado === 'PAGADA') {
            throw new ErrorDominio('La venta ya está pagada.', 422);
        }
        if (isset($datos['numero_cuota']) && $datos['numero_cuota'] > $venta->numero_cuotas) {
            throw new ErrorDominio('El número de cuota excede las cuotas de la venta.', 422);
        }

        if ($datos['metodo_pago'] === 'QR') {
            return $this->generarPagoQr($venta, $datos, $actor);
        }

        // EFECTIVO: se confirma de inmediato
        $resultado = DB::transaction(function () use ($venta, $datos) {
            $pago = Pago::create([
                'venta_id' => $venta->id,
                'estado' => 'REGISTRADO',
                'fecha_pago' => now(),
                'metodo_pago' => 'EFECTIVO',
                'monto' => $datos['monto'],
                'numero_cuota' => $datos['numero_cuota'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
            ]);

            return $this->confirmar($venta, $pago);
        });

        BitacoraService::registrar($actor->id, 'accion', 'pagos', "Pago EFECTIVO venta #{$venta->id}");

        return [
            'message' => 'Pago registrado.',
            'pago' => $resultado['pago'],
            'factura' => $resultado['factura'],
            'estado_venta' => $resultado['estado_venta'],
        ];
    }

    /** Genera el QR real de PagoFácil y crea el pago en estado PENDIENTE. */
    private function generarPagoQr(Venta $venta, array $datos, Usuario $actor): array
    {
        $cliente = $venta->cliente;
        if (! $cliente || empty($cliente->correo)) {
            throw new ErrorDominio('El cliente de la venta no tiene correo para generar el QR.', 422);
        }

        $qr = $this->pagoFacil->generarQr([
            'clientName' => trim($cliente->nombre . ' ' . $cliente->apellido) ?: 'Cliente',
            'documentId' => (string) $cliente->ci,
            'phoneNumber' => (string) $cliente->telefono,
            'email' => $cliente->correo,
            'paymentNumber' => $venta->codigo,   // companyTransactionId (PedidoID)
            'clientCode' => (string) $cliente->id,
            'amount' => $datos['monto'],
            'product' => 'Pago venta ' . $venta->codigo,
        ]);

        $pago = Pago::create([
            'venta_id' => $venta->id,
            'estado' => 'PENDIENTE',
            'fecha_pago' => now(),
            'metodo_pago' => 'QR',
            'monto' => $datos['monto'],
            'numero_cuota' => $datos['numero_cuota'] ?? null,
            'referencia' => $datos['referencia'] ?? 'PAGOFACIL_QR',
            'transaccion_id' => $qr['transaction_id'],
            'expira_en' => $qr['expiration_date'],
        ]);

        BitacoraService::registrar($actor->id, 'accion', 'pagos', "QR generado venta #{$venta->id}");

        return [
            'message' => 'QR generado. Escanéalo para completar el pago.',
            'pago_id' => $pago->id,
            'qr_base64' => $qr['qr_base64'],
            'transaccion_id' => $qr['transaction_id'],
            'expira_en' => $qr['expiration_date'],
        ];
    }

    /**
     * Consulta a PagoFácil el estado del pago QR. Si ya está pagado (status 2)
     * lo confirma y factura. Devuelve el estado del pago para el frontend (polling).
     */
    public function consultarEstadoQr(int|string $pagoId, Usuario $actor): array
    {
        $pago = Pago::with('venta')->find($pagoId);
        if (! $pago) {
            throw new ErrorDominio('Pago no encontrado.', 404);
        }
        if ($actor->esCliente() && $pago->venta->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes consultar un pago ajeno.', 403);
        }

        if ($pago->estado === 'REGISTRADO') {
            return ['estado' => 'REGISTRADO', 'pagado' => true, 'estado_venta' => $pago->venta->estado];
        }
        if ($pago->estado !== 'PENDIENTE' || $pago->metodo_pago !== 'QR') {
            return ['estado' => $pago->estado, 'pagado' => false, 'estado_venta' => $pago->venta->estado];
        }

        $status = $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $pago->venta->codigo);
        if ($status !== 2) {
            return ['estado' => 'PENDIENTE', 'pagado' => false, 'estado_venta' => $pago->venta->estado];
        }

        $resultado = $this->confirmarPago($pago);

        return [
            'estado' => 'REGISTRADO',
            'pagado' => true,
            'message' => 'Pago QR confirmado.',
            'factura' => $resultado['factura'],
            'estado_venta' => $resultado['estado_venta'],
        ];
    }

    /**
     * Confirmación disparada por el callback de PagoFácil. Ubica la venta por su
     * código (PedidoID/companyTransactionId), verifica el pago y lo confirma.
     * Devuelve true si el pago quedó registrado.
     */
    public function confirmarPorCallback(string $companyTransactionId, ?int $estado = null): bool
    {
        $venta = Venta::where('codigo', $companyTransactionId)->first();
        if (! $venta) {
            return false;
        }

        $pago = Pago::where('venta_id', $venta->id)
            ->where('metodo_pago', 'QR')
            ->where('estado', 'PENDIENTE')
            ->orderByDesc('id')->first();
        if (! $pago) {
            return false;
        }

        // Confiamos en Estado==2 del callback; si no vino, verificamos con la pasarela.
        $pagado = $estado === 2
            || $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $venta->codigo) === 2;
        if (! $pagado) {
            return false;
        }

        $this->confirmarPago($pago);
        BitacoraService::registrar(null, 'accion', 'pagos', "Callback PagoFácil venta #{$venta->id}");

        return true;
    }

    /** Marca el pago como REGISTRADO, emite factura y actualiza la venta. */
    private function confirmarPago(Pago $pago): array
    {
        $venta = $pago->venta ?: Venta::find($pago->venta_id);

        return DB::transaction(function () use ($venta, $pago) {
            $pago->estado = 'REGISTRADO';
            $pago->save();

            return $this->confirmar($venta, $pago);
        });
    }

    /** Genera la factura del pago y actualiza el estado de la venta. */
    private function confirmar(Venta $venta, Pago $pago): array
    {
        $factura = Factura::create([
            'venta_id' => $venta->id,
            'estado' => 'EMITIDA',
            'fecha_emision' => now(),
            'impuestos' => 0,
            'numero_factura' => "FAC-{$venta->id}-{$pago->id}",
            'subtotal' => $pago->monto,
            'total' => $pago->monto,
            'metodo_pago' => $pago->metodo_pago,
            'numero_cuota' => $pago->numero_cuota,
        ]);

        $totalPagado = (float) $venta->pagos()->where('estado', 'REGISTRADO')->sum('monto');
        $venta->estado = $totalPagado >= (float) $venta->total_final ? 'PAGADA' : 'PARCIAL';
        $venta->save();

        return [
            'pago' => $pago,
            'factura' => $factura,
            'estado_venta' => $venta->estado,
        ];
    }
}
