<?php

namespace App\Servicios\Comercial;

use App\Models\Factura;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Venta;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    /**
     * Margen extra tras el vencimiento nominal del QR durante el cual seguimos
     * verificando el pago con PagoFácil antes de darlo por perdido. Cubre
     * pequeños desfases entre nuestro reloj y el de la pasarela, y transferencias
     * que el banco confirma unos minutos después de mostrado el QR.
     */
    private const GRACIA_EXPIRACION_MINUTOS = 15;

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

    /**
     * Genera el QR real de PagoFácil y crea el pago en estado PENDIENTE.
     *
     * Idea clave: PagoFácil registra cada QR con un código propio y no permite
     * repetirlo. Antes mandábamos el código de la venta ("SaTecno-6") en cada QR,
     * así que no se podía regenerar cuando vencía. Ahora cada QR lleva un código
     * único ("SaTecno-6-12" = código de venta + id del pago). Si el usuario pide
     * un QR nuevo, marcamos el anterior como EXPIRADO (queda superado) pero la
     * venta sigue viva: puede volver a intentar cuantas veces quiera sin límite
     * de tiempo, hasta completar el pago.
     */
    private function generarPagoQr(Venta $venta, array $datos, Usuario $actor): array
    {
        $cliente = $venta->cliente;
        if (! $cliente || empty($cliente->correo)) {
            throw new ErrorDominio('El cliente de la venta no tiene correo para generar el QR.', 422);
        }

        // El QR anterior (si lo hay) queda superado por el nuevo.
        Pago::where('venta_id', $venta->id)
            ->where('metodo_pago', 'QR')
            ->where('estado', 'PENDIENTE')
            ->when(
                isset($datos['numero_cuota']),
                fn ($q) => $q->where('numero_cuota', $datos['numero_cuota'])
            )
            ->update(['estado' => 'EXPIRADO']);

        // Creamos el pago primero para tener el id y armar un código de QR único.
        $pago = Pago::create([
            'venta_id' => $venta->id,
            'estado' => 'PENDIENTE',
            'fecha_pago' => now(),
            'metodo_pago' => 'QR',
            'monto' => $datos['monto'],
            'numero_cuota' => $datos['numero_cuota'] ?? null,
            'referencia' => $datos['referencia'] ?? 'PAGOFACIL_QR',
        ]);

        $codigoPasarela = $venta->codigo . '-' . $pago->id;

        try {
            $qr = $this->pagoFacil->generarQr([
                'clientName' => trim($cliente->nombre . ' ' . $cliente->apellido) ?: 'Cliente',
                'documentId' => (string) $cliente->ci,
                'phoneNumber' => (string) $cliente->telefono,
                'email' => $cliente->correo,
                'paymentNumber' => $codigoPasarela,   // companyTransactionId (PedidoID) único por QR
                'clientCode' => (string) $cliente->id,
                'amount' => $datos['monto'],
                'product' => 'Pago venta ' . $venta->codigo,
            ]);
        } catch (\Throwable $e) {
            $pago->delete(); // no quedó QR: no dejamos un pago huérfano
            throw $e;
        }

        $pago->update([
            'codigo_pasarela' => $codigoPasarela,
            'transaccion_id' => $qr['transaction_id'],
            'expira_en' => $qr['expiration_date'],
            'qr_base64' => $qr['qr_base64'],
        ]);

        BitacoraService::registrar($actor->id, 'accion', 'pagos', "QR generado venta #{$venta->id} ({$codigoPasarela})");

        return [
            'message' => 'QR generado. Escanéalo para completar el pago.',
            'pago_id' => $pago->id,
            'qr_base64' => $qr['qr_base64'],
            'transaccion_id' => $qr['transaction_id'],
            'expira_en' => $qr['expiration_date'],
        ];
    }

    /**
     * Devuelve el QR pendiente más reciente de una venta (para volver a mostrarlo
     * si el usuario cerró el modal). null si no hay ninguno vigente.
     *
     * PagoFácil puede anular un QR (paymentStatus 4) sin que nuestro reloj lo note
     * — el usuario cierra el modal, el scheduler de reconciliación (cada minuto)
     * todavía no pasó, y si solo mirásemos nuestro estado local le mostraríamos un
     * QR ya inservible. Por eso, antes de devolverlo, consultamos el estado real
     * en la pasarela y lo confirmamos/anulamos en el momento si corresponde.
     */
    public function qrActivoDeVenta(int|string $ventaId, Usuario $actor): ?array
    {
        $venta = Venta::find($ventaId);
        if (! $venta) {
            throw new ErrorDominio('Venta no encontrada.', 404);
        }
        if ($actor->esCliente() && $venta->cliente_id !== $actor->id) {
            throw new ErrorDominio('No puedes ver una venta ajena.', 403);
        }

        $pago = Pago::where('venta_id', $venta->id)
            ->where('metodo_pago', 'QR')
            ->where('estado', 'PENDIENTE')
            ->whereNotNull('qr_base64')
            ->orderByDesc('id')->first();
        if (! $pago) {
            return null;
        }

        if ($this->estaVencido($pago)) {
            $pago->estado = 'EXPIRADO';
            $pago->save();

            return null;
        }

        try {
            $status = $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $pago->codigo_pasarela);
        } catch (\Throwable $e) {
            // Si la pasarela no responde, mostramos el QR guardado igual: el
            // polling normal ya lo reintentará.
            $status = null;
        }

        if ($status === 2) {
            $this->confirmarPago($pago);

            return null; // ya se pagó: el frontend recargará y no verá saldo pendiente
        }
        if ($status === 4) {
            $pago->estado = 'ANULADO';
            $pago->save();

            return null; // anulado en PagoFácil: no sirve, hay que generar uno nuevo
        }

        return [
            'pago_id' => $pago->id,
            'qr_base64' => $pago->qr_base64,
            'expira_en' => $pago->expira_en,
            'monto' => $pago->monto,
            'numero_cuota' => $pago->numero_cuota,
            'vencido' => false,
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

        if ($pago->estado !== 'PENDIENTE' || $pago->metodo_pago !== 'QR') {
            return [
                'estado' => $pago->estado,
                'pagado' => $pago->estado === 'REGISTRADO',
                'estado_venta' => $pago->venta->estado,
            ];
        }

        $status = $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $pago->codigo_pasarela);

        if ($status === 2) {
            $resultado = $this->confirmarPago($pago);

            return [
                'estado' => 'REGISTRADO',
                'pagado' => true,
                'message' => 'Pago QR confirmado.',
                'factura' => $resultado['factura'],
                'estado_venta' => $resultado['estado_venta'],
            ];
        }
        if ($status === 4) {
            $pago->estado = 'ANULADO';
            $pago->save();

            return ['estado' => 'ANULADO', 'pagado' => false, 'estado_venta' => $pago->venta->estado];
        }
        if ($this->estaVencido($pago)) {
            $pago->estado = 'EXPIRADO';
            $pago->save();

            return ['estado' => 'EXPIRADO', 'pagado' => false, 'estado_venta' => $pago->venta->estado];
        }

        return ['estado' => 'PENDIENTE', 'pagado' => false, 'estado_venta' => $pago->venta->estado];
    }

    private function estaVencido(Pago $pago): bool
    {
        return $pago->expira_en
            && now()->greaterThan($pago->expira_en->clone()->addMinutes(self::GRACIA_EXPIRACION_MINUTOS));
    }

    /**
     * Confirmación disparada por el callback de PagoFácil. El PedidoID
     * (companyTransactionId) es el código único del QR (codigo_pasarela), así que
     * ubicamos el pago exacto. Se mantiene compatibilidad con callbacks antiguos
     * que mandaban el código de la venta. Devuelve true si el pago quedó registrado.
     */
    public function confirmarPorCallback(string $companyTransactionId, ?int $estado = null): bool
    {
        $pago = Pago::with('venta')
            ->where('codigo_pasarela', $companyTransactionId)
            ->where('metodo_pago', 'QR')
            ->orderByDesc('id')->first();

        // Compatibilidad: callbacks antiguos con el código de la venta.
        if (! $pago) {
            $venta = Venta::where('codigo', $companyTransactionId)->first();
            if (! $venta) {
                return false;
            }
            $pago = Pago::with('venta')
                ->where('venta_id', $venta->id)
                ->where('metodo_pago', 'QR')
                ->where('estado', 'PENDIENTE')
                ->orderByDesc('id')->first();
        }
        if (! $pago) {
            return false;
        }
        if ($pago->estado === 'REGISTRADO') {
            return true; // ya estaba confirmado (callback duplicado)
        }

        if ($estado === 4) {
            $pago->estado = 'ANULADO';
            $pago->save();

            return false;
        }

        // Confiamos en Estado==2 del callback; si no vino, verificamos con la pasarela.
        $pagado = $estado === 2
            || $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $pago->codigo_pasarela) === 2;
        if (! $pagado) {
            return false;
        }

        $this->confirmarPago($pago);
        BitacoraService::registrar(null, 'accion', 'pagos', "Callback PagoFácil venta #{$pago->venta_id}");

        return true;
    }

    /**
     * Reconciliación en segundo plano (independiente de cualquier navegador abierto).
     * Recorre todos los pagos QR PENDIENTE y consulta su estado real en PagoFácil:
     *  2=Pagado → confirma y factura; 4=Anulado → marca ANULADO; 5=Revisión → deja
     *  pendiente; sin novedad y vencido → marca EXPIRADO. Pensado para correr cada
     *  minuto vía el scheduler (routes/console.php), igual que QrPaymentMonitor.
     */
    public function verificarPendientes(): array
    {
        $pendientes = Pago::with('venta')
            ->where('metodo_pago', 'QR')
            ->where('estado', 'PENDIENTE')
            ->get();

        $resumen = ['confirmados' => 0, 'anulados' => 0, 'expirados' => 0, 'sinCambio' => 0, 'errores' => 0];

        foreach ($pendientes as $pago) {
            try {
                $status = $this->pagoFacil->consultarTransaccion($pago->transaccion_id, $pago->codigo_pasarela);

                if ($status === 2) {
                    $this->confirmarPago($pago);
                    $resumen['confirmados']++;
                    continue;
                }
                if ($status === 4) {
                    $pago->estado = 'ANULADO';
                    $pago->save();
                    $resumen['anulados']++;
                    continue;
                }
                if ($status === 5) {
                    // En revisión por PagoFácil: no tocamos el pago todavía.
                    $resumen['sinCambio']++;
                    continue;
                }
                if ($this->estaVencido($pago)) {
                    $pago->estado = 'EXPIRADO';
                    $pago->save();
                    $resumen['expirados']++;
                    continue;
                }
                $resumen['sinCambio']++;
            } catch (\Throwable $e) {
                $resumen['errores']++;
                Log::error("Error verificando pago QR pendiente #{$pago->id}: " . $e->getMessage());
            }
        }

        return $resumen;
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

        $estadoVenta = $this->actualizarEstadoVenta($venta);

        return [
            'pago' => $pago,
            'factura' => $factura,
            'estado_venta' => $estadoVenta,
        ];
    }

    /**
     * Recalcula el estado de la venta según lo efectivamente pagado (pagos
     * REGISTRADO) y su forma de pago:
     *  - CONTADO: PENDIENTE → PAGADA (no existe PARCIAL, no hay cuotas).
     *  - CREDITO: PENDIENTE → PARCIAL (pagó algo) → PAGADA (saldó todo).
     */
    private function actualizarEstadoVenta(Venta $venta): string
    {
        $totalPagado = (float) $venta->pagos()->where('estado', 'REGISTRADO')->sum('monto');
        $total = (float) $venta->total_final;

        if ($totalPagado + 0.001 >= $total) {
            $venta->estado = 'PAGADA';
        } elseif ($venta->tipo_pago === 'CREDITO' && $totalPagado > 0) {
            $venta->estado = 'PARCIAL';
        } else {
            $venta->estado = 'PENDIENTE';
        }
        $venta->save();

        return $venta->estado;
    }
}
