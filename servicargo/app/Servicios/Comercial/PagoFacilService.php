<?php

namespace App\Servicios\Comercial;

use App\Servicios\ErrorDominio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la pasarela PagoFácil (masterqr).
 *
 * Flujo: login (token) → generate-qr → query-transaction (paymentStatus).
 * paymentStatus == 2 significa "Pagado". El token se cachea hasta su expiración.
 * Todas las credenciales viven en config/services.php (leídas de .env).
 */
class PagoFacilService
{
    private string $baseUrl;

    private bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.pagofacil.base_url'), '/');
        $this->verifySsl = (bool) config('services.pagofacil.verify_ssl', true);
    }

    /** Genera un QR de cobro. Devuelve qr_base64, transaction_id y expiration_date. */
    public function generarQr(array $datos): array
    {
        $body = [
            'paymentMethod' => (string) config('services.pagofacil.payment_method_id'),
            'clientName' => $datos['clientName'] ?? 'Cliente',
            'documentType' => (int) config('services.pagofacil.document_type'),
            'documentId' => (string) ($datos['documentId'] ?? ''),
            'phoneNumber' => (string) ($datos['phoneNumber'] ?? ''),
            'email' => (string) ($datos['email'] ?? ''),
            'paymentNumber' => (string) ($datos['paymentNumber'] ?? ''),
            'amount' => round((float) $datos['amount'], 2),
            'currency' => (int) config('services.pagofacil.currency'),
            'clientCode' => (string) ($datos['clientCode'] ?? ''),
            'callbackUrl' => (string) config('services.pagofacil.callback_url'),
            'orderDetail' => [[
                'serial' => 1,
                'product' => $datos['product'] ?? 'Pago Servicargo',
                'quantity' => 1,
                'price' => round((float) $datos['amount'], 2),
                'discount' => 0,
                'total' => round((float) $datos['amount'], 2),
            ]],
        ];

        $data = $this->postConToken('/generate-qr', $body);
        // La pasarela envuelve los datos útiles dentro de "values".
        $values = $data['values'] ?? $data;

        $qrBase64 = $values['qrBase64'] ?? null;
        if (empty($qrBase64)) {
            Log::warning('PagoFácil generate-qr sin qrBase64', ['respuesta' => $data]);
            throw new ErrorDominio('PagoFácil no devolvió el código QR.', 502);
        }

        return [
            'qr_base64' => $qrBase64,
            'transaction_id' => isset($values['transactionId']) ? (string) $values['transactionId'] : null,
            'expiration_date' => $values['expirationDate'] ?? null,
        ];
    }

    /**
     * Consulta el estado de la transacción. Devuelve el paymentStatus (int),
     * o null si no se pudo determinar. 2 = Pagado.
     */
    public function consultarTransaccion(?string $transactionId, ?string $companyTransactionId = null): ?int
    {
        $body = ! empty($transactionId)
            ? ['pagofacilTransactionId' => (int) $transactionId]
            : ['companyTransactionId' => (string) $companyTransactionId];

        $data = $this->postConToken('/query-transaction', $body);
        // La respuesta útil viene dentro de "values".
        $values = $data['values'] ?? $data;
        $estado = $values['paymentStatus'] ?? null;

        return $estado === null ? null : (int) $estado;
    }

    /** POST autenticado con reintento si el token expiró (401/403). */
    private function postConToken(string $ruta, array $body): array
    {
        $token = $this->token();
        $resp = Http::withToken($token)
            ->withOptions(['verify' => $this->verifySsl])
            ->acceptJson()
            ->timeout(20)
            ->post($this->baseUrl . $ruta, $body);

        if ($resp->status() === 401 || $resp->status() === 403) {
            $token = $this->token(forzar: true);
            $resp = Http::withToken($token)
                ->withOptions(['verify' => $this->verifySsl])
                ->acceptJson()
                ->timeout(20)
                ->post($this->baseUrl . $ruta, $body);
        }

        if ($resp->failed()) {
            Log::error('PagoFácil error HTTP', ['ruta' => $ruta, 'status' => $resp->status(), 'body' => $resp->body()]);
            throw new ErrorDominio('No se pudo comunicar con PagoFácil (HTTP ' . $resp->status() . ').', 502);
        }

        return $resp->json() ?? [];
    }

    /** Obtiene un accessToken (cacheado). Con $forzar renueva aunque haya uno vigente. */
    private function token(bool $forzar = false): string
    {
        if ($forzar) {
            Cache::forget('pagofacil_token');
        }

        return Cache::remember('pagofacil_token', now()->addMinutes(25), function () {
            $servicio = config('services.pagofacil.token_service');
            $secreto = config('services.pagofacil.token_secret');
            if (empty($servicio) || empty($secreto)) {
                throw new ErrorDominio('Credenciales de PagoFácil no configuradas.', 500);
            }

            $resp = Http::withHeaders([
                'tcTokenService' => $servicio,
                'tcTokenSecret' => $secreto,
            ])->withOptions(['verify' => $this->verifySsl])
                ->acceptJson()->timeout(20)->post($this->baseUrl . '/login', (object) []);

            if ($resp->failed()) {
                Log::error('PagoFácil login falló', ['status' => $resp->status(), 'body' => $resp->body()]);
                throw new ErrorDominio('No se pudo autenticar con PagoFácil.', 502);
            }

            $data = $resp->json() ?? [];
            $token = $data['values']['accessToken'] ?? $data['accessToken'] ?? null;
            if (empty($token)) {
                throw new ErrorDominio('PagoFácil no devolvió accessToken.', 502);
            }

            return $token;
        });
    }
}
