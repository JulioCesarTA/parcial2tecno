<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';

const { sesion, puede } = useAuth();
const toast = useToast();
const esCliente = computed(() => sesion.usuario?.rol === 'cliente');
const puedeCrear = puede('ventas', 'crear');

const lista = ref([]);
const encomiendas = ref([]);
const clientes = ref([]);
const modal = ref(false);
const errores = ref({});
const form = ref(vacio());

function vacio() {
  return { cliente_id: '', vendedor_id: sesion.usuario?.id, encomienda_id: '', impuestos: 0, descuento: 0, tipo_pago: 'CONTADO', numero_cuotas: 2 };
}

async function cargar() { lista.value = await api('/ventas'); }
onMounted(async () => {
  await cargar();
  if (puedeCrear) {
    encomiendas.value = await api('/encomiendas').catch(() => []);
    clientes.value = await api('/usuarios', { params: { rol: 'cliente' } }).catch(() => []);
  }
});

function nueva() { form.value = vacio(); errores.value = {}; modal.value = true; }
function autocompletarCliente() {
  const e = encomiendas.value.find((x) => x.id == form.value.encomienda_id);
  if (e) form.value.cliente_id = e.cliente_id;
}
async function guardar() {
  errores.value = {};
  try {
    const d = await api('/ventas', { method: 'POST', body: form.value });
    toast.exito(`Venta ${d.codigo} creada (total Bs ${d.total_final})`);
    modal.value = false; cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}
function badge(e) { return { PENDIENTE: 'aviso', PARCIAL: 'info', PAGADA: 'exito' }[e] || ''; }
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Ventas</h1>
    <p class="subtitulo">Notas de venta sobre encomiendas</p>
    <div class="card" v-if="puedeCrear">
      <div class="fila-acciones" style="justify-content:flex-end"><button class="btn" @click="nueva">+ Nueva nota de venta</button></div>
    </div>
    <div class="card">
      <table>
        <thead><tr><th>Código</th><th>Cliente</th><th>Encomienda</th><th>Total</th><th>Pago</th><th>Cuotas</th><th>Estado</th></tr></thead>
        <tbody>
          <tr v-for="v in lista" :key="v.id">
            <td><strong>{{ v.codigo }}</strong></td>
            <td>{{ v.cliente?.nombre }}</td>
            <td>Guía {{ v.encomienda?.guia_rastreo }}</td>
            <td>Bs {{ v.total_final }}</td>
            <td>{{ v.tipo_pago }}</td>
            <td>{{ v.numero_cuotas }}</td>
            <td><span class="badge" :class="badge(v.estado)">{{ v.estado }}</span></td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" style="color:var(--color-texto-suave)">Sin ventas</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal" class="modal-fondo" @click.self="modal=false">
      <div class="modal">
        <h3 style="margin-top:0">Nueva nota de venta</h3>
        <form @submit.prevent="guardar">
          <label>Encomienda</label>
          <select class="input" v-model="form.encomienda_id" @change="autocompletarCliente">
            <option value="">—</option>
            <option v-for="e in encomiendas" :key="e.id" :value="e.id">Guía {{ e.guia_rastreo }} — {{ e.destino }}</option>
          </select>
          <div v-if="errores.encomienda_id" class="error-campo">{{ errores.encomienda_id[0] }}</div>
          <label>Cliente</label>
          <select class="input" v-model="form.cliente_id"><option value="">—</option><option v-for="u in clientes" :key="u.id" :value="u.id">{{ u.nombre }} {{ u.apellido }}</option></select>
          <div v-if="errores.cliente_id" class="error-campo">{{ errores.cliente_id[0] }}</div>
          <div class="grid grid-2">
            <div><label>Impuestos (Bs)</label><input class="input" type="number" step="0.01" v-model="form.impuestos" /></div>
            <div><label>Descuento (Bs)</label><input class="input" type="number" step="0.01" v-model="form.descuento" /></div>
            <div><label>Tipo de pago</label><select class="input" v-model="form.tipo_pago"><option value="CONTADO">Contado</option><option value="CREDITO">Crédito</option></select></div>
            <div v-if="form.tipo_pago==='CREDITO'"><label>Nº cuotas (≥2)</label><input class="input" type="number" min="2" v-model="form.numero_cuotas" /><div v-if="errores.numero_cuotas" class="error-campo">{{ errores.numero_cuotas[0] }}</div></div>
          </div>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modal=false">Cancelar</button><button class="btn">Crear venta</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
