<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('usuario');
            $table->foreignId('vendedor_id')->constrained('usuario');
            $table->string('estado', 30)->default('PENDIENTE'); // PENDIENTE|APROBADA|RECHAZADA|VENCIDA|COMPLETADA
            $table->timestamp('fecha_emision');
            $table->string('remitente', 120);
            $table->string('destinatario', 120);
            $table->text('contenido');
            $table->string('origen', 120);
            $table->string('destino', 120);
            $table->string('tipo_envio', 30); // aereo|maritimo|terrestre
            $table->decimal('peso_kg', 10, 2);
            $table->decimal('volumen_m3', 10, 2);
            $table->timestamp('fecha_entrega_estimada')->nullable();
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total_estimado', 10, 2)->default(0);
            $table->integer('validez_dias'); // 1..7
            // Soporte ventana 20 min de encomienda
            $table->timestamp('fecha_aprobacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion');
    }
};
