<?php

namespace App\Servicios;

use RuntimeException;

/**
 * Excepción de la capa de negocio (Services).
 *
 * Los Services la lanzan cuando una regla de dominio no se cumple o un recurso
 * no existe. El manejador global (bootstrap/app.php) la traduce:
 *  - a JSON { message } con su código HTTP para peticiones API/AJAX,
 *  - a un redirect()->back() con error para peticiones Inertia/web.
 *
 * Así el controlador queda delgado: no necesita comprobar null ni armar el error.
 */
class ErrorDominio extends RuntimeException
{
    public function __construct(string $mensaje, private int $estado = 422)
    {
        parent::__construct($mensaje);
    }

    /** Código HTTP asociado (404 no encontrado, 422 regla de negocio, etc.). */
    public function getStatusCode(): int
    {
        return $this->estado;
    }
}
