<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Inventario;
use App\Models\MetodoPago;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Recurso;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------- Recursos (módulos del menú dinámico) ---------- */
        $recursos = [
            ['clave' => 'usuarios', 'nombre' => 'Usuarios', 'icono' => 'Users', 'ruta' => 'usuarios', 'orden' => 1],
            ['clave' => 'catalogo', 'nombre' => 'Catálogo', 'icono' => 'Package', 'ruta' => 'catalogo', 'orden' => 2],
            ['clave' => 'almacenes', 'nombre' => 'Almacenes', 'icono' => 'Warehouse', 'ruta' => 'almacenes', 'orden' => 3],
            ['clave' => 'inventario', 'nombre' => 'Inventario', 'icono' => 'Boxes', 'ruta' => 'inventario', 'orden' => 4],
            ['clave' => 'cotizaciones', 'nombre' => 'Cotizaciones', 'icono' => 'FileText', 'ruta' => 'cotizaciones', 'orden' => 5],
            ['clave' => 'encomiendas', 'nombre' => 'Encomiendas', 'icono' => 'Truck', 'ruta' => 'encomiendas', 'orden' => 6],
            ['clave' => 'ventas', 'nombre' => 'Ventas', 'icono' => 'ShoppingCart', 'ruta' => 'ventas', 'orden' => 7],
            ['clave' => 'pagos', 'nombre' => 'Pagos', 'icono' => 'CreditCard', 'ruta' => 'pagos', 'orden' => 8],
            ['clave' => 'facturas', 'nombre' => 'Facturas', 'icono' => 'Receipt', 'ruta' => 'facturas', 'orden' => 9],
            ['clave' => 'reportes', 'nombre' => 'Reportes', 'icono' => 'BarChart3', 'ruta' => 'reportes', 'orden' => 10],
            ['clave' => 'estadisticas', 'nombre' => 'Estadísticas', 'icono' => 'PieChart', 'ruta' => 'estadisticas', 'orden' => 11],
            ['clave' => 'permisos', 'nombre' => 'Matriz de Acceso', 'icono' => 'Shield', 'ruta' => 'permisos', 'orden' => 12],
            ['clave' => 'bitacora', 'nombre' => 'Bitácora', 'icono' => 'ScrollText', 'ruta' => 'bitacora', 'orden' => 13],
        ];
        $recObj = [];
        foreach ($recursos as $r) {
            $recObj[$r['clave']] = Recurso::updateOrCreate(['clave' => $r['clave']], $r);
        }

        /* ---------- Matriz de permisos (rol × recurso) ---------- */
        // [ver, crear, editar, eliminar]
        $matriz = [
            'admin' => [
                'usuarios' => [1, 1, 1, 1], 'catalogo' => [1, 1, 1, 1], 'almacenes' => [1, 1, 1, 1],
                'inventario' => [1, 1, 1, 1], 'cotizaciones' => [1, 1, 1, 1], 'encomiendas' => [1, 1, 1, 0],
                'ventas' => [1, 1, 0, 0], 'pagos' => [1, 1, 0, 0], 'facturas' => [1, 0, 0, 0],
                'reportes' => [1, 0, 0, 0], 'estadisticas' => [1, 0, 0, 0], 'permisos' => [1, 0, 1, 0], 'bitacora' => [1, 0, 0, 0],
            ],
            'vendedor' => [
                'usuarios' => [0, 0, 0, 0], 'catalogo' => [1, 0, 0, 0], 'almacenes' => [0, 0, 0, 0],
                'inventario' => [1, 1, 0, 0], 'cotizaciones' => [1, 1, 1, 0], 'encomiendas' => [1, 1, 1, 0],
                'ventas' => [1, 1, 0, 0], 'pagos' => [1, 1, 0, 0], 'facturas' => [1, 0, 0, 0],
                'reportes' => [1, 0, 0, 0], 'estadisticas' => [0, 0, 0, 0], 'permisos' => [0, 0, 0, 0], 'bitacora' => [0, 0, 0, 0],
            ],
            'cliente' => [
                'usuarios' => [0, 0, 0, 0], 'catalogo' => [0, 0, 0, 0], 'almacenes' => [0, 0, 0, 0],
                'inventario' => [0, 0, 0, 0], 'cotizaciones' => [1, 0, 0, 0], 'encomiendas' => [1, 0, 0, 0],
                'ventas' => [0, 0, 0, 0], 'pagos' => [1, 1, 0, 0], 'facturas' => [1, 0, 0, 0],
                'reportes' => [0, 0, 0, 0], 'estadisticas' => [0, 0, 0, 0], 'permisos' => [0, 0, 0, 0], 'bitacora' => [0, 0, 0, 0],
            ],
        ];
        foreach ($matriz as $rol => $recs) {
            foreach ($recs as $clave => $p) {
                Permiso::updateOrCreate(
                    ['rol' => $rol, 'recurso_id' => $recObj[$clave]->id],
                    ['ver' => (bool) $p[0], 'crear' => (bool) $p[1], 'editar' => (bool) $p[2], 'eliminar' => (bool) $p[3]]
                );
            }
        }

        /* ---------- Usuarios demo (seed inicial admin garantizado) ---------- */
        $admin = Usuario::updateOrCreate(['correo' => 'admin@servicargo.bo'], [
            'ci' => '1000001', 'nombre' => 'Ana', 'apellido' => 'Admin',
            'contrasena' => Hash::make('password'), 'rol' => 'admin', 'telefono' => '70000001',
        ]);
        $vendedor = Usuario::updateOrCreate(['correo' => 'vendedor@servicargo.bo'], [
            'ci' => '1000002', 'nombre' => 'Victor', 'apellido' => 'Vendedor',
            'contrasena' => Hash::make('password'), 'rol' => 'vendedor', 'telefono' => '70000002',
        ]);
        $cliente = Usuario::updateOrCreate(['correo' => 'cliente@servicargo.bo'], [
            'ci' => '1000003', 'nombre' => 'Carla', 'apellido' => 'Cliente',
            'contrasena' => Hash::make('password'), 'rol' => 'cliente', 'telefono' => '70000003',
        ]);

        /* ---------- Catálogo demo ---------- */
        $catGeneral = Categoria::updateOrCreate(['nombre' => 'Paquetería General'], ['descripcion' => 'Envíos estándar']);
        $catEspecial = Categoria::updateOrCreate(['nombre' => 'Carga Especial'], ['descripcion' => 'Frágil / peligrosa']);

        $p1 = Producto::updateOrCreate(['codigo' => 'ENV-STD'], [
            'categoria_id' => $catGeneral->id, 'nombre' => 'Envío estándar (hasta 5 kg)',
            'descripcion' => 'Encomienda general', 'precio_unitario' => 35.00, 'tipo' => 'carga_general',
        ]);
        $p2 = Producto::updateOrCreate(['codigo' => 'ENV-FRAG'], [
            'categoria_id' => $catEspecial->id, 'nombre' => 'Envío frágil',
            'descripcion' => 'Manejo con cuidado', 'precio_unitario' => 60.00, 'tipo' => 'fragil',
        ]);
        $p3 = Producto::updateOrCreate(['codigo' => 'ENV-PERE'], [
            'categoria_id' => $catEspecial->id, 'nombre' => 'Envío refrigerado',
            'descripcion' => 'Cadena de frío', 'precio_unitario' => 90.00, 'tipo' => 'perecedera',
        ]);

        /* ---------- Almacenes + inventario ---------- */
        $alm1 = Almacen::updateOrCreate(['nombre' => 'Almacén Central SCZ'], [
            'direccion' => 'Av. Cristo Redentor 4to anillo', 'capacidad' => 1000, 'responsable_id' => $vendedor->id,
        ]);
        $alm2 = Almacen::updateOrCreate(['nombre' => 'Almacén La Paz'], [
            'direccion' => 'El Alto, zona industrial', 'capacidad' => 600, 'responsable_id' => $admin->id,
        ]);
        Inventario::updateOrCreate(['producto_id' => $p1->id, 'almacen_id' => $alm1->id], ['cantidad' => 120, 'stock_minimo' => 20]);
        Inventario::updateOrCreate(['producto_id' => $p2->id, 'almacen_id' => $alm1->id], ['cantidad' => 15, 'stock_minimo' => 10]);
        Inventario::updateOrCreate(['producto_id' => $p3->id, 'almacen_id' => $alm2->id], ['cantidad' => 8, 'stock_minimo' => 10]);

        /* ---------- Cotización demo (PENDIENTE) ---------- */
        if (! Cotizacion::where('cliente_id', $cliente->id)->exists()) {
            $cot = Cotizacion::create([
                'cliente_id' => $cliente->id, 'vendedor_id' => $vendedor->id, 'estado' => 'PENDIENTE',
                'fecha_emision' => now(), 'remitente' => 'Carla Cliente', 'destinatario' => 'Juan Perez',
                'contenido' => 'Documentos', 'origen' => 'Santa Cruz', 'destino' => 'La Paz',
                'tipo_envio' => 'terrestre', 'peso_kg' => 3.5, 'volumen_m3' => 0.02,
                'fecha_entrega_estimada' => now()->addDays(3), 'impuestos' => 5.00,
                'subtotal' => 35.00, 'total_estimado' => 40.00, 'validez_dias' => 5,
            ]);
            DetalleCotizacion::create([
                'cotizacion_id' => $cot->id, 'producto_id' => $p1->id,
                'cantidad' => 1, 'precio_unitario' => 35.00, 'subtotal' => 35.00,
            ]);
        }

        /* ---------- Métodos de pago registrados (demo) ---------- */
        MetodoPago::firstOrCreate(
            ['usuario_id' => $cliente->id, 'alias' => 'Mi QR personal'],
            ['tipo' => 'QR', 'referencia' => 'qr-carla', 'activo' => true]
        );
        MetodoPago::firstOrCreate(
            ['usuario_id' => $vendedor->id, 'alias' => 'Caja efectivo sucursal'],
            ['tipo' => 'EFECTIVO', 'referencia' => 'caja-1', 'activo' => true]
        );

        // Datos de prueba para reportes y estadísticas (idempotente).
        $this->call(DemoDatosSeeder::class);
    }
}
