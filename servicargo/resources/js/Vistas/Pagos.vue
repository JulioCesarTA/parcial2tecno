<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast, useConfirm } from '../servicios/useUI';

const { sesion } = useAuth();
const toast = useToast();
const { confirmar } = useConfirm();
const esCliente = computed(() => sesion.usuario?.rol === 'cliente');

const ventas = ref([]);
const pagos = ref([]);
const metodos = ref([]);
const modal = ref(false);
const ventaSel = ref(null);
const form = ref({ monto: '', metodo_pago: 'QR', metodo_pago_id: '', numero_cuota: '' });
const qr = ref(null);

// Registro de método de pago
const nuevoMetodo = ref({ tipo: esCliente.value ? 'QR' : 'EFECTIVO', alias: '', referencia: '' });

async function cargar() {
  ventas.value = (await api('/ventas')).filter((v) => v.estado !== 'PAGADA');
  pagos.value = await api('/pagos');
  metodos.value = await api('/metodos-pago');
}
onMounted(cargar);

/* ---------- Métodos de pago ---------- */
async function registrarMetodo() {
  try {
    await api('/metodos-pago', { method: 'POST', body: nuevoMetodo.value });
    toast.exito('Método de pago registrado');
    nuevoMetodo.value = { tipo: esCliente.value ? 'QR' : 'EFECTIVO', alias: '', referencia: '' };
    cargar();
  } catch (e) { toast.error(e.message); }
}
async function eliminarMetodo(m) {
  if (!(await confirmar({ mensaje: `¿Eliminar el método "${m.alias}"?` }))) return;
  try { await api('/metodos-pago/' + m.id, { method: 'DELETE' }); toast.exito('Eliminado'); cargar(); }
  catch (e) { toast.error(e.message); }
}

// Métodos usables para pagar (cliente: solo QR)
const metodosUsables = computed(() =>
  metodos.value.filter((m) => m.activo && (!esCliente.value || m.tipo === 'QR'))
);

/* ---------- Pagar ---------- */
function pagar(v) {
  ventaSel.value = v;
  form.value = { monto: v.total_final, metodo_pago: esCliente.value ? 'QR' : 'EFECTIVO', metodo_pago_id: '', numero_cuota: '' };
  qr.value = null;
  modal.value = true;
}
function aplicarMetodo() {
  const m = metodos.value.find((x) => x.id == form.value.metodo_pago_id);
  if (m) form.value.metodo_pago = m.tipo;
}

async function registrar() {
  try {
    const body = { venta_id: ventaSel.value.id, monto: form.value.monto, metodo_pago: form.value.metodo_pago };
    if (form.value.metodo_pago_id) body.metodo_pago_id = form.value.metodo_pago_id;
    if (form.value.numero_cuota) body.numero_cuota = form.value.numero_cuota;
    const d = await api('/pagos', { method: 'POST', body });
    if (form.value.metodo_pago === 'QR') {
      qr.value = { pago_id: d.pago_id, qr: d.qr };
      toast.info('QR generado. Confirma el pago.');
    } else {
      toast.exito(`Pago registrado. Factura ${d.factura.numero_factura}. Venta: ${d.estado_venta}`);
      modal.value = false; cargar();
    }
  } catch (e) { toast.error(e.message); }
}
async function confirmarQR() {
  try {
    const d = await api(`/pagos/${qr.value.pago_id}/simular-confirmacion`, { method: 'POST' });
    toast.exito(`Pago QR confirmado. Factura ${d.factura.numero_factura}. Venta: ${d.estado_venta}`);
    modal.value = false; cargar();
  } catch (e) { toast.error(e.message); }
}
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Pagos</h1>
    <p class="subtitulo">{{ esCliente ? 'Paga tus ventas con QR' : 'Cobro de ventas (efectivo o QR)' }}</p>

    <!-- Registro de métodos de pago (punto 10) -->
    <div class="card">
      <h3>Mis métodos de pago</h3>
      <form @submit.prevent="registrarMetodo" class="fila-acciones" style="align-items:flex-end">
        <div><label>Tipo</label>
          <select class="input" v-model="nuevoMetodo.tipo">
            <option value="QR">QR</option>
            <option v-if="!esCliente" value="EFECTIVO">Efectivo</option>
          </select>
        </div>
        <div style="flex:1"><label>Alias</label><input class="input" v-model="nuevoMetodo.alias" placeholder="Ej. Mi QR personal" /></div>
        <div><label>Referencia (opcional)</label><input class="input" v-model="nuevoMetodo.referencia" /></div>
        <button class="btn">Registrar método</button>
      </form>
      <table style="margin-top:12px">
        <thead><tr><th>Tipo</th><th>Alias</th><th>Referencia</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="m in metodos" :key="m.id">
            <td><span class="badge info">{{ m.tipo }}</span></td><td>{{ m.alias }}</td><td>{{ m.referencia || '—' }}</td>
            <td><span class="badge" :class="m.activo?'exito':'error'">{{ m.activo?'Activo':'Inactivo' }}</span></td>
            <td><button class="btn chico peligro" @click="eliminarMetodo(m)">Eliminar</button></td>
          </tr>
          <tr v-if="!metodos.length"><td colspan="5" style="color:var(--color-texto-suave)">Aún no registraste métodos de pago</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Ventas por cobrar</h3>
      <table>
        <thead><tr><th>Código</th><th>Total</th><th>Pago</th><th>Cuotas</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr v-for="v in ventas" :key="v.id">
            <td>{{ v.codigo }}</td><td>Bs {{ v.total_final }}</td><td>{{ v.tipo_pago }}</td><td>{{ v.numero_cuotas }}</td>
            <td><span class="badge aviso">{{ v.estado }}</span></td>
            <td><button class="btn chico" @click="pagar(v)">Pagar</button></td>
          </tr>
          <tr v-if="!ventas.length"><td colspan="6" style="color:var(--color-texto-suave)">No hay ventas pendientes</td></tr>
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

    <div v-if="modal" class="modal-fondo" @click.self="modal=false">
      <div class="modal">
        <h3 style="margin-top:0">Pagar venta {{ ventaSel?.codigo }}</h3>
        <template v-if="!qr">
          <form @submit.prevent="registrar">
            <label>Monto (Bs)</label><input class="input" type="number" step="0.01" v-model="form.monto" />

            <label>Método registrado</label>
            <select class="input" v-model="form.metodo_pago_id" @change="aplicarMetodo">
              <option value="">— Elegir manualmente —</option>
              <option v-for="m in metodosUsables" :key="m.id" :value="m.id">{{ m.tipo }} · {{ m.alias }}</option>
            </select>

            <label>Tipo de pago</label>
            <select class="input" v-model="form.metodo_pago" :disabled="esCliente || !!form.metodo_pago_id">
              <option value="QR">QR</option>
              <option v-if="!esCliente" value="EFECTIVO">Efectivo</option>
            </select>

            <label v-if="ventaSel?.tipo_pago==='CREDITO'">Nº de cuota (plan de pagos)</label>
            <input v-if="ventaSel?.tipo_pago==='CREDITO'" class="input" type="number" v-model="form.numero_cuota" />

            <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
              <button type="button" class="btn secundario" @click="modal=false">Cancelar</button>
              <button class="btn">{{ form.metodo_pago === 'QR' ? 'Generar QR' : 'Registrar pago' }}</button>
            </div>
          </form>
        </template>
        <template v-else>
          <div style="text-align:center; padding:14px">
            <div style="background:#fff; border:2px dashed var(--color-borde); border-radius:12px; padding:24px; font-family:monospace; font-size:11px; word-break:break-all; color:#000">
              {{ qr.qr }}
            </div>
            <p style="color:var(--color-texto-suave); margin-top:10px">QR PagoFácil (simulado). Confirma para registrar el pago.</p>
            <div class="fila-acciones" style="justify-content:center; margin-top:8px">
              <button class="btn secundario" @click="modal=false">Cerrar</button>
              <button class="btn acento" @click="confirmarQR">Simular confirmación</button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
