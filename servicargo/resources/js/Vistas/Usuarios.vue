<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useToast, useConfirm } from '../servicios/useUI';

const toast = useToast();
const { confirmar } = useConfirm();
const lista = ref([]);
const filtroRol = ref('*');
const mostrarForm = ref(false);
const editando = ref(null);
const errores = ref({});
const form = ref(vacio());

function vacio() {
  return { ci: '', nombre: '', apellido: '', correo: '', contrasena: '', rol: 'cliente', telefono: '' };
}

async function cargar() {
  lista.value = await api('/usuarios', { params: { rol: filtroRol.value } });
}
onMounted(cargar);

function nuevo() { editando.value = null; form.value = vacio(); errores.value = {}; mostrarForm.value = true; }
function editar(u) {
  editando.value = u;
  form.value = { ci: u.ci, nombre: u.nombre, apellido: u.apellido, correo: u.correo, contrasena: '', rol: u.rol, telefono: u.telefono || '' };
  errores.value = {};
  mostrarForm.value = true;
}

async function guardar() {
  errores.value = {};
  try {
    const body = { ...form.value };
    if (editando.value && !body.contrasena) delete body.contrasena;
    if (editando.value) await api('/usuarios/' + editando.value.id, { method: 'PUT', body });
    else await api('/usuarios', { method: 'POST', body });
    toast.exito('Usuario guardado');
    mostrarForm.value = false;
    cargar();
  } catch (e) {
    errores.value = e.errors || {};
    toast.error(e.message);
  }
}

async function eliminar(u) {
  if (!(await confirmar({ titulo: 'Eliminar usuario', mensaje: `¿Eliminar a ${u.nombre} (CI ${u.ci})?` }))) return;
  try { await api('/usuarios/' + u.ci, { method: 'DELETE' }); toast.exito('Usuario eliminado'); cargar(); }
  catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Usuarios</h1>
    <p class="subtitulo">Gestión de personal y clientes</p>

    <div class="card">
      <div class="fila-acciones" style="justify-content:space-between">
        <select class="input" style="max-width:200px" v-model="filtroRol" @change="cargar">
          <option value="*">Todos los roles</option>
          <option value="admin">Admin</option>
          <option value="vendedor">Vendedor</option>
          <option value="cliente">Cliente</option>
        </select>
        <button class="btn" @click="nuevo">+ Nuevo usuario</button>
      </div>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>ID</th><th>CI</th><th>Nombre</th><th>Correo</th><th>Rol</th><th>Teléfono</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in lista" :key="u.id">
            <td>{{ u.id }}</td><td>{{ u.ci }}</td>
            <td>{{ u.nombre }} {{ u.apellido }}</td><td>{{ u.correo }}</td>
            <td><span class="badge info">{{ u.rol }}</span></td><td>{{ u.telefono }}</td>
            <td class="fila-acciones">
              <button class="btn chico secundario" @click="editar(u)">Editar</button>
              <button class="btn chico peligro" @click="eliminar(u)">Eliminar</button>
            </td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" style="color:var(--color-texto-suave)">Sin usuarios</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="mostrarForm" class="modal-fondo" @click.self="mostrarForm=false">
      <div class="modal">
        <h3 style="margin-top:0">{{ editando ? 'Editar' : 'Nuevo' }} usuario</h3>
        <form @submit.prevent="guardar">
          <div class="grid grid-2">
            <div><label>CI</label><input class="input" v-model="form.ci" /><div v-if="errores.ci" class="error-campo">{{ errores.ci[0] }}</div></div>
            <div><label>Teléfono</label><input class="input" v-model="form.telefono" /><div v-if="errores.telefono" class="error-campo">{{ errores.telefono[0] }}</div></div>
            <div><label>Nombre</label><input class="input" v-model="form.nombre" /><div v-if="errores.nombre" class="error-campo">{{ errores.nombre[0] }}</div></div>
            <div><label>Apellido</label><input class="input" v-model="form.apellido" /><div v-if="errores.apellido" class="error-campo">{{ errores.apellido[0] }}</div></div>
          </div>
          <label>Correo</label><input class="input" type="email" v-model="form.correo" /><div v-if="errores.correo" class="error-campo">{{ errores.correo[0] }}</div>
          <div class="grid grid-2">
            <div><label>Rol</label><select class="input" v-model="form.rol"><option value="admin">Admin</option><option value="vendedor">Vendedor</option><option value="cliente">Cliente</option></select></div>
            <div><label>Contraseña {{ editando ? '(dejar vacío = no cambiar)' : '' }}</label><input class="input" type="password" v-model="form.contrasena" /><div v-if="errores.contrasena" class="error-campo">{{ errores.contrasena[0] }}</div></div>
          </div>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="mostrarForm=false">Cancelar</button>
            <button class="btn">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
