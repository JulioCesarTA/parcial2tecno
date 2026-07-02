<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; }

        .encabezado { border-bottom: 3px solid #1f6feb; padding-bottom: 10px; margin-bottom: 16px; }
        .encabezado h1 { color: #1f6feb; font-size: 20px; margin: 0 0 4px; }
        .encabezado .meta { color: #666; font-size: 10px; }
        .marca { float: right; text-align: right; color: #1f6feb; font-weight: bold; font-size: 14px; }

        .seccion-titulo { font-size: 13px; color: #1f2937; margin: 18px 0 8px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }

        /* Tarjetas de resumen */
        .kpis { width: 100%; margin-bottom: 4px; }
        .kpis td { padding: 4px; }
        .kpi { background: #f4f6f8; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; text-align: center; }
        .kpi .valor { font-size: 16px; font-weight: bold; color: #1f6feb; }
        .kpi .etiqueta { font-size: 9px; color: #666; text-transform: uppercase; margin-top: 2px; }

        /* Tablas */
        table.datos { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.datos th { background: #1f6feb; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
        table.datos td { padding: 5px 8px; border-bottom: 1px solid #eee; }
        table.datos tr:nth-child(even) td { background: #f9fafb; }
        .num { text-align: right; }
        .vacio { color: #999; font-style: italic; padding: 8px; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge.ok { background: #d7f5e3; color: #187a48; }
        .badge.bajo { background: #fbe0e4; color: #b32338; }
        .badge.gris { background: #e9edf2; color: #445; }

        .pie { position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; color: #999; font-size: 9px; }
    </style>
</head>
@php
    $money = fn ($n) => 'Bs ' . number_format((float) $n, 2);
    $fmt = function ($f) {
        if (! $f) return '—';
        try { return \Illuminate\Support\Carbon::parse($f)->format('d/m/Y'); }
        catch (\Throwable $e) { return (string) $f; }
    };
    $rango = $desde ? ($fmt($desde) . ' a ' . $fmt($hasta)) : 'Todo el histórico';
@endphp
<body>
    <div class="encabezado">
        <div class="marca">SERVICARGO</div>
        <h1>{{ $titulo }}</h1>
        <div class="meta">Generado el {{ $fecha }} &nbsp;·&nbsp; Periodo: {{ $rango }}</div>
    </div>

    {{-- ---------------- VENTAS ---------------- --}}
    @if ($tipo === 'ventas')
        <table class="kpis"><tr>
            <td><div class="kpi"><div class="valor">{{ $datos['total_ventas'] }}</div><div class="etiqueta">Ventas</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $money($datos['monto_total']) }}</div><div class="etiqueta">Monto total</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $money($datos['total_cobrado']) }}</div><div class="etiqueta">Cobrado</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $datos['pagadas'] }}</div><div class="etiqueta">Pagadas</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $datos['parciales'] }}</div><div class="etiqueta">Parciales</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $datos['pendientes'] }}</div><div class="etiqueta">Pendientes</div></div></td>
        </tr></table>

        <div class="seccion-titulo">Por tipo de pago</div>
        <table class="datos">
            <thead><tr><th>Tipo de pago</th><th class="num">Cantidad</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @forelse ($datos['por_tipo_pago'] as $tipoPago => $info)
                    <tr><td>{{ $tipoPago ?: 'N/D' }}</td><td class="num">{{ $info['cantidad'] }}</td><td class="num">{{ $money($info['monto']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="seccion-titulo">Detalle de ventas</div>
        <table class="datos">
            <thead><tr><th>Código</th><th>Fecha</th><th>Tipo pago</th><th>Estado</th><th class="num">Total</th></tr></thead>
            <tbody>
                @forelse ($datos['ultimas'] as $v)
                    <tr>
                        <td>{{ $v->codigo }}</td>
                        <td>{{ $fmt($v->fecha_venta) }}</td>
                        <td>{{ $v->tipo_pago }}</td>
                        <td><span class="badge gris">{{ $v->estado }}</span></td>
                        <td class="num">{{ $money($v->total_final) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin ventas en el periodo</td></tr>
                @endforelse
            </tbody>
        </table>

    {{-- ---------------- ENCOMIENDAS ---------------- --}}
    @elseif ($tipo === 'encomiendas')
        <table class="kpis"><tr>
            <td><div class="kpi"><div class="valor">{{ $datos['total'] }}</div><div class="etiqueta">Encomiendas</div></div></td>
            @foreach ($datos['por_estado'] as $estado => $cant)
                <td><div class="kpi"><div class="valor">{{ $cant }}</div><div class="etiqueta">{{ $estado }}</div></div></td>
            @endforeach
        </tr></table>

        <div class="seccion-titulo">Detalle de encomiendas</div>
        <table class="datos">
            <thead><tr><th>Guía</th><th>Destinatario</th><th>Ruta</th><th>Estado</th><th>Registro</th></tr></thead>
            <tbody>
                @forelse ($datos['ultimas'] as $e)
                    <tr>
                        <td>{{ $e->guia_rastreo }}</td>
                        <td>{{ $e->destinatario }}</td>
                        <td>{{ $e->origen }} &rarr; {{ $e->destino }}</td>
                        <td><span class="badge gris">{{ $e->estado }}</span></td>
                        <td>{{ $fmt($e->fecha_registro) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin encomiendas en el periodo</td></tr>
                @endforelse
            </tbody>
        </table>

    {{-- ---------------- INVENTARIO ---------------- --}}
    @elseif ($tipo === 'inventario')
        <table class="kpis"><tr>
            <td><div class="kpi"><div class="valor">{{ $datos['total_productos'] }}</div><div class="etiqueta">Productos</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $datos['unidades_totales'] }}</div><div class="etiqueta">Unidades totales</div></div></td>
        </tr></table>

        <div class="seccion-titulo">Stock por almacén</div>
        <table class="datos">
            <thead><tr><th>Producto</th><th>Almacén</th><th class="num">Cantidad</th><th class="num">Stock mínimo</th><th>Nivel</th></tr></thead>
            <tbody>
                @forelse ($datos['stock'] as $s)
                    <tr>
                        <td>{{ $s['producto'] ?? '—' }}</td>
                        <td>{{ $s['almacen'] ?? '—' }}</td>
                        <td class="num">{{ $s['cantidad'] }}</td>
                        <td class="num">{{ $s['stock_minimo'] }}</td>
                        <td><span class="badge {{ $s['nivel'] === 'BAJO' ? 'bajo' : 'ok' }}">{{ $s['nivel'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin registros de inventario</td></tr>
                @endforelse
            </tbody>
        </table>

    {{-- ---------------- COTIZACIONES ---------------- --}}
    @elseif ($tipo === 'cotizaciones')
        <table class="kpis"><tr>
            <td><div class="kpi"><div class="valor">{{ $datos['total'] }}</div><div class="etiqueta">Cotizaciones</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $datos['tasa_conversion'] }}%</div><div class="etiqueta">Tasa conversión</div></div></td>
        </tr></table>

        <div class="seccion-titulo">Por estado</div>
        <table class="datos">
            <thead><tr><th>Estado</th><th class="num">Cantidad</th><th class="num">Monto estimado</th></tr></thead>
            <tbody>
                @forelse ($datos['por_estado'] as $estado => $info)
                    <tr><td>{{ $estado }}</td><td class="num">{{ $info['cantidad'] }}</td><td class="num">{{ $money($info['monto']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="seccion-titulo">Detalle de cotizaciones</div>
        <table class="datos">
            <thead><tr><th>#</th><th>Destinatario</th><th>Ruta</th><th>Estado</th><th class="num">Total est.</th><th>Emisión</th></tr></thead>
            <tbody>
                @forelse ($datos['ultimas'] as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->destinatario }}</td>
                        <td>{{ $c->origen }} &rarr; {{ $c->destino }}</td>
                        <td><span class="badge gris">{{ $c->estado }}</span></td>
                        <td class="num">{{ $money($c->total_estimado) }}</td>
                        <td>{{ $fmt($c->fecha_emision) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="vacio">Sin cotizaciones en el periodo</td></tr>
                @endforelse
            </tbody>
        </table>

    {{-- ---------------- FACTURAS ---------------- --}}
    @elseif ($tipo === 'facturas')
        <table class="kpis"><tr>
            <td><div class="kpi"><div class="valor">{{ $datos['total'] }}</div><div class="etiqueta">Facturas</div></div></td>
            <td><div class="kpi"><div class="valor">{{ $money($datos['monto_total']) }}</div><div class="etiqueta">Monto total</div></div></td>
        </tr></table>

        <div class="seccion-titulo">Por método de pago</div>
        <table class="datos">
            <thead><tr><th>Método</th><th class="num">Cantidad</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @forelse ($datos['por_metodo'] as $metodo => $info)
                    <tr><td>{{ $metodo ?: 'N/D' }}</td><td class="num">{{ $info['cantidad'] }}</td><td class="num">{{ $money($info['monto']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="vacio">Sin datos</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="seccion-titulo">Detalle de facturas</div>
        <table class="datos">
            <thead><tr><th>N.º factura</th><th>Método</th><th>Estado</th><th class="num">Total</th><th>Emisión</th></tr></thead>
            <tbody>
                @forelse ($datos['ultimas'] as $f)
                    <tr>
                        <td>{{ $f->numero_factura }}</td>
                        <td>{{ $f->metodo_pago }}</td>
                        <td><span class="badge gris">{{ $f->estado }}</span></td>
                        <td class="num">{{ $money($f->total) }}</td>
                        <td>{{ $fmt($f->fecha_emision) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="vacio">Sin facturas en el periodo</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div class="pie">Servicargo · Sistema de logística y encomiendas — Reporte generado automáticamente</div>
</body>
</html>
