<?php

namespace App\Console\Commands;

use App\Servicios\Comercial\PagoService;
use Illuminate\Console\Command;

/**
 * Reconciliación en segundo plano de pagos QR pendientes con PagoFácil.
 *
 * Necesario porque el callback de PagoFácil solo llega si nuestra URL pública es
 * alcanzable desde internet (en local/desarrollo no lo es) y porque el polling del
 * frontend deja de correr en cuanto el usuario cierra el modal o la pestaña. Este
 * comando corre server-side, sin depender de ningún navegador (equivalente a
 * QrPaymentMonitor del proyecto original).
 */
class VerificarPagosQr extends Command
{
    protected $signature = 'pagos:verificar-qr';

    protected $description = 'Consulta en PagoFácil el estado de los pagos QR pendientes y los confirma/anula/expira';

    public function handle(PagoService $pagos): int
    {
        $resumen = $pagos->verificarPendientes();

        $this->info(sprintf(
            'Confirmados: %d | Anulados: %d | Expirados: %d | Sin cambio: %d | Errores: %d',
            $resumen['confirmados'],
            $resumen['anulados'],
            $resumen['expirados'],
            $resumen['sinCambio'],
            $resumen['errores'],
        ));

        return self::SUCCESS;
    }
}
