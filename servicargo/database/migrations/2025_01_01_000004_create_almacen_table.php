<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacen', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('direccion', 200);
            $table->integer('capacidad');
            $table->foreignId('responsable_id')->constrained('usuario');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen');
    }
};
