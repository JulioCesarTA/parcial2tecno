<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useToast } from '../servicios/useUI';

const toast = useToast();
const recursos = ref([]);
const roles = ref([]);
const permisos = ref([]);

async function cargar() {
  const d = await api('/permisos');
  recursos.value = d.recursos; roles.value = d.roles; permisos.value = d.permisos;
}
onMounted(cargar);

function celda(rol, recursoId) {
  return permisos.value.find((p) => p.rol === rol && p.recurso_id === recursoId)
    || { rol, recurso_id: recursoId, ver: false, crear: false, editar: false, eliminar: false };
}

async function toggle(rol, recursoId, accion) {
  const c = { ...celda(rol, recursoId) };
  c[accion] = !c[accion];
  try {
    await api('/permisos', { method: 'PUT', body: { rol, recurso_id: recursoId, ver: !!c.ver, crear: !!c.crear, editar: !!c.editar, eliminar: !!c.eliminar } });
    toast.exito('Permiso actualizado'); cargar();
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Matriz de Acceso</h1>
    <p class="subtitulo">Control fino rol × recurso (ver / crear / editar / eliminar)</p>

    <div class="card" v-for="rol in roles" :key="rol">
      <h3 style="text-transform:capitalize">{{ rol }}</h3>
      <table>
        <thead><tr><th>Recurso</th><th>Ver</th><th>Crear</th><th>Editar</th><th>Eliminar</th></tr></thead>
        <tbody>
          <tr v-for="r in recursos" :key="r.id">
            <td>{{ r.nombre }}</td>
            <td v-for="a in ['ver','crear','editar','eliminar']" :key="a">
              <input type="checkbox" :checked="celda(rol, r.id)[a]" @change="toggle(rol, r.id, a)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
