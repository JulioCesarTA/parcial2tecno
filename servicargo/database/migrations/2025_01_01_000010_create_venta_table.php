<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('cliente_id')->constrained('usuario');
            $table->foreignId('vendedor_id')->constrained('usuario');
            $table->foreignId('encomienda_id')->constrained('encomienda');
            $table->string('estado', 30)->default('PENDIENTE'); // PENDIENTE|PARCIAL|PAGADA
            $table->timestamp('fecha_venta');
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total_final', 10, 2);
            $table->string('tipo_pago', 30); // CONTADO|CREDITO
            $table->integer('numero_cuotas')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta');
    }
};
