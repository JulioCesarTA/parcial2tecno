<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la transacción PagoFácil para los pagos por QR:
 * transaccion_id = pagofacilTransactionId (para consultar el estado),
 * expira_en = vencimiento del QR generado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->string('transaccion_id', 60)->nullable()->after('referencia');
            $table->timestamp('expira_en')->nullable()->after('transaccion_id');
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->dropColumn(['transaccion_id', 'expira_en']);
        });
    }
};
