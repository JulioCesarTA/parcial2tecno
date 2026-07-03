<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reportes.partials.estilos')
</head>
@php
    $money = fn ($n) => 'Bs ' . number_format((float) $n, 2);
    $fmt = fn ($f) => \App\Support\Formato::fecha($f);
    $badge = match ($factura->estado) {
        'EMITIDA' => 'ok',
        'ANULADA' => 'error',
        default => 'gris',
    };
    $cliente = $factura->venta->cliente;
    $vendedor = $factura->venta->vendedor;
@endphp
<body>
    <div class="encabezado">
        <div class="marca">SERVICARGO</div>
        <h1>Factura</h1>
        <div class="documento-num">{{ $factura->numero_factura }}</div>
        <div class="meta">Generado el {{ $fecha }} &nbsp;·&nbsp; Venta {{ $factura->venta->codigo }} &nbsp;·&nbsp; <span class="badge {{ $badge }}">{{ $factura->estado }}</span></div>
    </div>

    <table class="ficha"><tr>
        <td>
            <div class="etiqueta">Cliente</div>
            <div class="valor">{{ trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellido ?? '')) ?: '—' }}</div>
        </td>
        <td>
            <div class="etiqueta">CI / Correo</div>
            <div class="valor">{{ $cliente->ci ?? '—' }}</div>
            <div style="font-size:9px; color:#666">{{ $cliente->correo ?? '' }}</div>
        </td>
        <td>
            <div class="etiqueta">Fecha de emisión</div>
            <div class="valor">{{ $fmt($factura->fecha_emision) }}</div>
        </td>
        <td>
            <div class="etiqueta">Método de pago</div>
            <div class="valor">{{ $factura->metodo_pago }}{{ $factura->numero_cuota ? ' · Cuota ' . $factura->numero_cuota : '' }}</div>
        </td>
    </tr></table>

    <div class="seccion-titulo">Detalle</div>
    <table class="datos">
        <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">P. Unitario</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
            @forelse ($items as $it)
                <tr>
                    <td>{{ $it->producto->nombre ?? '—' }}</td>
                    <td class="num">{{ $it->cantidad }}</td>
                    <td class="num">{{ $money($it->precio_unitario) }}</td>
                    <td class="num">{{ $money($it->subtotal) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin ítems asociados a esta venta</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ficha" style="margin-top:14px">
        <tr>
            <td><div class="etiqueta">Subtotal</div><div class="valor">{{ $money($factura->subtotal) }}</div></td>
            <td><div class="etiqueta">Impuestos</div><div class="valor">{{ $money($factura->impuestos) }}</div></td>
            <td colspan="2"></td>
        </tr>
    </table>

    <div class="total-caja">
        <div class="etiqueta">Total pagado en este comprobante</div>
        <div class="valor">{{ $money($factura->total) }}</div>
    </div>

    <p class="nota">Vendedor/asesor a cargo: {{ trim(($vendedor->nombre ?? '') . ' ' . ($vendedor->apellido ?? '')) ?: '—' }}.
        Esta factura corresponde a un solo pago; si la venta se paga en cuotas, cada cuota emite su propia factura.</p>

    <div class="pie">Servicargo · Sistema de logística y encomiendas — Documento generado automáticamente</div>
</body>
</html>
