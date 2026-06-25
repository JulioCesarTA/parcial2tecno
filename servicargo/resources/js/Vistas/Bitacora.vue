<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';

const lista = ref([]);
const filtro = ref('');

async function cargar() {
  lista.value = await api('/bitacora', { params: filtro.value ? { accion: filtro.value } : {} });
}
onMounted(cargar);

function badge(a) {
  return { login_ok: 'exito', login_fallido: 'error', logout: 'info', acceso_recurso: '', accion: 'aviso' }[a] || '';
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Bitácora</h1>
    <p class="subtitulo">Auditoría de accesos y acciones</p>
    <div class="card">
      <div class="fila-acciones">
        <select class="input" style="max-width:220px" v-model="filtro" @change="cargar">
          <option value="">Todas las acciones</option>
          <option value="login_ok">Login OK</option>
          <option value="login_fallido">Login fallido</option>
          <option value="logout">Logout</option>
          <option value="acceso_recurso">Acceso a recurso</option>
          <option value="accion">Acción</option>
        </select>
      </div>
    </div>
    <div class="card">
      <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Recurso</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="b in lista" :key="b.id">
            <td>{{ new Date(b.fecha).toLocaleString() }}</td>
            <td>{{ b.usuario?.nombre || '—' }}</td>
            <td><span class="badge" :class="badge(b.accion)">{{ b.accion }}</span></td>
            <td>{{ b.recurso }}</td><td>{{ b.detalle }}</td><td>{{ b.ip }}</td>
          </tr>
          <tr v-if="!lista.length"><td colspan="6" style="color:var(--color-texto-suave)">Sin registros</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
