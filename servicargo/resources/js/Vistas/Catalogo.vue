<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast, useConfirm } from '../servicios/useUI';

const { puede } = useAuth();
const toast = useToast();
const { confirmar } = useConfirm();

const tab = ref('categorias');
const categorias = ref([]);
const productos = ref([]);
const puedeEditar = puede('catalogo', 'crear');

// Categoría
const formCat = ref({ nombre: '', descripcion: '' });
const editCat = ref(null);
const modalCat = ref(false);
const erroresCat = ref({});

// Producto
const formProd = ref(vacioProd());
const editProd = ref(null);
const modalProd = ref(false);
const erroresProd = ref({});

function vacioProd() {
  return { categoria_id: '', codigo: '', nombre: '', descripcion: '', precio_unitario: '', tipo: 'carga_general' };
}

async function cargar() {
  try {
    categorias.value = await api('/categorias');
    productos.value = await api('/productos');
  } catch (e) { toast.error(e.message); }
}
onMounted(cargar);

/* Categorías */
function nuevaCat() { editCat.value = null; formCat.value = { nombre: '', descripcion: '' }; erroresCat.value = {}; modalCat.value = true; }
function editarCat(c) { editCat.value = c; formCat.value = { nombre: c.nombre, descripcion: c.descripcion || '' }; erroresCat.value = {}; modalCat.value = true; }
async function guardarCat() {
  erroresCat.value = {};
  try {
    if (editCat.value) await api('/categorias/' + editCat.value.id, { method: 'PUT', body: formCat.value });
    else await api('/categorias', { method: 'POST', body: formCat.value });
    toast.exito('Categoría guardada'); modalCat.value = false; cargar();
  } catch (e) { erroresCat.value = e.errors || {}; toast.error(e.message); }
}
async function eliminarCat(c) {
  if (!(await confirmar({ mensaje: `¿Eliminar la categoría "${c.nombre}"?` }))) return;
  try { await api('/categorias/' + c.id, { method: 'DELETE' }); toast.exito('Eliminada'); cargar(); } catch (e) { toast.error(e.message); }
}

/* Productos */
function nuevoProd() { editProd.value = null; formProd.value = vacioProd(); erroresProd.value = {}; modalProd.value = true; }
function editarProd(p) {
  editProd.value = p;
  formProd.value = { categoria_id: p.categoria_id, codigo: p.codigo, nombre: p.nombre, descripcion: p.descripcion || '', precio_unitario: p.precio_unitario, tipo: p.tipo };
  erroresProd.value = {}; modalProd.value = true;
}
async function guardarProd() {
  erroresProd.value = {};
  try {
    const body = { ...formProd.value };
    if (editProd.value) { delete body.codigo; await api('/productos/' + editProd.value.codigo, { method: 'PUT', body }); }
    else await api('/productos', { method: 'POST', body });
    toast.exito('Producto guardado'); modalProd.value = false; cargar();
  } catch (e) { erroresProd.value = e.errors || {}; toast.error(e.message); }
}
async function eliminarProd(p) {
  if (!(await confirmar({ mensaje: `¿Eliminar el producto "${p.nombre}"?` }))) return;
  try { await api('/productos/' + p.codigo, { method: 'DELETE' }); toast.exito('Eliminado'); cargar(); } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Catálogo</h1>
    <p class="subtitulo">Categorías y productos de envío</p>

    <div class="card">
      <div class="fila-acciones">
        <button class="btn" :class="{ secundario: tab !== 'categorias' }" @click="tab='categorias'">Categorías</button>
        <button class="btn" :class="{ secundario: tab !== 'productos' }" @click="tab='productos'">Productos</button>
      </div>
    </div>

    <!-- Categorías -->
    <div v-if="tab==='categorias'" class="card">
      <div class="fila-acciones" style="justify-content:flex-end">
        <button v-if="puedeEditar" class="btn" @click="nuevaCat">+ Nueva categoría</button>
      </div>
      <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th></th></tr></thead>
        <tbody>
          <tr v-for="c in categorias" :key="c.id">
            <td>{{ c.id }}</td><td>{{ c.nombre }}</td><td>{{ c.descripcion }}</td>
            <td class="fila-acciones" v-if="puedeEditar">
              <button class="btn chico secundario" @click="editarCat(c)">Editar</button>
              <button class="btn chico peligro" @click="eliminarCat(c)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Productos -->
    <div v-if="tab==='productos'" class="card">
      <div class="fila-acciones" style="justify-content:flex-end">
        <button v-if="puedeEditar" class="btn" @click="nuevoProd">+ Nuevo producto</button>
      </div>
      <table>
        <thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Precio</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in productos" :key="p.id">
            <td>{{ p.codigo }}</td><td>{{ p.nombre }}</td><td>{{ p.categoria?.nombre }}</td>
            <td><span class="badge">{{ p.tipo }}</span></td><td>Bs {{ p.precio_unitario }}</td>
            <td class="fila-acciones" v-if="puedeEditar">
              <button class="btn chico secundario" @click="editarProd(p)">Editar</button>
              <button class="btn chico peligro" @click="eliminarProd(p)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal categoría -->
    <div v-if="modalCat" class="modal-fondo" @click.self="modalCat=false">
      <div class="modal">
        <h3 style="margin-top:0">{{ editCat ? 'Editar' : 'Nueva' }} categoría</h3>
        <form @submit.prevent="guardarCat">
          <label>Nombre</label><input class="input" v-model="formCat.nombre" /><div v-if="erroresCat.nombre" class="error-campo">{{ erroresCat.nombre[0] }}</div>
          <label>Descripción</label><textarea class="input" v-model="formCat.descripcion"></textarea>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modalCat=false">Cancelar</button><button class="btn">Guardar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal producto -->
    <div v-if="modalProd" class="modal-fondo" @click.self="modalProd=false">
      <div class="modal">
        <h3 style="margin-top:0">{{ editProd ? 'Editar' : 'Nuevo' }} producto</h3>
        <form @submit.prevent="guardarProd">
          <div class="grid grid-2">
            <div><label>Código</label><input class="input" v-model="formProd.codigo" :disabled="!!editProd" /><div v-if="erroresProd.codigo" class="error-campo">{{ erroresProd.codigo[0] }}</div></div>
            <div><label>Categoría</label><select class="input" v-model="formProd.categoria_id"><option value="">—</option><option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option></select><div v-if="erroresProd.categoria_id" class="error-campo">{{ erroresProd.categoria_id[0] }}</div></div>
          </div>
          <label>Nombre</label><input class="input" v-model="formProd.nombre" /><div v-if="erroresProd.nombre" class="error-campo">{{ erroresProd.nombre[0] }}</div>
          <div class="grid grid-2">
            <div><label>Precio unitario</label><input class="input" type="number" step="0.01" v-model="formProd.precio_unitario" /><div v-if="erroresProd.precio_unitario" class="error-campo">{{ erroresProd.precio_unitario[0] }}</div></div>
            <div><label>Tipo</label><select class="input" v-model="formProd.tipo"><option value="carga_general">Carga general</option><option value="fragil">Frágil</option><option value="perecedera">Perecedera</option><option value="peligrosa">Peligrosa</option></select></div>
          </div>
          <label>Descripción</label><textarea class="input" v-model="formProd.descripcion"></textarea>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modalProd=false">Cancelar</button><button class="btn">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
