<?php

use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\BuscarController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\EncomiendaController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\PreferenciaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VisitaController;
use Illuminate\Support\Facades\Route;

/* ---------- Públicas ---------- */
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/visitas/{pagina}', [VisitaController::class, 'incrementar']);

/* ---------- Autenticadas (JWT) ---------- */
Route::middleware('jwt')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

/* ---------- Autenticadas + bitácora de acceso ---------- */
Route::middleware(['jwt', 'bitacora'])->group(function () {

    // Búsqueda del negocio
    Route::get('/buscar', [BuscarController::class, 'buscar']);

    // Preferencias de tema/accesibilidad (por usuario, requisito 5)
    Route::get('/preferencias', [PreferenciaController::class, 'show']);
    Route::put('/preferencias', [PreferenciaController::class, 'update']);

    // Perfil propio (cualquier usuario autenticado edita SUS datos y su foto)
    Route::get('/perfil', [PerfilController::class, 'show']);
    Route::put('/perfil', [PerfilController::class, 'update']);
    Route::post('/perfil/foto', [PerfilController::class, 'foto']);

    // Usuarios (solo admin)
    Route::middleware('rol:admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
        Route::delete('/usuarios/{ci}', [UsuarioController::class, 'destroy']);
    });

    // Categorías
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::get('/categorias', [CategoriaController::class, 'index']);
        Route::get('/categorias/{id}', [CategoriaController::class, 'show']);
    });
    Route::middleware('rol:admin')->group(function () {
        Route::post('/categorias', [CategoriaController::class, 'store']);
        Route::put('/categorias/{id}', [CategoriaController::class, 'update']);
        Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy']);
    });

    // Productos
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::get('/productos', [ProductoController::class, 'index']);
        Route::get('/productos/{codigo}', [ProductoController::class, 'show']);
    });
    Route::middleware('rol:admin')->group(function () {
        Route::post('/productos', [ProductoController::class, 'store']);
        Route::put('/productos/{codigo}', [ProductoController::class, 'update']);
        Route::delete('/productos/{codigo}', [ProductoController::class, 'destroy']);
    });

    // Almacenes (solo admin)
    Route::middleware('rol:admin')->group(function () {
        Route::get('/almacenes', [AlmacenController::class, 'index']);
        Route::get('/almacenes/{id}', [AlmacenController::class, 'show']);
        Route::post('/almacenes', [AlmacenController::class, 'store']);
        Route::put('/almacenes/{id}', [AlmacenController::class, 'update']);
        Route::delete('/almacenes/{id}', [AlmacenController::class, 'destroy']);
    });

    // Inventario (admin, vendedor)
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::get('/inventario', [InventarioController::class, 'index']);
        Route::post('/inventario/movimiento', [InventarioController::class, 'movimiento']);
    });

    // Cotizaciones
    Route::get('/cotizaciones', [CotizacionController::class, 'index']); // cliente filtrado en controller
    Route::get('/cotizaciones/{id}', [CotizacionController::class, 'show']);
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::post('/cotizaciones', [CotizacionController::class, 'store']);
        Route::put('/cotizaciones/{id}', [CotizacionController::class, 'update']);
    });
    Route::delete('/cotizaciones/{id}', [CotizacionController::class, 'destroy'])->middleware('rol:admin');
    Route::post('/cotizaciones/{id}/aprobar', [CotizacionController::class, 'aprobar'])->middleware('rol:cliente');

    // Encomiendas
    Route::get('/encomiendas', [EncomiendaController::class, 'index']);
    Route::get('/encomiendas/{id}', [EncomiendaController::class, 'show']);
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::post('/encomiendas', [EncomiendaController::class, 'store']);
        Route::put('/encomiendas/{id}', [EncomiendaController::class, 'update']);
    });

    // Ventas
    Route::get('/ventas', [VentaController::class, 'index']);
    Route::get('/ventas/{id}', [VentaController::class, 'show']);
    Route::post('/ventas', [VentaController::class, 'store'])->middleware('rol:admin,vendedor');

    // Métodos de pago (registro por usuario)
    Route::get('/metodos-pago', [MetodoPagoController::class, 'index']);
    Route::post('/metodos-pago', [MetodoPagoController::class, 'store']);
    Route::put('/metodos-pago/{id}', [MetodoPagoController::class, 'update']);
    Route::delete('/metodos-pago/{id}', [MetodoPagoController::class, 'destroy']);

    // Pagos
    Route::get('/pagos', [PagoController::class, 'index']);
    Route::post('/pagos', [PagoController::class, 'registrar']);
    Route::get('/pagos/{pago}/estado-qr', [PagoController::class, 'estadoQr']);
    Route::get('/ventas/{venta}/qr-activo', [PagoController::class, 'qrActivo']);

    // Facturas
    Route::get('/facturas', [FacturaController::class, 'index']);
    Route::get('/facturas/{id}', [FacturaController::class, 'show']);

    // Reportes
    Route::middleware('rol:admin,vendedor')->group(function () {
        Route::get('/reportes/ventas', [ReporteController::class, 'ventas']);
        Route::get('/reportes/encomiendas', [ReporteController::class, 'encomiendas']);
        Route::get('/reportes/cotizaciones', [ReporteController::class, 'cotizaciones']);
        Route::get('/reportes/facturas', [ReporteController::class, 'facturas']);
        Route::get('/reportes/{tipo}/pdf', [ReporteController::class, 'pdf']);
    });
    Route::middleware('rol:admin')->group(function () {
        Route::get('/reportes/inventario', [ReporteController::class, 'inventario']);
        Route::get('/reportes/acceso', [ReporteController::class, 'acceso']);

        // Estadísticas del negocio (requisito 8)
        Route::get('/estadisticas', [EstadisticaController::class, 'index']);
        // Gráficos generados con JpGraph (imagen PNG por tipo)
        Route::get('/estadisticas/grafico/{tipo}', [EstadisticaController::class, 'grafico']);

        // Bitácora + matriz de acceso + visitas
        Route::get('/bitacora', [BitacoraController::class, 'index']);
        Route::get('/permisos', [PermisoController::class, 'index']);
        Route::put('/permisos', [PermisoController::class, 'update']);
        Route::get('/visitas', [VisitaController::class, 'index']);
    });
});
