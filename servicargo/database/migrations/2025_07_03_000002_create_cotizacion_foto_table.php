<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fotos del producto/paquete que el cliente adjunta al solicitar su propia cotización. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizacion')->cascadeOnDelete();
            $table->string('ruta', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_foto');
    }
};
