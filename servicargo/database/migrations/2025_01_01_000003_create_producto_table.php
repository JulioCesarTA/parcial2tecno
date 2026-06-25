<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categoria');
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->decimal('precio_unitario', 10, 2);
            $table->string('tipo', 30); // carga_general | fragil | perecedera | peligrosa
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
