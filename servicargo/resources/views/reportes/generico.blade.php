<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { color: #1f6feb; font-size: 18px; }
        .meta { color: #666; font-size: 10px; margin-bottom: 12px; }
        pre { background: #f4f6f8; padding: 10px; border-radius: 6px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">Servicargo — generado el {{ $fecha }}</div>
    <pre>{{ json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</body>
</html>
