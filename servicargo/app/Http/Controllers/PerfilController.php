<?php

namespace App\Http\Controllers;

use App\Servicios\Core\PerfilService;
use App\Support\MenuService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerfilController extends Controller
{
    public function __construct(private PerfilService $perfil) {}

    /** Datos del perfil propio (usuario + permisos + menú, igual que /auth/me). */
    public function show(Request $request)
    {
        $usuario = $request->user();

        return response()->json([
            'usuario' => $usuario,
            'permisos' => MenuService::permisos($usuario->rol),
            'menu' => MenuService::menu($usuario->rol),
        ]);
    }

    /** Actualiza los datos propios (sin cambiar rol). */
    public function update(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'apellido' => ['sometimes', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'correo' => ['sometimes', 'email', 'max:120', Rule::unique('usuario', 'correo')->ignore($usuario->id)],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'contrasena' => ['sometimes', 'nullable', 'string', 'min:6', 'max:120'],
        ]);

        // Contraseña vacía = no cambiar.
        if (array_key_exists('contrasena', $datos) && ($datos['contrasena'] === null || $datos['contrasena'] === '')) {
            unset($datos['contrasena']);
        }

        $usuario = $this->perfil->actualizar($usuario, $datos);

        return response()->json(['message' => 'Perfil actualizado.', 'usuario' => $usuario]);
    }

    /** Sube/reemplaza la foto de perfil. */
    public function foto(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:30720'],
        ], [
            'foto.required' => 'Selecciona una imagen.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'foto.max' => 'La imagen no debe superar los 30 MB.',
            'foto.uploaded' => 'La imagen no se pudo subir: supera el límite del servidor (revisa que pese menos de 30 MB).',
        ]);

        $usuario = $this->perfil->actualizarFoto($request->user(), $request->file('foto'));

        return response()->json(['message' => 'Foto actualizada.', 'usuario' => $usuario]);
    }
}
