<?php

namespace App\Servicios\Core;

use App\Models\PreferenciaUsuario;
use App\Models\Usuario;

/**
 * Requisito 5 — Preferencias de tema/accesibilidad por usuario (módulo core).
 * Permanente (en BD) y única por usuario.
 */
class PreferenciaService
{
    private array $defaults = ['tema' => 'navy:auto', 'fuente' => 'md', 'contraste' => 'normal'];

    /** Devuelve las preferencias del usuario (o los valores por defecto si no tiene). */
    public function obtenerDe(Usuario $actor): array
    {
        $pref = PreferenciaUsuario::where('usuario_id', $actor->id)->first();
        if (! $pref) {
            return $this->defaults;
        }

        return [
            'tema' => $pref->tema,
            'fuente' => $pref->fuente,
            'contraste' => $pref->contraste,
        ];
    }

    /** Crea o actualiza las preferencias del usuario. */
    public function guardar(array $datos, Usuario $actor): array
    {
        $pref = PreferenciaUsuario::updateOrCreate(
            ['usuario_id' => $actor->id],
            [
                'tema' => $datos['tema'] ?? $this->defaults['tema'],
                'fuente' => $datos['fuente'] ?? $this->defaults['fuente'],
                'contraste' => $datos['contraste'] ?? $this->defaults['contraste'],
            ]
        );

        return [
            'tema' => $pref->tema,
            'fuente' => $pref->fuente,
            'contraste' => $pref->contraste,
        ];
    }
}
