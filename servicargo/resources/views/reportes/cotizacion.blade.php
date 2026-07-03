<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reportes.partials.estilos')
</head>
@php
    $money = fn ($n) => 'Bs ' . number_format((float) $n, 2);
    $fmt = fn ($f) => \App\Support\Formato::fecha($f);
    $badge = match ($cotizacion->estado) {
        'APROBADA' => 'info',
        'RECHAZADA' => 'error',
        'COMPLETADA' => 'ok',
        'VENCIDA' => 'error',
        default => 'aviso',
    };
    $validaHasta = $cotizacion->fecha_emision
        ? $cotizacion->fecha_emision->copy()->addDays((int) $cotizacion->validez_dias)
        : null;
@endphp
<body>
    <div class="encabezado">
        <div class="marca">SERVICARGO</div>
        <h1>Cotización de envío</h1>
        <div class="documento-num">#{{ $cotizacion->id }}</div>
        <div class="meta">Generado el {{ $fecha }} &nbsp;·&nbsp; <span class="badge {{ $badge }}">{{ $cotizacion->estado }}</span></div>
    </div>

    <table class="ficha"><tr>
        <td>
            <div class="etiqueta">Cliente</div>
            <div class="valor">{{ trim(($cotizacion->cliente->nombre ?? '') . ' ' . ($cotizacion->cliente->apellido ?? '')) ?: '—' }}</div>
        </td>
        <td>
            <div class="etiqueta">Asesor</div>
            <div class="valor">{{ trim(($cotizacion->vendedor->nombre ?? '') . ' ' . ($cotizacion->vendedor->apellido ?? '')) ?: '—' }}</div>
        </td>
        <td>
            <div class="etiqueta">Fecha de emisión</div>
            <div class="valor">{{ $fmt($cotizacion->fecha_emision) }}</div>
        </td>
        <td>
            <div class="etiqueta">Válida hasta</div>
            <div class="valor">{{ $fmt($validaHasta) }}</div>
        </td>
    </tr></table>

    <table class="ficha"><tr>
        <td>
            <div class="etiqueta">Remitente</div>
            <div class="valor">{{ $cotizacion->remitente }}</div>
        </td>
        <td>
            <div class="etiqueta">Destinatario</div>
            <div class="valor">{{ $cotizacion->destinatario }}</div>
        </td>
        <td>
            <div class="etiqueta">Ruta</div>
            <div class="valor">{{ $cotizacion->origen }} &rarr; {{ $cotizacion->destino }}</div>
        </td>
        <td>
            <div class="etiqueta">Envío / Peso / Volumen</div>
            <div class="valor">{{ ucfirst($cotizacion->tipo_envio) }} · {{ $cotizacion->peso_kg }} kg · {{ $cotizacion->volumen_m3 }} m³</div>
        </td>
    </tr></table>

    <p class="nota" style="margin:-6px 0 12px"><strong>Contenido:</strong> {{ $cotizacion->contenido }}</p>

    <div class="seccion-titulo">Productos / servicios cotizados</div>
    <table class="datos">
        <thead><tr><th>Producto</th><th class="num">Cantidad</th><th class="num">P. Unitario</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
            @forelse ($cotizacion->detalles as $d)
                <tr>
                    <td>{{ $d->producto->nombre ?? '—' }}</td>
                    <td class="num">{{ $d->cantidad }}</td>
                    <td class="num">{{ $money($d->precio_unitario) }}</td>
                    <td class="num">{{ $money($d->subtotal) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Sin productos cotizados</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ficha" style="margin-top:14px">
        <tr>
            <td><div class="etiqueta">Subtotal</div><div class="valor">{{ $money($cotizacion->subtotal) }}</div></td>
            <td><div class="etiqueta">Impuestos</div><div class="valor">{{ $money($cotizacion->impuestos) }}</div></td>
            <td colspan="2"></td>
        </tr>
    </table>

    <div class="total-caja">
        <div class="etiqueta">Total estimado</div>
        <div class="valor">{{ $money($cotizacion->total_estimado) }}</div>
    </div>

    <p class="nota">
        Esta cotización es una estimación válida por {{ $cotizacion->validez_dias }} día(s) desde su emisión.
        @if ($cotizacion->estado === 'APROBADA' && $cotizacion->fecha_aprobacion)
            Aprobada el {{ \App\Support\Formato::fechaHora($cotizacion->fecha_aprobacion) }}.
        @endif
    </p>

    <div class="pie">Servicargo · Sistema de logística y encomiendas — Documento generado automáticamente</div>
</body>
</html>
