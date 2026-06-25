<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuario')->cascadeOnDelete();
            $table->string('tipo', 30);          // EFECTIVO | QR
            $table->string('alias', 80);         // "Mi QR personal", "Caja efectivo"
            $table->string('referencia', 60)->nullable(); // PCI-safe: alias/cuenta, nunca datos sensibles
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago');
    }
};
