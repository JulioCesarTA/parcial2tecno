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

    const res = await fetch(url, opts);
    let data = null;
    try { data = await res.json(); } catch (e) { /* sin cuerpo */ }

    if (!res.ok) {
        const error = new Error(data?.message || 'Ocurrió un error.');
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
