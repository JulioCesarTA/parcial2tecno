// Cliente HTTP que inyecta el token JWT en cada petición
import { withBase } from './useBase';

const TOKEN_KEY = 'sc_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}
export function setToken(t) {
    if (t) localStorage.setItem(TOKEN_KEY, t);
    else localStorage.removeItem(TOKEN_KEY);
}

// Mensaje en español por código HTTP, para cuando el servidor no manda uno propio
// (p. ej. errores de infraestructura que no pasan por nuestros Services).
const MENSAJES_ESTADO = {
    400: 'La solicitud no es válida.',
    401: 'Tu sesión expiró o no has iniciado sesión. Vuelve a iniciar sesión.',
    403: 'No tienes permiso para realizar esta acción.',
    404: 'No se encontró el recurso solicitado.',
    405: 'La operación solicitada no está permitida.',
    408: 'La solicitud tardó demasiado. Inténtalo de nuevo.',
    409: 'La operación no se pudo completar por un conflicto con el estado actual.',
    413: 'Los datos o el archivo enviados son demasiado grandes.',
    419: 'Tu sesión expiró. Actualiza la página e inténtalo de nuevo.',
    422: 'Revisa los datos ingresados: hay campos incorrectos.',
    429: 'Hiciste demasiadas solicitudes en poco tiempo. Espera un momento e inténtalo de nuevo.',
    500: 'Ocurrió un error interno en el servidor. Inténtalo de nuevo más tarde.',
    502: 'El servidor no está respondiendo correctamente. Inténtalo más tarde.',
    503: 'El servicio no está disponible en este momento. Inténtalo de nuevo en unos minutos.',
    504: 'El servidor tardó demasiado en responder. Inténtalo más tarde.',
};

// Cuando fetch() falla (backend apagado, sin internet, DNS, CORS…) no hay respuesta
// HTTP: el navegador lanza un TypeError. Lo traducimos a un mensaje claro en español.
function errorDeConexion() {
    const sinRed = typeof navigator !== 'undefined' && navigator.onLine === false;
    const error = new Error(
        sinRed
            ? 'No tienes conexión a internet. Revisa tu red e inténtalo de nuevo.'
            : 'No se pudo conectar con el servidor. Puede estar apagado o sin conexión; inténtalo de nuevo en unos minutos.',
    );
    error.status = 0;
    error.conexion = true;
    error.errors = null;
    return error;
}

export async function api(path, { method = 'GET', body = null, params = null } = {}) {
    let url = withBase('/api' + path);
    if (params) {
        const qs = new URLSearchParams(params).toString();
        if (qs) url += '?' + qs;
    }

    const headers = { Accept: 'application/json' };
    const token = getToken();
    if (token) headers.Authorization = 'Bearer ' + token;

    const opts = { method, headers };
    if (body instanceof FormData) {
        opts.body = body;
    } else if (body !== null) {
        headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }

    let res;
    try {
        res = await fetch(url, opts);
    } catch (e) {
        // Falla de red: el backend está caído o no hay conexión.
        throw errorDeConexion();
    }

    let data = null;
    try { data = await res.json(); } catch (e) { /* respuesta sin cuerpo JSON */ }

    if (!res.ok) {
        const mensaje = data?.message
            || MENSAJES_ESTADO[res.status]
            || 'Ocurrió un error inesperado. Inténtalo de nuevo.';
        const error = new Error(mensaje);
        error.status = res.status;
        error.errors = data?.errors || null;
        throw error;
    }
    return data;
}

export const get = (p, params) => api(p, { params });
export const post = (p, body) => api(p, { method: 'POST', body });
export const put = (p, body) => api(p, { method: 'PUT', body });
export const del = (p) => api(p, { method: 'DELETE' });
