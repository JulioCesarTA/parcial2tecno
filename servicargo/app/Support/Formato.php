<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Formato boliviano reutilizable para las vistas Blade de PDF (moneda Bs, fechas d/m/Y). */
class Formato
{
    public static function money(mixed $n): string
    {
        return 'Bs ' . number_format((float) $n, 2);
    }

    public static function fecha(mixed $f): string
    {
        if (! $f) {
            return '—';
        }
        try {
            return Carbon::parse($f)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $f;
        }
    }

    public static function fechaHora(mixed $f): string
    {
        if (! $f) {
            return '—';
        }
        try {
            return Carbon::parse($f)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $f;
        }
    }
}
