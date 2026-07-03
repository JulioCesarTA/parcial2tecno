<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast, useConfirm } from '../servicios/useUI';

const { sesion, puede } = useAuth();
const toast = useToast();
const { confirmar } = useConfirm();

const esCliente = computed(() => sesion.usuario?.rol === 'cliente');
const puedeCrear = puede('cotizaciones', 'crear');

const lista = ref([]);
const clientes = ref([]);
const productos = ref([]);
const modalCrear = ref(false);
const modalVer = ref(false);
const detalle = ref(null);
const errores = ref({});
const form = ref(vacio());

function vacio() {
  return {
    cliente_id: '', vendedor_id: sesion.usuario?.id, remitente: '', destinatario: '',
    contenido: '', origen: '', destino: '', tipo_envio: 'terrestre', peso_kg: '', volumen_m3: '',
    fecha_entrega_estimada: '', impuestos: 0, validez_dias: 5,
    productos: [{ producto_id: '', cantidad: 1 }],
  };
}

async function cargar() {
  try {
    lista.value = await api('/cotizaciones');
  } catch (e) {
    toast.error(e.message);
  }
}
onMounted(async () => {
  await cargar();
  if (!esCliente.value) {
    clientes.value = (await api('/usuarios', { params: { rol: 'cliente' } }).catch(() => []));
    productos.value = (await api('/productos').catch(() => []));
  }
});

function nueva() { form.value = vacio(); errores.value = {}; modalCrear.value = true; }
function agregarLinea() { form.value.productos.push({ producto_id: '', cantidad: 1 }); }
function quitarLinea(i) { form.value.productos.splice(i, 1); }

async function guardar() {
  errores.value = {};
  try {
    const d = await api('/cotizaciones', { method: 'POST', body: form.value });
    toast.exito(`Cotización #${d.id} creada (total Bs ${d.total_estimado})`);
    modalCrear.value = false; cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}

async function ver(c) {
  detalle.value = await api('/cotizaciones/' + c.id);
  modalVer.value = true;
}

async function decidir(c, decision) {
  const txt = decision === 'si' ? 'aprobar' : 'rechazar';
  if (!(await confirmar({ titulo: 'Confirmar', mensaje: `¿Deseas ${txt} la cotización #${c.id}?` }))) return;
  try {
    await api(`/cotizaciones/${c.id}/aprobar`, { method: 'POST', body: { decision } });
    toast.exito(`Cotización ${decision === 'si' ? 'aprobada' : 'rechazada'}`);
    cargar();
  } catch (e) { toast.error(e.message); }
}

async function generarEncomienda(c) {
  if (!(await confirmar({ titulo: 'Generar encomienda', mensaje: `¿Crear la encomienda de la cotización #${c.id}?` }))) return;
  try {
    const d = await api('/encomiendas', { method: 'POST', body: { cliente_id: c.cliente_id, guia_rastreo: c.id } });
    toast.exito(`Encomienda creada (guía ${d.guia_rastreo})`);
    cargar();
  } catch (e) { toast.error(e.message); }
}

async function eliminar(c) {
  if (!(await confirmar({ mensaje: `¿Eliminar la cotización #${c.id}?` }))) return;
  try { await api('/cotizaciones/' + c.id, { method: 'DELETE' }); toast.exito('Eliminada'); cargar(); } catch (e) { toast.error(e.message); }
}

function badge(estado) {
  return { PENDIENTE: 'aviso', APROBADA: 'info', RECHAZADA: 'error', COMPLETADA: 'exito', VENCIDA: 'error' }[estado] || '';
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Cotizaciones</h1>
    <p class="subtitulo">{{ esCliente ? 'Tus solicitudes de envío' : 'Solicitudes de envío y aprobaciones' }}</p>

    <div class="card" v-if="puedeCrear">
      <div class="fila-acciones" style="justify-content:flex-end"><button class="btn" @click="nueva">+ Nueva cotización</button></div>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>#</th><th>Cliente</th><th>Destino</th><th>Tipo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-for="c in lista" :key="c.id">
            <td>{{ c.id }}</td>
            <td>{{ c.cliente?.nombre }} {{ c.cliente?.apellido }}</td>
            <td>{{ c.origen }} → {{ c.destino }}</td>
            <td>{{ c.tipo_envio }}</td>
            <td>Bs {{ c.total_estimado }}</td>
            <td><span class="badge" :class="badge(c.estado)">{{ c.estado }}</span></td>
            <td class="fila-acciones">
              <button class="btn chico secundario" @click="ver(c)">Ver</button>
              <template v-if="esCliente && c.estado==='PENDIENTE'">
                <button class="btn chico acento" @click="decidir(c,'si')">Aprobar</button>
                <button class="btn chico peligro" @click="decidir(c,'no')">Rechazar</button>
              </template>
              <template v-if="!esCliente">
                <button v-if="c.estado==='APROBADA'" class="btn chico" @click="generarEncomienda(c)">Generar encomienda</button>
                <button v-if="c.estado==='PENDIENTE' && puede('cotizaciones','eliminar')" class="btn chico peligro" @click="eliminar(c)">Eliminar</button>
              </template>
            </td>
          </tr>
          <tr v-if="!lista.length"><td colspan="7" style="color:var(--color-texto-suave)">Sin cotizaciones</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Crear -->
    <div v-if="modalCrear" class="modal-fondo" @click.self="modalCrear=false">
      <div class="modal" style="max-width:680px">
        <h3 style="margin-top:0">Nueva cotización</h3>
        <form @submit.prevent="guardar">
          <div class="grid grid-2">
            <div><label>Cliente</label><select class="input" v-model="form.cliente_id"><option value="">—</option><option v-for="u in clientes" :key="u.id" :value="u.id">{{ u.nombre }} {{ u.apellido }}</option></select><div v-if="errores.cliente_id" class="error-campo">{{ errores.cliente_id[0] }}</div></div>
            <div><label>Tipo de envío</label><select class="input" v-model="form.tipo_envio"><option value="terrestre">Terrestre</option><option value="aereo">Aéreo</option><option value="maritimo">Marítimo</option></select></div>
            <div><label>Remitente</label><input class="input" v-model="form.remitente" /><div v-if="errores.remitente" class="error-campo">{{ errores.remitente[0] }}</div></div>
            <div><label>Destinatario</label><input class="input" v-model="form.destinatario" /><div v-if="errores.destinatario" class="error-campo">{{ errores.destinatario[0] }}</div></div>
            <div><label>Origen</label><input class="input" v-model="form.origen" /><div v-if="errores.origen" class="error-campo">{{ errores.origen[0] }}</div></div>
            <div><label>Destino</label><input class="input" v-model="form.destino" /><div v-if="errores.destino" class="error-campo">{{ errores.destino[0] }}</div></div>
            <div><label>Peso (kg)</label><input class="input" type="number" step="0.01" v-model="form.peso_kg" /><div v-if="errores.peso_kg" class="error-campo">{{ errores.peso_kg[0] }}</div></div>
            <div><label>Volumen (m³)</label><input class="input" type="number" step="0.01" v-model="form.volumen_m3" /><div v-if="errores.volumen_m3" class="error-campo">{{ errores.volumen_m3[0] }}</div></div>
            <div><label>Impuestos (Bs)</label><input class="input" type="number" step="0.01" v-model="form.impuestos" /></div>
            <div><label>Validez (días 1-7)</label><input class="input" type="number" v-model="form.validez_dias" /><div v-if="errores.validez_dias" class="error-campo">{{ errores.validez_dias[0] }}</div></div>
          </div>
          <label>Contenido (solo letras)</label><input class="input" v-model="form.contenido" /><div v-if="errores.contenido" class="error-campo">{{ errores.contenido[0] }}</div>

          <label style="margin-top:14px">Productos / servicios</label>
          <div v-for="(l, i) in form.productos" :key="i" class="fila-acciones" style="margin-bottom:6px">
            <select class="input" v-model="l.producto_id"><option value="">—</option><option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }} (Bs {{ p.precio_unitario }})</option></select>
            <input class="input" type="number" style="max-width:90px" v-model="l.cantidad" />
            <button type="button" class="btn chico peligro" @click="quitarLinea(i)" v-if="form.productos.length>1">×</button>
          </div>
          <button type="button" class="btn chico secundario" @click="agregarLinea">+ Agregar producto</button>
          <div v-if="errores.productos" class="error-campo">{{ errores.productos[0] }}</div>

          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modalCrear=false">Cancelar</button><button class="btn">Crear cotización</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ver -->
    <div v-if="modalVer && detalle" class="modal-fondo" @click.self="modalVer=false">
      <div class="modal" style="max-width:620px">
        <h3 style="margin-top:0">Cotización #{{ detalle.id }} <span class="badge" :class="badge(detalle.estado)">{{ detalle.estado }}</span></h3>
        <p><strong>{{ detalle.remitente }}</strong> → <strong>{{ detalle.destinatario }}</strong></p>
        <p>{{ detalle.origen }} → {{ detalle.destino }} · {{ detalle.tipo_envio }} · {{ detalle.peso_kg }} kg · {{ detalle.volumen_m3 }} m³</p>
        <p>Contenido: {{ detalle.contenido }}</p>
        <table>
          <thead><tr><th>Producto</th><th>Cant.</th><th>P.Unit</th><th>Subtotal</th></tr></thead>
          <tbody>
            <tr v-for="d in detalle.detalles" :key="d.producto_id"><td>{{ d.producto?.nombre }}</td><td>{{ d.cantidad }}</td><td>Bs {{ d.precio_unitario }}</td><td>Bs {{ d.subtotal }}</td></tr>
          </tbody>
        </table>
        <p style="text-align:right; margin-top:10px">Subtotal: Bs {{ detalle.subtotal }} · Impuestos: Bs {{ detalle.impuestos }} · <strong>Total: Bs {{ detalle.total_estimado }}</strong></p>
        <div class="fila-acciones" style="justify-content:flex-end"><button class="btn secundario" @click="modalVer=false">Cerrar</button></div>
      </div>
    </div>
  </AppLayout>
</template>
