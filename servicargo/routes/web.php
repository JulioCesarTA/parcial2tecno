<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// web.php SOLO renderiza páginas Inertia. Toda la lógica vive en api.php.

Route::get('/', fn () => Inertia::render('Landing'))->name('landing');
Route::get('/login', fn () => Inertia::render('Login'))->name('login');
Route::get('/registro', fn () => Inertia::render('Register'))->name('register');

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
