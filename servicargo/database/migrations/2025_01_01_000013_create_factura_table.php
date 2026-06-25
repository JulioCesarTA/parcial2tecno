<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('venta');
            $table->string('estado', 30)->default('EMITIDA');
            $table->timestamp('fecha_emision');
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->string('numero_factura', 40)->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('metodo_pago', 30);
            $table->integer('numero_cuota')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura');
    }
};
