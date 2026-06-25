<?php

namespace App\Support;

use App\Models\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private const TTL_MINUTOS = 30; // sesión de 30 minutos

    private static function secret(): string
    {
        return (string) env('JWT_SECRET', config('app.key'));
    }

    public static function emitir(Usuario $u): array
    {
        $ahora = time();
        $exp = $ahora + self::TTL_MINUTOS * 60;
        $payload = [
            'sub' => $u->id,
            'rol' => $u->rol,
            'correo' => $u->correo,
            'iat' => $ahora,
            'exp' => $exp,
        ];
        $token = JWT::encode($payload, self::secret(), 'HS256');

        return ['token' => $token, 'expira_en' => self::TTL_MINUTOS * 60, 'exp' => $exp];
    }

    /** Devuelve el Usuario o null si el token es inválido/expirado. */
    public static function usuarioDesdeToken(?string $token): ?Usuario
    {
        if (! $token) {
            return null;
        }
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }

        return Usuario::find($decoded->sub ?? null);
    }
}
