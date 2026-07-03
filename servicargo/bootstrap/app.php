<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware del proyecto
        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtAuth::class,
            'rol' => \App\Http\Middleware\CheckRole::class,
            'bitacora' => \App\Http\Middleware\RegistrarBitacora::class,
        ]);

        // Inertia en el grupo web (puente SPA)
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // PagoFácil llama a /callback y /return sin token CSRF.
        $middleware->validateCsrfTokens(except: [
            'callback',
            'return',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Errores de la capa de negocio (Services): mensaje + código HTTP.
        // JSON/AJAX -> { message }; Inertia/web -> redirect back con error.
        $exceptions->render(function (\App\Servicios\ErrorDominio $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
            }

            return back()->withErrors(['dominio' => $e->getMessage()]);
        });

        // Mensaje en español por código HTTP (framework/infraestructura), para que el
        // usuario nunca vea textos en inglés como "Server Error" o "Too Many Requests".
        $mensajesHttp = [
            400 => 'La solicitud no es válida.',
            401 => 'Tu sesión expiró o no has iniciado sesión. Vuelve a iniciar sesión.',
            403 => 'No tienes permiso para realizar esta acción.',
            404 => 'No se encontró el recurso solicitado.',
            405 => 'La operación solicitada no está permitida.',
            408 => 'La solicitud tardó demasiado. Inténtalo de nuevo.',
            409 => 'La operación no se pudo completar por un conflicto con el estado actual.',
            413 => 'Los datos o el archivo enviados son demasiado grandes.',
            419 => 'Tu sesión expiró. Actualiza la página e inténtalo de nuevo.',
            429 => 'Hiciste demasiadas solicitudes en poco tiempo. Espera un momento e inténtalo de nuevo.',
            500 => 'Ocurrió un error interno en el servidor. Inténtalo de nuevo más tarde.',
            502 => 'El servidor no está respondiendo correctamente. Inténtalo más tarde.',
            503 => 'El servicio no está disponible en este momento. Inténtalo de nuevo en unos minutos.',
            504 => 'El servidor tardó demasiado en responder. Inténtalo más tarde.',
        ];

        // ¿La excepción de BD es por conexión perdida (servidor apagado, red caída,
        // credenciales/host inválidos) y no por una consulta mal formada?
        $esConexionBd = function (\Throwable $e): bool {
            // SQLSTATE de conexión: pgsql (08xxx, 57Pxx, 7) y mysql (2002/2003/2006/2013…).
            $codigosConexion = ['08006', '08001', '08004', '08003', '57P01', '57P03', '7',
                '2002', '2003', '2005', '2006', '2013', '1042', '1044', '1045', '1049'];
            if (in_array((string) $e->getCode(), $codigosConexion, true)) {
                return true;
            }
            $msg = strtolower($e->getMessage());
            foreach ([
                'could not connect', 'connection refused', 'connection timed out',
                'server closed the connection', 'no connection to the server',
                'could not translate host name', 'server has gone away', 'lost connection',
                'could not find driver', 'connection reset', 'no such host', 'actively refused',
                'terminating connection', 'too many connections',
            ] as $frag) {
                if (str_contains($msg, $frag)) {
                    return true;
                }
            }

            return false;
        };

        // Construye la respuesta según el tipo de petición: JSON para el SPA/API,
        // y una página mínima en español (sin depender de la BD) para cargas directas.
        $responder = function (Request $request, string $mensaje, int $codigo) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $mensaje], $codigo);
            }

            $html = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Servicargo</title></head>'
                . '<body style="font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#0d1117;color:#e6edf3;'
                . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px;text-align:center">'
                . '<div style="max-width:460px">'
                . '<h1 style="font-size:20px;margin:0 0 12px">No pudimos cargar la página</h1>'
                . '<p style="color:#9da7b3;line-height:1.6;margin:0 0 20px">' . e($mensaje) . '</p>'
                . '<a href="#" onclick="location.reload();return false;" style="display:inline-block;padding:10px 18px;'
                . 'background:#1f6feb;color:#fff;border-radius:8px;text-decoration:none;font-weight:600">Reintentar</a>'
                . '</div></body></html>';

            return response($html, $codigo, ['Content-Type' => 'text/html; charset=UTF-8']);
        };

        // Manejador general: traduce a español los errores que no son de dominio.
        $exceptions->render(function (\Throwable $e, Request $request) use ($mensajesHttp, $responder, $esConexionBd) {
            // La validación conserva su cuerpo { message, errors } para resaltar cada campo.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }
            // Los errores de dominio ya se tradujeron en el manejador anterior.
            if ($e instanceof \App\Servicios\ErrorDominio) {
                return null;
            }

            // Problemas con la base de datos.
            if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \PDOException) {
                // Conexión perdida / servidor de BD apagado: mensaje claro, siempre en español.
                if ($esConexionBd($e)) {
                    \Illuminate\Support\Facades\Log::error('Fallo de conexión a la base de datos', [
                        'ruta' => $request->fullUrl(),
                        'detalle' => $e->getMessage(),
                    ]);

                    return $responder(
                        $request,
                        'No se pudo conectar con la base de datos. Por favor, inténtalo de nuevo en unos minutos.',
                        503,
                    );
                }

                // Error de consulta (no de conexión): en debug lo mostramos al desarrollador;
                // en producción, mensaje genérico en español sin filtrar detalles internos.
                if (config('app.debug')) {
                    return null;
                }
                \Illuminate\Support\Facades\Log::error('Error de consulta a la base de datos', [
                    'ruta' => $request->fullUrl(),
                    'detalle' => $e->getMessage(),
                ]);

                return $responder(
                    $request,
                    'Ocurrió un problema al procesar los datos. Inténtalo de nuevo más tarde.',
                    500,
                );
            }

            // Errores HTTP conocidos (404, 405, 429, abort(), etc.): mensaje en español.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $codigo = $e->getStatusCode();
                $propio = trim($e->getMessage());
                $textoIngles = \Symfony\Component\HttpFoundation\Response::$statusTexts[$codigo] ?? '';
                // Respeta un mensaje propio en español; ignora los textos por defecto en inglés.
                $mensaje = ($propio !== '' && $propio !== $textoIngles)
                    ? $propio
                    : ($mensajesHttp[$codigo] ?? 'Ocurrió un error al procesar la solicitud.');

                return $responder($request, $mensaje, $codigo);
            }

            // Sesión expirada / no autenticado (por si algún flujo lo lanza).
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return $responder(
                    $request,
                    'Tu sesión expiró o no has iniciado sesión. Vuelve a iniciar sesión.',
                    401,
                );
            }

            // Cualquier otro error inesperado (500). En modo debug dejamos que Laravel
            // muestre el detalle al desarrollador; en producción, mensaje en español.
            if (config('app.debug')) {
                return null;
            }

            \Illuminate\Support\Facades\Log::error('Error no controlado', [
                'ruta' => $request->fullUrl(),
                'detalle' => $e->getMessage(),
            ]);

            return $responder(
                $request,
                'Ocurrió un error inesperado en el servidor. Inténtalo de nuevo más tarde.',
                500,
            );
        });
    })->create();
