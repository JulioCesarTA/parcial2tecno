<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useToast } from '../servicios/useUI';

const toast = useToast();
const lista = ref([]);
const productos = ref([]);
const almacenes = ref([]);
const filtroAlmacen = ref('*');
const modal = ref(false);
const errores = ref({});
const form = ref({ producto_id: '', almacen_id: '', cantidad: '', tipo: 'INGRESO' });

async function cargar() {
  lista.value = await api('/inventario', { params: { almacen_id: filtroAlmacen.value } });
}
onMounted(async () => {
  productos.value = await api('/productos');
  almacenes.value = await api('/almacenes').catch(() => []);
  cargar();
});

function nuevo() { form.value = { producto_id: '', almacen_id: '', cantidad: '', tipo: 'INGRESO' }; errores.value = {}; modal.value = true; }
async function registrar() {
  errores.value = {};
  try {
    const d = await api('/inventario/movimiento', { method: 'POST', body: form.value });
    toast.exito(d.message + ' Stock: ' + d.cantidad_final); modal.value = false; cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Inventario</h1>
    <p class="subtitulo">Stock por producto y almacén</p>
    <div class="card">
      <div class="fila-acciones" style="justify-content:space-between">
        <select class="input" style="max-width:240px" v-model="filtroAlmacen" @change="cargar">
          <option value="*">Todos los almacenes</option>
          <option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option>
        </select>
        <button class="btn" @click="nuevo">+ Registrar movimiento</button>
      </div>
    </div>
    <div class="card">
      <table>
        <thead><tr><th>Producto</th><th>Almacén</th><th>Cantidad</th><th>Stock mínimo</th><th>Nivel</th></tr></thead>
        <tbody>
          <tr v-for="(i, idx) in lista" :key="idx">
            <td>{{ i.producto?.nombre }}</td><td>{{ i.almacen?.nombre }}</td>
            <td>{{ i.cantidad }}</td><td>{{ i.stock_minimo }}</td>
            <td><span class="badge" :class="i.cantidad <= i.stock_minimo ? 'error' : 'exito'">{{ i.cantidad <= i.stock_minimo ? 'BAJO' : 'OK' }}</span></td>
          </tr>
          <tr v-if="!lista.length"><td colspan="5" style="color:var(--color-texto-suave)">Sin registros</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal" class="modal-fondo" @click.self="modal=false">
      <div class="modal">
        <h3 style="margin-top:0">Movimiento de inventario</h3>
        <form @submit.prevent="registrar">
          <label>Producto</label><select class="input" v-model="form.producto_id"><option value="">—</option><option v-for="p in productos" :key="p.id" :value="p.id">{{ p.codigo }} — {{ p.nombre }}</option></select><div v-if="errores.producto_id" class="error-campo">{{ errores.producto_id[0] }}</div>
          <label>Almacén</label><select class="input" v-model="form.almacen_id"><option value="">—</option><option v-for="a in almacenes" :key="a.id" :value="a.id">{{ a.nombre }}</option></select><div v-if="errores.almacen_id" class="error-campo">{{ errores.almacen_id[0] }}</div>
          <div class="grid grid-2">
            <div><label>Cantidad</label><input class="input" type="number" v-model="form.cantidad" /><div v-if="errores.cantidad" class="error-campo">{{ errores.cantidad[0] }}</div></div>
            <div><label>Tipo</label><select class="input" v-model="form.tipo"><option value="INGRESO">Ingreso</option><option value="SALIDA">Salida</option></select></div>
          </div>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modal=false">Cancelar</button><button class="btn">Registrar</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
