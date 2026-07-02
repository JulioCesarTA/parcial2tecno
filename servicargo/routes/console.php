<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconciliación de pagos QR pendientes con PagoFácil (ver App\Console\Commands\VerificarPagosQr).
// Requiere que el scheduler de Laravel esté corriendo (cron `schedule:run` en prod,
// o `php artisan schedule:work` en desarrollo).
Schedule::command('pagos:verificar-qr')->everyMinute()->withoutOverlapping();
