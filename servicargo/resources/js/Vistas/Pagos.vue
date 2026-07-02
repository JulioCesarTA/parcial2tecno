<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast } from '../servicios/useUI';

const { sesion } = useAuth();
const toast = useToast();
const esCliente = computed(() => sesion.usuario?.rol === 'cliente');
const esAdmin = computed(() => sesion.usuario?.rol === 'admin');

const ventas = ref([]);
const pagos = ref([]);
const modal = ref(false);
const ventaSel = ref(null);
const form = ref({ monto: '', metodo_pago: 'QR', numero_cuota: '' });
const qr = ref(null);            // { pago_id, qr_base64, expira_en }
const esperandoQr = ref(false);  // polling en curso
const qrVencido = ref(false);
let pollTimer = null;

async function cargar() {
  ventas.value = (await api('/ventas')).filter((v) => v.estado !== 'PAGADA');
  pagos.value = await api('/pagos');
}
onMounted(cargar);

/* ---------- Cálculos por venta ---------- */
const num = (x) => Number(x || 0);
function pagadoDe(v) {
  return pagos.value
    .filter((p) => p.venta_id === v.id && p.estado === 'REGISTRADO')
    .reduce((s, p) => s + num(p.monto), 0);
}
function saldoDe(v) {
  return Math.max(0, num(v.total_final) - pagadoDe(v));
}
function tieneQrPendiente(v) {
  return pagos.value.some(
    (p) => p.venta_id === v.id && p.metodo_pago === 'QR' && p.estado === 'PENDIENTE',
  );
}

/* Monto de una cuota individual: en CONTADO es el total (1 sola "cuota"); en
 * CRÉDITO se reparte el total en partes iguales entre el número de cuotas. */
function montoCuota(v) {
  if (!v) return 0;
  return v.tipo_pago === 'CREDITO' ? num(v.total_final) / (v.numero_cuotas || 1) : num(v.total_final);
}
function pagadoCuota(v, numeroCuota) {
  if (!v) return 0;
  return pagos.value
    .filter((p) => p.venta_id === v.id && p.estado === 'REGISTRADO' && (
      v.tipo_pago === 'CREDITO' ? Number(p.numero_cuota) === Number(numeroCuota) : true
    ))
    .reduce((s, p) => s + num(p.monto), 0);
}
function saldoCuota(v, numeroCuota) {
  return Math.max(0, montoCuota(v) - pagadoCuota(v, numeroCuota));
}

/* ---------- Pago total (solo admin): completa lo que falte para dejar
 * la venta (CONTADO) o la cuota seleccionada (CRÉDITO) como pagada. ---------- */
function pagoTotal() {
  if (!ventaSel.value) return;
  if (ventaSel.value.tipo_pago === 'CREDITO') {
    if (!form.value.numero_cuota) {
      toast.error('Selecciona primero el número de cuota a pagar.');
      return;
    }
    const saldo = saldoCuota(ventaSel.value, form.value.numero_cuota);
    if (saldo <= 0) {
      toast.info('Esa cuota ya está completamente pagada.');
      return;
    }
    form.value.monto = saldo.toFixed(2);
  } else {
    const saldo = saldoDe(ventaSel.value);
    if (saldo <= 0) {
      toast.info('Esta venta ya está completamente pagada.');
      return;
    }
    form.value.monto = saldo.toFixed(2);
  }
}

/* ---------- Abrir modal de pago ---------- */
function pagar(v) {
  ventaSel.value = v;
  form.value = {
    monto: saldoDe(v) || num(v.total_final),
    metodo_pago: esCliente.value ? 'QR' : 'EFECTIVO',
    numero_cuota: '',
  };
  qr.value = null;
  qrVencido.value = false;
  modal.value = true;
}

/* ---------- Registrar pago / generar QR ---------- */
async function registrar() {
  try {
    const body = {
      venta_id: ventaSel.value.id,
      monto: form.value.monto,
      metodo_pago: form.value.metodo_pago,
    };
    if (form.value.numero_cuota) body.numero_cuota = form.value.numero_cuota;
    const d = await api('/pagos', { method: 'POST', body });
    if (form.value.metodo_pago === 'QR') {
      qr.value = { pago_id: d.pago_id, qr_base64: d.qr_base64, expira_en: d.expira_en };
      qrVencido.value = false;
      toast.info('QR generado. Escanéalo con tu app bancaria.');
      iniciarPolling();
    } else {
      toast.exito(`Pago registrado. Factura ${d.factura.numero_factura}. Venta: ${d.estado_venta}`);
      cerrarModal();
      cargar();
    }
  } catch (e) { toast.error(e.message); }
}

/* Genera un QR nuevo (el anterior queda superado). Sin límite de intentos. */
async function generarNuevoQr() {
  form.value.metodo_pago = 'QR';
  qr.value = null;
  qrVencido.value = false;
  detenerPolling();
  await registrar();
}

/* ---------- Volver a ver el QR pendiente de una venta ---------- */
async function verQr(v) {
  try {
    const d = await api(`/ventas/${v.id}/qr-activo`);
    if (!d) {
      toast.info('Ese QR ya no está vigente (vencido, anulado o ya pagado). Genera uno nuevo desde "Pagar".');
      cargar(); // el backend pudo haber cambiado el estado del pago/venta: refrescamos
      return;
    }
    ventaSel.value = v;
    form.value = {
      monto: d.monto,
      metodo_pago: 'QR',
      numero_cuota: d.numero_cuota || '',
    };
    qr.value = { pago_id: d.pago_id, qr_base64: d.qr_base64, expira_en: d.expira_en };
    qrVencido.value = !!d.vencido;
    modal.value = true;
    if (!d.vencido) iniciarPolling();
  } catch (e) { toast.error(e.message); }
}

/* ---------- QR: polling del estado en PagoFácil ---------- */
function iniciarPolling() {
  detenerPolling();
  esperandoQr.value = true;
  pollTimer = setInterval(verificarPago, 4000);
}
function detenerPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  esperandoQr.value = false;
}
async function verificarPago(manual = false) {
  if (!qr.value) return;
  try {
    const d = await api(`/pagos/${qr.value.pago_id}/estado-qr`);
    if (d.pagado) {
      detenerPolling();
      toast.exito(`Pago QR confirmado. Factura ${d.factura?.numero_factura ?? ''}. Venta: ${d.estado_venta}`);
      cerrarModal();
      cargar();
    } else if (d.estado === 'EXPIRADO' || d.estado === 'ANULADO') {
      detenerPolling();
      qrVencido.value = true;
      if (manual) toast.info('El QR ya no está vigente. Genera uno nuevo para pagar.');
    } else if (manual) {
      toast.info('El pago aún no se ha registrado. Intenta de nuevo en unos segundos.');
    }
  } catch (e) { if (manual) toast.error(e.message); }
}

function cerrarModal() {
  detenerPolling();
  modal.value = false;
  qr.value = null;
  qrVencido.value = false;
}
onUnmounted(detenerPolling);
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Pagos</h1>
    <p class="subtitulo">{{ esCliente ? 'Paga tus ventas con QR' : 'Cobro de ventas (efectivo o QR)' }}</p>

    <div class="card">
      <h3>Ventas por cobrar</h3>
      <table>
        <thead><tr><th>Código</th><th>Total</th><th>Pagado</th><th>Saldo</th><th>Pago</th><th>Cuotas</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="v in ventas" :key="v.id">
            <td>{{ v.codigo }}</td>
            <td>Bs {{ v.total_final }}</td>
            <td>Bs {{ pagadoDe(v).toFixed(2) }}</td>
            <td>Bs {{ saldoDe(v).toFixed(2) }}</td>
            <td>{{ v.tipo_pago }}</td>
            <td>{{ v.numero_cuotas }}</td>
            <td><span class="badge aviso">{{ v.estado }}</span></td>
            <td class="fila-acciones">
              <button class="btn chico" @click="pagar(v)">Pagar</button>
              <button v-if="tieneQrPendiente(v)" class="btn chico secundario" @click="verQr(v)">Ver QR</button>
            </td>
          </tr>
          <tr v-if="!ventas.length"><td colspan="8" style="color:var(--color-texto-suave)">No hay ventas pendientes</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Historial de pagos</h3>
      <table>
        <thead><tr><th>#</th><th>Venta</th><th>Método</th><th>Monto</th><th>Cuota</th><th>Estado</th><th>Fecha</th></tr></thead>
        <tbody>
          <tr v-for="p in pagos" :key="p.id">
            <td>{{ p.id }}</td><td>{{ p.venta?.codigo }}</td><td>{{ p.metodo_pago }}</td>
            <td>Bs {{ p.monto }}</td><td>{{ p.numero_cuota || '—' }}</td>
            <td><span class="badge" :class="p.estado==='REGISTRADO' ? 'exito':'aviso'">{{ p.estado }}</span></td>
            <td>{{ new Date(p.fecha_pago).toLocaleDateString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal" class="modal-fondo" @click.self="cerrarModal">
      <div class="modal">
        <h3 style="margin-top:0">Pagar venta {{ ventaSel?.codigo }}</h3>

        <!-- Resumen de la venta -->
        <div class="resumen-pago">
          <div><span>Código</span><strong>{{ ventaSel?.codigo }}</strong></div>
          <div><span>Forma de pago</span><strong>{{ ventaSel?.tipo_pago }}</strong></div>
          <div v-if="ventaSel?.tipo_pago==='CREDITO'"><span>Cuotas</span><strong>{{ ventaSel?.numero_cuotas }}</strong></div>
          <div><span>Total venta</span><strong>Bs {{ ventaSel?.total_final }}</strong></div>
          <div><span>Pagado</span><strong>Bs {{ pagadoDe(ventaSel).toFixed(2) }}</strong></div>
          <div><span>Saldo</span><strong>Bs {{ saldoDe(ventaSel).toFixed(2) }}</strong></div>
        </div>

        <template v-if="!qr">
          <form @submit.prevent="registrar">
            <template v-if="ventaSel?.tipo_pago==='CREDITO'">
              <label>Nº de cuota (plan de pagos)</label>
              <input class="input" type="number" min="1" :max="ventaSel?.numero_cuotas" v-model="form.numero_cuota" />
            </template>

            <label>Monto a pagar (Bs)</label>
            <div class="fila-acciones" style="align-items:flex-start">
              <input class="input" style="flex:1" type="number" step="0.01" min="0.01" v-model="form.monto" />
              <button v-if="esAdmin" type="button" class="btn chico secundario" @click="pagoTotal">Pago total</button>
            </div>
            <p style="color:var(--color-texto-suave); font-size:12px; margin:4px 0 0">
              Editable: para pruebas puedes solicitar un monto menor por QR.
              <span v-if="esAdmin"> Como administrador puedes usar "Pago total" para completar {{ ventaSel?.tipo_pago==='CREDITO' ? 'la cuota seleccionada' : 'el saldo de la venta' }} de una sola vez.</span>
            </p>

            <label>Tipo de pago</label>
            <select class="input" v-model="form.metodo_pago" :disabled="esCliente">
              <option value="QR">QR</option>
              <option v-if="!esCliente" value="EFECTIVO">Efectivo</option>
            </select>
            <p v-if="esCliente" style="color:var(--color-texto-suave); font-size:12px; margin:4px 0 0">
              Desde tu cuenta solo puedes pagar con QR. Para pagar en efectivo acude a la tienda.
            </p>

            <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
              <button type="button" class="btn secundario" @click="cerrarModal">Cancelar</button>
              <button class="btn">{{ form.metodo_pago === 'QR' ? 'Generar QR' : 'Registrar pago' }}</button>
            </div>
          </form>
        </template>

        <template v-else>
          <div style="text-align:center; padding:14px">
            <div style="background:#fff; border:2px solid var(--color-borde); border-radius:12px; padding:16px; display:inline-block">
              <img :src="'data:image/png;base64,' + qr.qr_base64" alt="QR de pago" style="width:240px; height:240px; display:block" />
            </div>
            <p style="margin-top:12px; font-weight:600">Escanea el QR con tu app bancaria</p>
            <p v-if="qr.expira_en" style="color:var(--color-texto-suave); font-size:13px; margin-top:4px">
              Válido hasta: {{ new Date(qr.expira_en).toLocaleString() }}
            </p>
            <p style="font-size:13px; margin-top:8px">
              <span v-if="qrVencido" class="badge error">QR vencido — genera uno nuevo</span>
              <span v-else-if="esperandoQr" style="color:var(--color-texto-suave)">⏳ Esperando confirmación del pago…</span>
              <span v-else style="color:var(--color-texto-suave)">Verificando…</span>
            </p>
            <div class="fila-acciones" style="justify-content:center; margin-top:8px; flex-wrap:wrap">
              <button class="btn secundario" @click="cerrarModal">Cerrar</button>
              <button class="btn acento" :disabled="qrVencido" @click="verificarPago(true)">Ya pagué / Verificar</button>
              <button class="btn" @click="generarNuevoQr">Generar nuevo QR</button>
            </div>
            <p style="color:var(--color-texto-suave); font-size:12px; margin-top:10px">
              Si el QR vence, tu venta sigue activa: genera uno nuevo cuando quieras.
            </p>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.resumen-pago {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px 16px;
  background: var(--color-fondo-suave, #f6f8fa);
  border: 1px solid var(--color-borde);
  border-radius: 10px;
  padding: 12px 14px;
  margin: 4px 0 16px;
}
.resumen-pago > div { display: flex; flex-direction: column; }
.resumen-pago span { color: var(--color-texto-suave); font-size: 12px; }
.resumen-pago strong { font-size: 15px; }
</style>
