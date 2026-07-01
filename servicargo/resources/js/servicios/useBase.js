// Ruta base de la app cuando vive en una subcarpeta (ej. /inf513/grupo12sa/proyecto2).
// En local (APP_URL sin path) queda '' y todo se comporta como antes.
export const BASE_PATH = (typeof window !== 'undefined' && window.APP_BASE_PATH) || '';

export function withBase(path) {
    return BASE_PATH + path;
}

// Quita el prefijo de subcarpeta de un pathname del navegador (para comparar rutas internas).
export function stripBase(pathname) {
    if (BASE_PATH && pathname.startsWith(BASE_PATH)) {
        return pathname.slice(BASE_PATH.length) || '/';
    }
    return pathname;
}
