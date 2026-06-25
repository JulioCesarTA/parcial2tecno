<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encomienda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('usuario');
            $table->foreignId('cotizacion_id')->constrained('cotizacion');
            $table->string('guia_rastreo', 60)->unique();
            $table->string('remitente', 120);
            $table->string('destinatario', 120);
            $table->text('contenido');
            $table->string('origen', 120);
            $table->string('destino', 120);
            $table->string('tipo_envio', 30);
            $table->decimal('peso_kg', 10, 2);
            $table->decimal('volumen_m3', 10, 2);
            $table->string('estado', 30)->default('REGISTRADA');
            $table->timestamp('fecha_registro');
            $table->timestamp('fecha_entrega_estimada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encomienda');
    }
};
