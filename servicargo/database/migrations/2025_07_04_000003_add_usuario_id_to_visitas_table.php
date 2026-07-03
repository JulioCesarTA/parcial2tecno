<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada visita queda ligada a su usuario (antes era un contador global por
 * página, sin dueño). usuario_id es nullable porque /visitas/{pagina} también
 * lo llaman visitantes anónimos (landing pública, antes de iniciar sesión).
 * nullOnDelete: si se borra el usuario, se conserva el historial de visitas
 * para no perder la estadística, solo se desvincula del usuario borrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->dropUnique(['pagina']);
            $table->foreignId('usuario_id')->nullable()->after('id')
                ->constrained('usuario')->nullOnDelete();
            $table->unique(['usuario_id', 'pagina']);
        });
    }

    public function down(): void
    {
        Schema::table('visitas', function (Blueprint $table) {
            $table->dropUnique(['usuario_id', 'pagina']);
            $table->dropConstrainedForeignId('usuario_id');
            $table->unique('pagina');
        });
    }
};
