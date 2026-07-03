<?php

namespace Database\Seeders;

use App\Models\Cotizacion;
use App\Models\DetalleVenta;
use App\Models\Encomienda;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba para poder ver los reportes y las estadísticas con contenido.
 * Crea cadenas completas cotización → encomienda → venta → factura/pago con
 * variedad de estados, métodos de pago y fechas de los últimos 30 días.
 *
 * Idempotente: si ya existen datos demo (guías DEMO-G-*) no vuelve a insertarlos.
 */
class DemoDatosSeeder extends Seeder
{
    public function run(): void
    {
        if (Encomienda::where('guia_rastreo', 'like', 'DEMO-G-%')->exists()) {
            $this->command?->warn('Datos demo ya existen; se omite DemoDatosSeeder.');

            return;
        }

        $cliente = Usuario::where('correo', 'cliente@servicargo.bo')->first();
        $vendedor = Usuario::where('correo', 'vendedor@servicargo.bo')->first();
        $producto = Producto::first();

        if (! $cliente || ! $vendedor || ! $producto) {
            $this->command?->warn('Faltan usuarios/producto base; corre primero DatabaseSeeder.');

            return;
        }

        $ciudades = ['Santa Cruz', 'La Paz', 'Cochabamba', 'Sucre', 'Tarija', 'Oruro'];
        $nombres = ['Juan Pérez', 'María López', 'Carlos Rojas', 'Ana Gutiérrez', 'Luis Vaca', 'Sofía Áñez'];

        $cotEstados = ['APROBADA', 'COMPLETADA', 'PENDIENTE', 'RECHAZADA', 'APROBADA', 'COMPLETADA'];
        $encEstados = ['ENTREGADO', 'EN_TRANSITO', 'REGISTRADA', 'ENTREGADO', 'ENTREGADO', 'CANCELADA'];
        $ventaEstados = ['PAGADA', 'PARCIAL', 'PAGADA', 'PENDIENTE', 'PAGADA'];
        $tiposPago = ['CONTADO', 'CREDITO'];
        $metodos = ['EFECTIVO', 'QR'];

        $precio = (float) $producto->precio_unitario;

        for ($i = 1; $i <= 14; $i++) {
            $fecha = now()->subDays(rand(0, 29))->setTime(rand(8, 19), rand(0, 59));
            $cantidad = rand(1, 5);
            $subtotal = round($precio * $cantidad, 2);
            $impuestos = round($subtotal * 0.13, 2);
            $total = round($subtotal + $impuestos, 2);

            $origen = $ciudades[array_rand($ciudades)];
            $destino = $ciudades[array_rand($ciudades)];
            $destinatario = $nombres[array_rand($nombres)];

            // 1) Cotización
            $cot = Cotizacion::create([
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'estado' => $cotEstados[$i % count($cotEstados)],
                'fecha_emision' => $fecha,
                'remitente' => 'Carla Cliente',
                'destinatario' => $destinatario,
                'contenido' => 'Encomienda demo #' . $i,
                'origen' => $origen,
                'destino' => $destino,
                'tipo_envio' => 'terrestre',
                'peso_kg' => rand(1, 30),
                'volumen_m3' => round(rand(1, 50) / 100, 2),
                'fecha_entrega_estimada' => (clone $fecha)->addDays(3),
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'total_estimado' => $total,
                'validez_dias' => 7,
            ]);

            // Solo una cotización APROBADA/COMPLETADA puede tener encomienda (regla
            // real de EncomiendaService::crear). RECHAZADA y PENDIENTE quedan solo
            // como cotización, para no dejar una PENDIENTE con encomienda enganchada.
            if (! in_array($cot->estado, ['APROBADA', 'COMPLETADA'], true)) {
                continue;
            }

            // 2) Encomienda
            $enc = Encomienda::create([
                'cliente_id' => $cliente->id,
                'cotizacion_id' => $cot->id,
                'guia_rastreo' => sprintf('DEMO-G-%03d', $i),
                'remitente' => 'Carla Cliente',
                'destinatario' => $destinatario,
                'contenido' => 'Encomienda demo #' . $i,
                'origen' => $origen,
                'destino' => $destino,
                'tipo_envio' => 'terrestre',
                'peso_kg' => rand(1, 30),
                'volumen_m3' => round(rand(1, 50) / 100, 2),
                'estado' => $encEstados[$i % count($encEstados)],
                'fecha_registro' => $fecha,
                'fecha_entrega_estimada' => (clone $fecha)->addDays(3),
            ]);

            if ($enc->estado === 'CANCELADA') {
                continue;
            }

            // 3) Venta
            $estadoVenta = $ventaEstados[$i % count($ventaEstados)];
            $tipoPago = $tiposPago[$i % count($tiposPago)];
            $venta = Venta::create([
                'codigo' => sprintf('DEMO-V-%03d', $i),
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'encomienda_id' => $enc->id,
                'estado' => $estadoVenta,
                'fecha_venta' => $fecha,
                'impuestos' => $impuestos,
                'subtotal' => $subtotal,
                'descuento' => 0,
                'total_final' => $total,
                'tipo_pago' => $tipoPago,
                'numero_cuotas' => $tipoPago === 'CREDITO' ? 3 : 1,
            ]);
            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
            ]);

            // 4) Factura + Pago (solo para ventas PAGADA/PARCIAL)
            if (in_array($estadoVenta, ['PAGADA', 'PARCIAL'], true)) {
                $metodo = $metodos[$i % count($metodos)];
                Factura::create([
                    'venta_id' => $venta->id,
                    'estado' => 'EMITIDA',
                    'fecha_emision' => $fecha,
                    'impuestos' => $impuestos,
                    'numero_factura' => sprintf('DEMO-F-%03d', $i),
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'metodo_pago' => $metodo,
                    'numero_cuota' => 1,
                ]);

                // PAGADA cobra el total; PARCIAL solo la mitad.
                $monto = $estadoVenta === 'PAGADA' ? $total : round($total / 2, 2);
                Pago::create([
                    'venta_id' => $venta->id,
                    'estado' => 'REGISTRADO',
                    'fecha_pago' => $fecha,
                    'metodo_pago' => $metodo,
                    'monto' => $monto,
                    'numero_cuota' => 1,
                    'referencia' => 'demo-pago-' . $i,
                ]);
            }
        }

        $this->command?->info('Datos demo creados: cotizaciones, encomiendas, ventas, facturas y pagos.');
    }
}
