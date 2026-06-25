<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propietario_id')->constrained('usuario');
            $table->timestamp('fecha_generacion');
            $table->text('parametros')->nullable(); // periodo
            $table->string('tipo_reporte', 30); // REPVEN|REPENC|REPINV|REPCOT|REPFAC
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte');
    }
};
