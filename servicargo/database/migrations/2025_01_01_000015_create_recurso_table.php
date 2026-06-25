<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurso', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50)->unique(); // usuarios, catalogo, ...
            $table->string('nombre', 100);
            $table->string('icono', 50)->nullable();
            $table->string('ruta', 100)->nullable();
            $table->integer('orden')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurso');
    }
};
