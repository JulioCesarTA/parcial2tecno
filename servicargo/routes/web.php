<?php

use App\Http\Controllers\PagoController;
use App\Http\Controllers\SessionAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Páginas Inertia. La autenticación por sesión vive acá (idiomática);
// la lógica de negocio de los módulos aún se sirve por api.php (migración en curso).

// PagoFácil: callback (notificación de pago) y return (retorno del navegador).
// Públicos y exentos de CSRF (ver bootstrap/app.php); PagoFácil llama a estas URLs.
Route::post('/callback', [PagoController::class, 'callback'])->name('pagofacil.callback');
Route::match(['get', 'post'], '/return', [PagoController::class, 'retorno'])->name('pagofacil.return');

Route::get('/', fn () => Inertia::render('Landing'))->name('landing');
Route::get('/login', fn () => Inertia::render('Login'))->name('login');
Route::get('/registro', fn () => Inertia::render('Register'))->name('register');

// Auth por sesión (Inertia idiomático)
Route::post('/login', [SessionAuthController::class, 'login'])->name('login.post');
Route::post('/registro', [SessionAuthController::class, 'register'])->name('registro.post');
Route::post('/logout', [SessionAuthController::class, 'logout'])->name('logout');

Route::get('/inicio', fn () => Inertia::render('Inicio'))->name('inicio');
Route::get('/usuarios', fn () => Inertia::render('Usuarios'))->name('usuarios');
Route::get('/catalogo', fn () => Inertia::render('Catalogo'))->name('catalogo');
Route::get('/almacenes', fn () => Inertia::render('Almacenes'))->name('almacenes');
Route::get('/inventario', fn () => Inertia::render('Inventario'))->name('inventario');
Route::get('/cotizaciones', fn () => Inertia::render('Cotizaciones'))->name('cotizaciones');
Route::get('/encomiendas', fn () => Inertia::render('Encomiendas'))->name('encomiendas');
Route::get('/ventas', fn () => Inertia::render('Ventas'))->name('ventas');
Route::get('/pagos', fn () => Inertia::render('Pagos'))->name('pagos');
Route::get('/facturas', fn () => Inertia::render('Facturas'))->name('facturas');
Route::get('/reportes', fn () => Inertia::render('Reportes'))->name('reportes');
Route::get('/permisos', fn () => Inertia::render('Permisos'))->name('permisos');
Route::get('/bitacora', fn () => Inertia::render('Bitacora'))->name('bitacora');
