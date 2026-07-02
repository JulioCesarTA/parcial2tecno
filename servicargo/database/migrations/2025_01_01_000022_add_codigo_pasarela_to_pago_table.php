<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada QR de PagoFácil se registra con un código único (companyTransactionId).
 * Antes reutilizábamos el código de la venta ("SaTecno-6") en cada QR, así que
 * la pasarela rechazaba regenerar. Ahora cada pago QR lleva su propio
 * codigo_pasarela ("SaTecno-6-12") y guardamos el qr_base64 para poder volver a
 * mostrar el QR sin pedir uno nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->string('codigo_pasarela', 60)->nullable()->after('transaccion_id');
            $table->text('qr_base64')->nullable()->after('codigo_pasarela');
        });

        // Corrige ventas CONTADO que la lógica anterior dejó como PARCIAL (una
        // venta al contado no tiene cuotas: solo PENDIENTE o PAGADA).
        foreach (DB::table('venta')->where('tipo_pago', 'CONTADO')->where('estado', 'PARCIAL')->get() as $venta) {
            $pagado = (float) DB::table('pago')
                ->where('venta_id', $venta->id)->where('estado', 'REGISTRADO')->sum('monto');
            $estado = $pagado + 0.001 >= (float) $venta->total_final ? 'PAGADA' : 'PENDIENTE';
            DB::table('venta')->where('id', $venta->id)->update(['estado' => $estado]);
        }
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->dropColumn(['codigo_pasarela', 'qr_base64']);
        });
    }
};
