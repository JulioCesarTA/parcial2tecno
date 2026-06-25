<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('venta');
            $table->string('estado', 30)->default('REGISTRADO');
            $table->timestamp('fecha_pago');
            $table->string('metodo_pago', 30); // EFECTIVO|QR
            $table->decimal('monto', 10, 2);
            $table->integer('numero_cuota')->nullable();
            $table->string('referencia', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
