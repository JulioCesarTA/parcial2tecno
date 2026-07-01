import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { api, setToken, getToken } from './useApi';
import { withBase } from './useBase';

const SES_KEY = 'sc_sesion';

// Estado de sesión compartido (rehidratado de forma síncrona)
const sesion = reactive({
    usuario: null,
    permisos: {},
    menu: [],
    cargada: false,
});

function rehidratar() {
    try {
        const raw = localStorage.getItem(SES_KEY);
        if (raw) {
            const d = JSON.parse(raw);
            sesion.usuario = d.usuario || null;
            sesion.permisos = d.permisos || {};
            sesion.menu = d.menu || [];
        }
    } catch (e) { /* ignore */ }
}
rehidratar();

function guardar() {
    localStorage.setItem(SES_KEY, JSON.stringify({
        usuario: sesion.usuario, permisos: sesion.permisos, menu: sesion.menu,
    }));
}

export function useAuth() {
    const autenticado = computed(() => !!sesion.usuario && !!getToken());

    async function login(correo, contrasena) {
        const d = await api('/auth/login', { method: 'POST', body: { correo, contrasena } });
        setToken(d.token);
        sesion.usuario = d.usuario;
        sesion.permisos = d.permisos;
        sesion.menu = d.menu;
        sesion.cargada = true;
        guardar();
        return d;
    }

    async function refrescar() {
        if (!getToken()) return;
        try {
            const d = await api('/auth/me');
            sesion.usuario = d.usuario;
            sesion.permisos = d.permisos;
            sesion.menu = d.menu;
            guardar();
        } catch (e) {
            if (e.status === 401) cerrarLocal();
        } finally {
            sesion.cargada = true;
        }
    }

    function cerrarLocal() {
        setToken(null);
        localStorage.removeItem(SES_KEY);
        sesion.usuario = null;
        sesion.permisos = {};
        sesion.menu = [];
    }

    async function logout() {
        try { await api('/auth/logout', { method: 'POST' }); } catch (e) { /* ignore */ }
        cerrarLocal();
        router.visit(withBase('/login'));
    }

    // ¿el rol actual tiene permiso (accion) sobre el recurso?
    function puede(recurso, accion = 'ver') {
        return !!sesion.permisos?.[recurso]?.[accion];
    }

    function requiereSesion() {
        if (!autenticado.value) {
            router.visit(withBase('/login'));
            return false;
        }
        return true;
    }

    return { sesion, autenticado, login, logout, refrescar, puede, requiereSesion };
}
