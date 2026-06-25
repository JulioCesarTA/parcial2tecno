import { reactive } from 'vue';

// Estado compartido para toasts y diálogo de confirmación
const estado = reactive({
    toasts: [],
    confirm: null, // { titulo, mensaje, resolver }
});

let id = 0;

export function useToast() {
    function push(mensaje, tipo = 'info') {
        const t = { id: ++id, mensaje, tipo };
        estado.toasts.push(t);
        setTimeout(() => {
            const i = estado.toasts.findIndex((x) => x.id === t.id);
            if (i >= 0) estado.toasts.splice(i, 1);
        }, 3500);
    }
    return {
        toasts: estado.toasts,
        exito: (m) => push(m, 'exito'),
        error: (m) => push(m, 'error'),
        info: (m) => push(m, 'info'),
        quitar: (tid) => {
            const i = estado.toasts.findIndex((x) => x.id === tid);
            if (i >= 0) estado.toasts.splice(i, 1);
        },
    };
}

export function useConfirm() {
    function confirmar({ titulo = 'Confirmar', mensaje = '¿Estás seguro?' } = {}) {
        return new Promise((resolve) => {
            estado.confirm = { titulo, mensaje, resolver: resolve };
        });
    }
    return { confirmar };
}

export function _confirmState() {
    return estado;
}
