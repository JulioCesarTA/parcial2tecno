<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useToast, useConfirm } from '../servicios/useUI';

const toast = useToast();
const { confirmar } = useConfirm();
const lista = ref([]);
const usuarios = ref([]);
const modal = ref(false);
const editando = ref(null);
const errores = ref({});
const form = ref({ nombre: '', direccion: '', capacidad: '', responsable_id: '' });

async function cargar() {
  try {
    lista.value = await api('/almacenes');
    usuarios.value = await api('/usuarios');
  } catch (e) { toast.error(e.message); }
}
onMounted(cargar);

function nuevo() { editando.value = null; form.value = { nombre: '', direccion: '', capacidad: '', responsable_id: '' }; errores.value = {}; modal.value = true; }
function editar(a) { editando.value = a; form.value = { nombre: a.nombre, direccion: a.direccion, capacidad: a.capacidad, responsable_id: a.responsable_id }; errores.value = {}; modal.value = true; }
async function guardar() {
  errores.value = {};
  try {
    if (editando.value) await api('/almacenes/' + editando.value.id, { method: 'PUT', body: form.value });
    else await api('/almacenes', { method: 'POST', body: form.value });
    toast.exito('Almacén guardado'); modal.value = false; cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}
async function eliminar(a) {
  if (!(await confirmar({ mensaje: `¿Eliminar el almacén "${a.nombre}"?` }))) return;
  try { await api('/almacenes/' + a.id, { method: 'DELETE' }); toast.exito('Eliminado'); cargar(); } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Almacenes</h1>
    <p class="subtitulo">Centros logísticos</p>
    <div class="card">
      <div class="fila-acciones" style="justify-content:flex-end"><button class="btn" @click="nuevo">+ Nuevo almacén</button></div>
      <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Dirección</th><th>Capacidad</th><th>Responsable</th><th></th></tr></thead>
        <tbody>
          <tr v-for="a in lista" :key="a.id">
            <td>{{ a.id }}</td><td>{{ a.nombre }}</td><td>{{ a.direccion }}</td><td>{{ a.capacidad }}</td>
            <td>{{ a.responsable?.nombre }} {{ a.responsable?.apellido }}</td>
            <td class="fila-acciones">
              <button class="btn chico secundario" @click="editar(a)">Editar</button>
              <button class="btn chico peligro" @click="eliminar(a)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal" class="modal-fondo" @click.self="modal=false">
      <div class="modal">
        <h3 style="margin-top:0">{{ editando ? 'Editar' : 'Nuevo' }} almacén</h3>
        <form @submit.prevent="guardar">
          <label>Nombre</label><input class="input" v-model="form.nombre" /><div v-if="errores.nombre" class="error-campo">{{ errores.nombre[0] }}</div>
          <label>Dirección</label><input class="input" v-model="form.direccion" /><div v-if="errores.direccion" class="error-campo">{{ errores.direccion[0] }}</div>
          <div class="grid grid-2">
            <div><label>Capacidad</label><input class="input" type="number" v-model="form.capacidad" /><div v-if="errores.capacidad" class="error-campo">{{ errores.capacidad[0] }}</div></div>
            <div><label>Responsable</label><select class="input" v-model="form.responsable_id"><option value="">—</option><option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.nombre }} ({{ u.rol }})</option></select><div v-if="errores.responsable_id" class="error-campo">{{ errores.responsable_id[0] }}</div></div>
          </div>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modal=false">Cancelar</button><button class="btn">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
