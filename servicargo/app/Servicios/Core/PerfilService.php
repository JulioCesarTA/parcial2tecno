<?php

namespace App\Servicios\Core;

use App\Models\Usuario;
use App\Servicios\ErrorDominio;
use App\Support\BitacoraService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Perfil propio del usuario autenticado (módulo core).
 *
 * Cada usuario edita SUS datos y su foto (sin permisos de admin). La foto se
 * guarda directamente dentro de public/ para servirse por ruta estática:
 * así funciona en el hosting FTP (tecnoweb), donde el symlink storage:link
 * no está disponible.
 */
class PerfilService
{
    /** Carpeta (dentro de public/) donde viven las fotos de perfil. */
    private const CARPETA_FOTOS = 'fotos_perfil';

    /** Actualiza los datos propios del usuario (contraseña opcional). */
    public function actualizar(Usuario $usuario, array $datos): Usuario
    {
        if (isset($datos['contrasena'])) {
            $datos['contrasena'] = Hash::make($datos['contrasena']);
        }

        // El rol no se toca desde el perfil propio.
        unset($datos['rol']);

        $usuario->update($datos);
        BitacoraService::registrar($usuario->id, 'accion', 'perfil', 'Actualización de datos de perfil');

        return $usuario;
    }

    /** Guarda la nueva foto en public/fotos_perfil y borra la anterior. */
    public function actualizarFoto(Usuario $usuario, UploadedFile $archivo): Usuario
    {
        if (! $archivo->isValid()) {
            throw new ErrorDominio('El archivo de imagen no es válido.', 422);
        }

        $destino = public_path(self::CARPETA_FOTOS);
        if (! is_dir($destino)) {
            @mkdir($destino, 0755, true);
        }

        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension());
        $nombre = 'usuario_'.$usuario->id.'_'.time().'_'.Str::random(6).'.'.$extension;

        $archivo->move($destino, $nombre);

        // Borra la foto anterior (si existía y estaba en nuestra carpeta).
        $this->borrarFotoAnterior($usuario->foto);

        // Ruta relativa a public/ — el front la resuelve con withBase().
        $usuario->update(['foto' => self::CARPETA_FOTOS.'/'.$nombre]);
        BitacoraService::registrar($usuario->id, 'accion', 'perfil', 'Cambio de foto de perfil');

        return $usuario;
    }

    private function borrarFotoAnterior(?string $rutaRelativa): void
    {
        if (! $rutaRelativa || ! Str::startsWith($rutaRelativa, self::CARPETA_FOTOS.'/')) {
            return;
        }
        $ruta = public_path($rutaRelativa);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
