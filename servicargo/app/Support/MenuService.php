<?php

namespace App\Support;

use App\Models\Permiso;
use App\Models\Recurso;

class MenuService
{
    /** Permisos del rol indexados por clave de recurso. */
    public static function permisos(string $rol): array
    {
        $permisos = Permiso::with('recurso')->where('rol', $rol)->get();
        $out = [];
        foreach ($permisos as $p) {
            if (! $p->recurso) {
                continue;
            }
            $out[$p->recurso->clave] = [
                'ver' => (bool) $p->ver,
                'crear' => (bool) $p->crear,
                'editar' => (bool) $p->editar,
                'eliminar' => (bool) $p->eliminar,
            ];
        }

        return $out;
    }

    /** Menú dinámico (desde BD) para el rol: recursos con permiso de ver. */
    public static function menu(string $rol): array
    {
        $permisos = self::permisos($rol);
        $recursos = Recurso::orderBy('orden')->get();
        $menu = [];
        foreach ($recursos as $r) {
            if (($permisos[$r->clave]['ver'] ?? false) === true) {
                $menu[] = [
                    'clave' => $r->clave,
                    'nombre' => $r->nombre,
                    'icono' => $r->icono,
                    'ruta' => $r->ruta,
                ];
            }
        }

        return $menu;
    }
}
