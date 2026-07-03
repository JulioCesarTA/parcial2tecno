<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El cliente ahora puede solicitar su propia cotización sin pasar antes por un
 * asesor (la pide desde casa, con fotos del producto). En ese caso no hay
 * asesor asignado todavía: queda asignado recién cuando un admin/asesor la
 * revisa y le agrega el primer producto (ver CotizacionService::agregarProducto).
 *
 * Se usa SQL directo (no ->nullable()->change()) para no depender de
 * doctrine/dbal, que no está instalado en este proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE cotizacion ALTER COLUMN vendedor_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cotizacion ALTER COLUMN vendedor_id SET NOT NULL');
    }
};
