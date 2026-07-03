<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('reportes.partials.estilos')
</head>
@php
    $fmt = fn ($f) => \App\Support\Formato::fecha($f);
    $fmtHora = fn ($f) => \App\Support\Formato::fechaHora($f);
    $badge = match ($encomienda->estado) {
        'REGISTRADA' => 'info',
        'EN_TRANSITO', 'EN_DISTRIBUCION' => 'aviso',
        'ENTREGADA', 'ENTREGADO' => 'ok',
        'DEVUELTA', 'CANCELADA' => 'error',
        default => 'gris',
    };
    $historial = $encomienda->historial->sortBy('fecha_cambio')->values();
@endphp
<body>
    <div class="encabezado">
        <div class="marca">SERVICARGO</div>
        <h1>Encomienda</h1>
        <div class="documento-num">Guía {{ $encomienda->guia_rastreo }}</div>
        <div class="meta">Generado el {{ $fecha }} &nbsp;·&nbsp; <span class="badge {{ $badge }}">{{ $encomienda->estado }}</span></div>
    </div>

    <table class="ficha"><tr>
        <td>
            <div class="etiqueta">Cliente</div>
            <div class="valor">{{ trim(($encomienda->cliente->nombre ?? '') . ' ' . ($encomienda->cliente->apellido ?? '')) ?: '—' }}</div>
        </td>
        <td>
            <div class="etiqueta">Remitente &rarr; Destinatario</div>
            <div class="valor">{{ $encomienda->remitente }} &rarr; {{ $encomienda->destinatario }}</div>
        </td>
        <td>
            <div class="etiqueta">Ruta</div>
            <div class="valor">{{ $encomienda->origen }} &rarr; {{ $encomienda->destino }}</div>
        </td>
        <td>
            <div class="etiqueta">Envío / Peso / Volumen</div>
            <div class="valor">{{ ucfirst($encomienda->tipo_envio) }} · {{ $encomienda->peso_kg }} kg · {{ $encomienda->volumen_m3 }} m³</div>
        </td>
    </tr></table>

    <table class="ficha"><tr>
        <td>
            <div class="etiqueta">Fecha de registro</div>
            <div class="valor">{{ $fmt($encomienda->fecha_registro) }}</div>
        </td>
        <td>
            <div class="etiqueta">Entrega estimada</div>
            <div class="valor">{{ $fmt($encomienda->fecha_entrega_estimada) }}</div>
        </td>
        <td colspan="2">
            <div class="etiqueta">Contenido declarado</div>
            <div class="valor">{{ $encomienda->contenido }}</div>
        </td>
    </tr></table>

    <div class="seccion-titulo">Historial de estados (trazabilidad)</div>
    <table class="datos timeline">
        <thead><tr><th>Fecha</th><th>Cambio de estado</th><th>Observaciones</th></tr></thead>
        <tbody>
            @forelse ($historial as $h)
                <tr>
                    <td>{{ $fmtHora($h->fecha_cambio) }}</td>
                    <td><span class="punto">&#8594;</span> {{ $h->estado_anterior ? $h->estado_anterior . ' → ' : '' }}{{ $h->estado_nuevo }}</td>
                    <td>{{ $h->observaciones ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="vacio">Sin cambios de estado registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="nota">Cotización de origen: #{{ $encomienda->cotizacion->id ?? '—' }}.
        Conserva este documento y el código de guía para consultar el seguimiento de tu envío.</p>

    <div class="pie">Servicargo · Sistema de logística y encomiendas — Documento generado automáticamente</div>
</body>
</html>
