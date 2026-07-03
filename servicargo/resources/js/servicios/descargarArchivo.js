// Descarga binaria (PDF) con el token JWT: la API responde con
// Content-Disposition: attachment, pero fetch()+blob() lo maneja mejor que un
// <a href> directo porque nos deja mandar el header Authorization.
import { getToken } from './useApi';
import { withBase } from './useBase';

export async function descargarPdf(path, nombreArchivo) {
    let res;
    try {
        res = await fetch(withBase('/api' + path), {
            headers: { Authorization: 'Bearer ' + getToken() },
        });
    } catch (e) {
        // Backend caído o sin conexión: fetch() lanza antes de haber respuesta.
        const sinRed = typeof navigator !== 'undefined' && navigator.onLine === false;
        throw new Error(
            sinRed
                ? 'No tienes conexión a internet. Revisa tu red e inténtalo de nuevo.'
                : 'No se pudo conectar con el servidor para generar el PDF. Inténtalo de nuevo en unos minutos.',
        );
    }
    if (!res.ok) {
        let mensaje = 'No se pudo generar el PDF.';
        try { mensaje = (await res.json())?.message || mensaje; } catch (e) { /* sin cuerpo JSON */ }
        throw new Error(mensaje);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombreArchivo;
    enlace.click();
    URL.revokeObjectURL(url);
}
