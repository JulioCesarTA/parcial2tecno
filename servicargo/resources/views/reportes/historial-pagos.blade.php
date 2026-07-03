<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reportes.partials.estilos')
</head>
@php
    $money = fn ($n) => 'Bs ' . number_format((float) $n, 2);
    $fmt = fn ($f) => \App\Support\Formato::fecha($f);
    $badgeVenta = fn ($e) => match ($e) {
        'PAGADA' => 'ok',
        'PARCIAL' => 'info',
        default => 'aviso',
    };
    $badgePago = fn ($e) => match ($e) {
        'REGISTRADO' => 'ok',
        'PENDIENTE' => 'aviso',
        'ANULADO' => 'error',
        default => 'gris',
    };
@endphp
<body>
    <div class="encabezado">
        <div class="marca">SERVICARGO</div>
        <h1>Historial de pagos</h1>
        <div class="sub">{{ trim(($actor->nombre ?? '') . ' ' . ($actor->apellido ?? '')) ?: $actor->correo }}</div>
        <div class="meta">Generado el {{ $fecha }}</div>
    </div>

    <table class="kpis"><tr>
        <td><div class="kpi"><div class="valor">{{ count($datos['ventas']) }}</div><div class="etiqueta">Ventas</div></div></td>
        <td><div class="kpi"><div class="valor">{{ $money($datos['total_ventas']) }}</div><div class="etiqueta">Total facturado</div></div></td>
        <td><div class="kpi"><div class="valor">{{ $money($datos['total_pagado']) }}</div><div class="etiqueta">Pagado</div></div></td>
        <td><div class="kpi"><div class="valor">{{ $money($datos['total_saldo']) }}</div><div class="etiqueta">Saldo pendiente</div></div></td>
    </tr></table>

    <div class="seccion-titulo">Ventas: qué está pagado y qué falta</div>
    <table class="datos">
        <thead><tr><th>Código</th><th>Cliente</th><th class="num">Total</th><th class="num">Pagado</th><th class="num">Saldo</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($datos['ventas'] as $r)
                <tr>
                    <td>{{ $r['venta']->codigo }}</td>
                    <td>{{ trim(($r['venta']->cliente->nombre ?? '') . ' ' . ($r['venta']->cliente->apellido ?? '')) ?: '—' }}</td>
                    <td class="num">{{ $money($r['venta']->total_final) }}</td>
                    <td class="num">{{ $money($r['pagado']) }}</td>
                    <td class="num">{{ $money($r['saldo']) }}</td>
                    <td><span class="badge {{ $badgeVenta($r['venta']->estado) }}">{{ $r['venta']->estado }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="vacio">Sin ventas registradas</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="seccion-titulo">Detalle de pagos realizados</div>
    <table class="datos">
        <thead><tr><th>Fecha</th><th>Venta</th><th>Método</th><th class="num">Monto</th><th>Cuota</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($datos['pagos'] as $p)
                <tr>
                    <td>{{ $fmt($p->fecha_pago) }}</td>
                    <td>{{ $p->venta->codigo ?? '—' }}</td>
                    <td>{{ $p->metodo_pago }}</td>
                    <td class="num">{{ $money($p->monto) }}</td>
                    <td>{{ $p->numero_cuota ?: '—' }}</td>
                    <td><span class="badge {{ $badgePago($p->estado) }}">{{ $p->estado }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="vacio">Sin pagos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="nota">El saldo pendiente considera únicamente los pagos con estado REGISTRADO (confirmados). Los pagos QR pendientes de confirmación no reducen el saldo hasta ser confirmados.</p>

    <div class="pie">Servicargo · Sistema de logística y encomiendas — Documento generado automáticamente</div>
</body>
</html>
