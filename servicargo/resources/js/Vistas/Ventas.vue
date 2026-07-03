<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import BuscadorLista from '../Componentes/BuscadorLista.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';
import { coincide } from '../servicios/texto';

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

async function cargar() {
  try { lista.value = await api('/ventas'); }
  catch (e) { toast.error(e.message); }
}
onMounted(async () => {
  await cargar();
  if (puedeCrear) {
    encomiendas.value = await api('/encomiendas').catch(() => []);
    clientes.value = await api('/usuarios', { params: { rol: 'cliente' } }).catch(() => []);
  }
  if (esCliente.value) {
    encomiendas.value = await api('/encomiendas').catch(() => []);
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

/* ---------- Solicitar nota de venta (cliente) ---------- */
const modalSolicitar = ref(false);
const formSolicitud = ref(vacioSolicitud());

function vacioSolicitud() {
  return { encomienda_id: '', tipo_pago: 'CONTADO', numero_cuotas: 2 };
}

// Solo encomiendas propias que todavía no tienen nota de venta.
const encomiendasSinVenta = computed(() => {
  const conVenta = new Set(lista.value.map((v) => v.encomienda_id));
  return encomiendas.value.filter((e) => !conVenta.has(e.id));
});

function nuevaSolicitud() { formSolicitud.value = vacioSolicitud(); errores.value = {}; modalSolicitar.value = true; }

async function enviarSolicitud() {
  errores.value = {};
  try {
    const d = await api('/ventas/solicitar', { method: 'POST', body: formSolicitud.value });
    toast.exito(`Nota de venta ${d.codigo} creada por Bs ${d.total_final}. Ya podés pagarla cuando quieras.`);
    modalSolicitar.value = false;
    cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}

/* ---------- Buscador + filtros ---------- */
const q = ref('');
const filtroEstado = ref('');
const filtroTipoPago = ref('');
const estadosVenta = ['PENDIENTE', 'PARCIAL', 'PAGADA'];

function textoBusqueda(v) {
  return `${v.codigo} ${v.cliente?.nombre || ''} ${v.cliente?.apellido || ''} ${v.encomienda?.guia_rastreo || ''}`;
}
const listaFiltrada = computed(() => lista.value.filter((v) => (
  (!filtroEstado.value || v.estado === filtroEstado.value)
  && (!filtroTipoPago.value || v.tipo_pago === filtroTipoPago.value)
  && coincide(textoBusqueda(v), q.value)
)));
const sugerencias = computed(() => {
  if (!q.value.trim()) return [];
  return lista.value
    .filter((v) => coincide(textoBusqueda(v), q.value))
    .slice(0, 6)
    .map((v) => ({
      id: v.id,
      titulo: v.codigo,
      subtitulo: `${v.cliente?.nombre || ''} · Bs ${v.total_final} · ${v.estado}`,
    }));
});
function elegirSugerencia(s) { q.value = s.titulo; }
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Ventas</h1>
    <p class="subtitulo">Notas de venta sobre encomiendas</p>
    <div class="card" v-if="puedeCrear && !esCliente">
      <div class="fila-acciones" style="justify-content:flex-end"><button class="btn" @click="nueva">+ Nueva nota de venta</button></div>
    </div>
    <div class="card" v-if="esCliente">
      <div class="fila-acciones" style="justify-content:space-between; align-items:center">
        <p style="margin:0; color:var(--color-texto-suave)">
          ¿Ya tenés tu encomienda en curso? Pedí vos mismo la nota de venta y elegí cómo pagar.
        </p>
        <button class="btn" :disabled="!encomiendasSinVenta.length" @click="nuevaSolicitud">+ Solicitar nota de venta</button>
      </div>
      <p v-if="!encomiendasSinVenta.length" style="margin:8px 0 0; color:var(--color-texto-suave); font-size:13px">
        No tenés encomiendas pendientes de nota de venta.
      </p>
    </div>
    <div class="card">
      <div class="fila-acciones" style="margin-bottom:14px; flex-wrap:wrap">
        <BuscadorLista
          v-model="q"
          placeholder="Buscar por código, cliente o guía…"
          :sugerencias="sugerencias"
          @elegir="elegirSugerencia"
        />
        <select class="input" style="max-width:170px" v-model="filtroTipoPago">
          <option value="">Contado y crédito</option>
          <option value="CONTADO">Contado</option>
          <option value="CREDITO">Crédito</option>
        </select>
        <select class="input" style="max-width:170px" v-model="filtroEstado">
          <option value="">Todos los estados</option>
          <option v-for="e in estadosVenta" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <table>
        <thead><tr><th>Código</th><th>Cliente</th><th>Encomienda</th><th>Total</th><th>Pago</th><th>Cuotas</th><th>Estado</th></tr></thead>
        <tbody>
          <tr v-for="v in listaFiltrada" :key="v.id">
            <td><strong>{{ v.codigo }}</strong></td>
            <td>{{ v.cliente?.nombre }}</td>
            <td>Guía {{ v.encomienda?.guia_rastreo }}</td>
            <td>Bs {{ v.total_final }}</td>
            <td>{{ v.tipo_pago }}</td>
            <td>{{ v.numero_cuotas }}</td>
            <td><span class="badge" :class="badge(v.estado)">{{ v.estado }}</span></td>
          </tr>
          <tr v-if="!listaFiltrada.length"><td colspan="7" style="color:var(--color-texto-suave)">{{ lista.length ? 'Sin resultados para ese filtro' : 'Sin ventas' }}</td></tr>
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
            <div v-if="form.tipo_pago==='CREDITO'"><label>Nº cuotas (2 o 3)</label><input class="input" type="number" min="2" max="3" v-model="form.numero_cuotas" /><div v-if="errores.numero_cuotas" class="error-campo">{{ errores.numero_cuotas[0] }}</div></div>
          </div>
          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modal=false">Cancelar</button><button class="btn">Crear venta</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Solicitar nota de venta (cliente) -->
    <div v-if="modalSolicitar" class="modal-fondo" @click.self="modalSolicitar=false">
      <div class="modal">
        <h3 style="margin-top:0">Solicitar nota de venta</h3>
        <form @submit.prevent="enviarSolicitud">
          <label>Encomienda</label>
          <select class="input" v-model="formSolicitud.encomienda_id">
            <option value="">—</option>
            <option v-for="e in encomiendasSinVenta" :key="e.id" :value="e.id">Guía {{ e.guia_rastreo }} — {{ e.destino }}</option>
          </select>
          <div v-if="errores.encomienda_id" class="error-campo">{{ errores.encomienda_id[0] }}</div>

          <div class="grid grid-2">
            <div>
              <label>Forma de pago</label>
              <select class="input" v-model="formSolicitud.tipo_pago">
                <option value="CONTADO">Contado</option>
                <option value="CREDITO">Crédito</option>
              </select>
            </div>
            <div v-if="formSolicitud.tipo_pago==='CREDITO'">
              <label>Nº cuotas (2 o 3)</label>
              <input class="input" type="number" min="2" max="3" v-model="formSolicitud.numero_cuotas" />
              <div v-if="errores.numero_cuotas" class="error-campo">{{ errores.numero_cuotas[0] }}</div>
            </div>
          </div>
          <p style="color:var(--color-texto-suave); font-size:12px; margin-top:8px">
            El total se calcula solo, a partir de lo que ya te cotizaron.
          </p>

          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modalSolicitar=false">Cancelar</button><button class="btn">Enviar solicitud</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
