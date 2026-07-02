<?php

namespace App\Http\Controllers;

use App\Servicios\Comercial\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function __construct(private PagoService $pagos) {}

    public function index(Request $request)
    {
        return response()->json($this->pagos->listarPara($request->user()));
    }

    public function registrar(Request $request)
    {
        $datos = $request->validate([
            'venta_id' => ['required', 'integer', 'exists:venta,id'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'metodo_pago' => ['required', Rule::in(['EFECTIVO', 'QR'])],
            'numero_cuota' => ['nullable', 'integer', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:60'],
        ]);

        return response()->json($this->pagos->registrar($datos, $request->user()), 201);
    }

    /** Polling del frontend: consulta a PagoFácil si el QR ya fue pagado. */
    public function estadoQr(Request $request, $pago)
    {
        return response()->json($this->pagos->consultarEstadoQr($pago, $request->user()));
    }

    /** Devuelve el QR pendiente de una venta para volver a mostrarlo (o null). */
    public function qrActivo(Request $request, $venta)
    {
        return response()->json($this->pagos->qrActivoDeVenta($venta, $request->user()));
    }

    /**
     * Callback de PagoFácil (público, sin auth ni CSRF). PagoFácil hace POST aquí
     * cuando el pago se completa. Debe responder con el formato que la pasarela espera.
     */
    public function callback(Request $request)
    {
        $pedidoId = $request->input('PedidoID')
            ?? $request->input('companyTransactionId')
            ?? $request->input('paymentNumber');
        $estado = $request->input('Estado') ?? $request->input('paymentStatus');

        Log::info('Callback PagoFácil', $request->all());

        try {
            if ($pedidoId) {
                $this->pagos->confirmarPorCallback((string) $pedidoId, $estado !== null ? (int) $estado : null);
            }
        } catch (\Throwable $e) {
            Log::error('Error procesando callback PagoFácil: ' . $e->getMessage());
        }

        // Respuesta esperada por PagoFácil para dar el pago por notificado.
        return response()->json([
            'error' => 0,
            'status' => 1,
            'message' => 'Pago realizado correctamente',
            'messageMostrar' => 0,
            'messageSistema' => '',
            'values' => true,
        ]);
    }

    /** Página de retorno del navegador tras el pago (GET/POST). */
    public function retorno()
    {
        return response(<<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Completado - Servicargo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; background: #f0f4f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .box { background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,.1); max-width: 400px; width: 90%; }
        .icono { font-size: 60px; margin-bottom: 20px; }
        h1 { color: #2e7d32; margin-bottom: 10px; font-size: 24px; }
        p { color: #555; line-height: 1.6; }
        .sub { color: #888; font-size: 14px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icono">✅</div>
        <h1>¡Pago Completado!</h1>
        <p>Tu pago fue procesado correctamente.</p>
        <p>En breve verás la factura reflejada en tu cuenta.</p>
        <p class="sub">Puedes cerrar esta ventana.</p>
    </div>
</body>
</html>
HTML)->header('Content-Type', 'text/html');
    }
}
