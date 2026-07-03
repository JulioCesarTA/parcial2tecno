<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import BuscadorLista from '../Componentes/BuscadorLista.vue';
import ProductosCotizacion from '../Componentes/ProductosCotizacion.vue';
import { api } from '../servicios/useApi';
import { useAuth } from '../servicios/useAuth';
import { useToast, useConfirm } from '../servicios/useUI';
import { coincide } from '../servicios/texto';
import { descargarPdf } from '../servicios/descargarArchivo';
import { withBase } from '../servicios/useBase';

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

/* ---------- Solicitar cotización (cliente, con fotos) ---------- */
const modalSolicitar = ref(false);
const formSolicitud = ref(vacioSolicitud());
const fotos = ref([]); // File[]
const fotosPreview = ref([]); // { url }[]

function vacioSolicitud() {
  return {
    remitente: '', destinatario: '', contenido: '', origen: '', destino: '',
    tipo_envio: 'terrestre', peso_kg: '', volumen_m3: '', fecha_entrega_estimada: '', validez_dias: 5,
  };
}

function nuevaSolicitud() {
  formSolicitud.value = vacioSolicitud();
  errores.value = {};
  fotos.value = [];
  fotosPreview.value.forEach((f) => URL.revokeObjectURL(f.url));
  fotosPreview.value = [];
  modalSolicitar.value = true;
}

function elegirFotos(evento) {
  const nuevas = Array.from(evento.target.files || []);
  evento.target.value = '';
  if (fotos.value.length + nuevas.length > 5) {
    toast.error('Máximo 5 fotos.');
    return;
  }
  fotos.value.push(...nuevas);
  fotosPreview.value.push(...nuevas.map((f) => ({ url: URL.createObjectURL(f) })));
}
function quitarFoto(i) {
  URL.revokeObjectURL(fotosPreview.value[i].url);
  fotos.value.splice(i, 1);
  fotosPreview.value.splice(i, 1);
}

async function enviarSolicitud() {
  errores.value = {};
  try {
    const body = new FormData();
    Object.entries(formSolicitud.value).forEach(([k, v]) => body.append(k, v ?? ''));
    fotos.value.forEach((f) => body.append('fotos[]', f));
    const d = await api('/cotizaciones/solicitar', { method: 'POST', body });
    toast.exito(`Solicitud #${d.id} enviada. Un asesor la va a revisar.`);
    modalSolicitar.value = false;
    cargar();
  } catch (e) { errores.value = e.errors || {}; toast.error(e.message); }
}

async function ver(c) {
  detalle.value = await api('/cotizaciones/' + c.id);
  modalVer.value = true;
}

function onProductosActualizados(cot) {
  detalle.value = cot;
  cargar();
}

async function descargarCotizacionPdf(c) {
  try { await descargarPdf(`/cotizaciones/${c.id}/pdf`, `cotizacion-${c.id}.pdf`); }
  catch (e) { toast.error(e.message); }
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

/* ---------- Buscador + filtro ---------- */
const q = ref('');
const filtroEstado = ref('');
const estadosCotizacion = ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'COMPLETADA', 'VENCIDA'];

function textoBusqueda(c) {
  return `${c.id} ${c.cliente?.nombre || ''} ${c.cliente?.apellido || ''} ${c.origen} ${c.destino} ${c.tipo_envio}`;
}
const listaFiltrada = computed(() => lista.value.filter((c) => (
  (!filtroEstado.value || c.estado === filtroEstado.value) && coincide(textoBusqueda(c), q.value)
)));
const sugerencias = computed(() => {
  if (!q.value.trim()) return [];
  return lista.value
    .filter((c) => coincide(textoBusqueda(c), q.value))
    .slice(0, 6)
    .map((c) => ({
      id: c.id,
      titulo: `#${c.id} · ${c.origen} → ${c.destino}`,
      subtitulo: `${c.cliente?.nombre || ''} ${c.cliente?.apellido || ''} · ${c.estado}`,
    }));
});
function elegirSugerencia(s) { q.value = String(s.id); }
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Cotizaciones</h1>
    <p class="subtitulo">{{ esCliente ? 'Tus solicitudes de envío' : 'Solicitudes de envío y aprobaciones' }}</p>

    <div class="card" v-if="puedeCrear && !esCliente">
      <div class="fila-acciones" style="justify-content:flex-end"><button class="btn" @click="nueva">+ Nueva cotización</button></div>
    </div>
    <div class="card" v-if="esCliente">
      <div class="fila-acciones" style="justify-content:space-between; align-items:center">
        <p style="margin:0; color:var(--color-texto-suave)">
          ¿Necesitás enviar algo? Pedí tu cotización desde acá, sin ir a la tienda.
        </p>
        <button class="btn" @click="nuevaSolicitud">+ Solicitar cotización</button>
      </div>
    </div>

    <div class="card">
      <div class="fila-acciones" style="margin-bottom:14px; flex-wrap:wrap">
        <BuscadorLista
          v-model="q"
          placeholder="Buscar por cliente, origen o destino…"
          :sugerencias="sugerencias"
          @elegir="elegirSugerencia"
        />
        <select class="input" style="max-width:190px" v-model="filtroEstado">
          <option value="">Todos los estados</option>
          <option v-for="e in estadosCotizacion" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <table>
        <thead><tr><th>#</th><th>Cliente</th><th>Destino</th><th>Tipo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-for="c in listaFiltrada" :key="c.id">
            <td>{{ c.id }}</td>
            <td>{{ c.cliente?.nombre }} {{ c.cliente?.apellido }}</td>
            <td>{{ c.origen }} → {{ c.destino }}</td>
            <td>{{ c.tipo_envio }}</td>
            <td>Bs {{ c.total_estimado }}</td>
            <td><span class="badge" :class="badge(c.estado)">{{ c.estado }}</span></td>
            <td class="fila-acciones">
              <button class="btn chico secundario" @click="ver(c)">Ver</button>
              <button class="btn chico secundario" @click="descargarCotizacionPdf(c)">PDF</button>
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
          <tr v-if="!listaFiltrada.length"><td colspan="7" style="color:var(--color-texto-suave)">{{ lista.length ? 'Sin resultados para ese filtro' : 'Sin cotizaciones' }}</td></tr>
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

    <!-- Solicitar cotización (cliente) -->
    <div v-if="modalSolicitar" class="modal-fondo" @click.self="modalSolicitar=false">
      <div class="modal" style="max-width:680px">
        <h3 style="margin-top:0">Solicitar cotización</h3>
        <p class="subtitulo" style="margin-top:-6px">
          Contanos qué vas a enviar; un asesor revisa estos datos y tus fotos, y te arma la cotización.
        </p>
        <form @submit.prevent="enviarSolicitud">
          <div class="grid grid-2">
            <div><label>Tipo de envío</label><select class="input" v-model="formSolicitud.tipo_envio"><option value="terrestre">Terrestre</option><option value="aereo">Aéreo</option><option value="maritimo">Marítimo</option></select></div>
            <div><label>Validez de la solicitud (días 1-7)</label><input class="input" type="number" v-model="formSolicitud.validez_dias" /><div v-if="errores.validez_dias" class="error-campo">{{ errores.validez_dias[0] }}</div></div>
            <div><label>Remitente</label><input class="input" v-model="formSolicitud.remitente" /><div v-if="errores.remitente" class="error-campo">{{ errores.remitente[0] }}</div></div>
            <div><label>Destinatario</label><input class="input" v-model="formSolicitud.destinatario" /><div v-if="errores.destinatario" class="error-campo">{{ errores.destinatario[0] }}</div></div>
            <div><label>Origen</label><input class="input" v-model="formSolicitud.origen" /><div v-if="errores.origen" class="error-campo">{{ errores.origen[0] }}</div></div>
            <div><label>Destino</label><input class="input" v-model="formSolicitud.destino" /><div v-if="errores.destino" class="error-campo">{{ errores.destino[0] }}</div></div>
            <div><label>Peso aproximado (kg)</label><input class="input" type="number" step="0.01" v-model="formSolicitud.peso_kg" /><div v-if="errores.peso_kg" class="error-campo">{{ errores.peso_kg[0] }}</div></div>
            <div><label>Volumen aproximado (m³)</label><input class="input" type="number" step="0.01" v-model="formSolicitud.volumen_m3" /><div v-if="errores.volumen_m3" class="error-campo">{{ errores.volumen_m3[0] }}</div></div>
          </div>
          <label>Contenido (solo letras)</label><input class="input" v-model="formSolicitud.contenido" /><div v-if="errores.contenido" class="error-campo">{{ errores.contenido[0] }}</div>

          <label style="margin-top:14px">Fotos del producto/paquete (hasta 5, opcional)</label>
          <input class="input" type="file" accept="image/*" multiple @change="elegirFotos" />
          <div v-if="errores.fotos" class="error-campo">{{ errores.fotos[0] }}</div>
          <div v-if="fotosPreview.length" class="fila-acciones" style="margin-top:8px; flex-wrap:wrap">
            <div v-for="(f, i) in fotosPreview" :key="i" style="position:relative">
              <img :src="f.url" style="width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid var(--color-borde)" />
              <button type="button" class="btn chico peligro" style="position:absolute; top:-8px; right:-8px; padding:2px 7px" @click="quitarFoto(i)">×</button>
            </div>
          </div>

          <div class="fila-acciones" style="justify-content:flex-end; margin-top:16px">
            <button type="button" class="btn secundario" @click="modalSolicitar=false">Cancelar</button><button class="btn">Enviar solicitud</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ver -->
    <div v-if="modalVer && detalle" class="modal-fondo" @click.self="modalVer=false">
      <div class="modal" style="max-width:680px">
        <h3 style="margin-top:0">Cotización #{{ detalle.id }} <span class="badge" :class="badge(detalle.estado)">{{ detalle.estado }}</span></h3>
        <p><strong>{{ detalle.remitente }}</strong> → <strong>{{ detalle.destinatario }}</strong></p>
        <p>{{ detalle.origen }} → {{ detalle.destino }} · {{ detalle.tipo_envio }} · {{ detalle.peso_kg }} kg · {{ detalle.volumen_m3 }} m³</p>
        <p>Contenido: {{ detalle.contenido }}</p>

        <template v-if="detalle.fotos?.length">
          <label>Fotos enviadas por el cliente</label>
          <div class="fila-acciones" style="flex-wrap:wrap; margin-bottom:10px">
            <a v-for="f in detalle.fotos" :key="f.id" :href="withBase('/' + f.ruta)" target="_blank">
              <img :src="withBase('/' + f.ruta)" style="width:90px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--color-borde)" />
            </a>
          </div>
        </template>

        <ProductosCotizacion
          :cotizacion="detalle"
          :productos="productos"
          :puede-editar="!esCliente"
          @actualizado="onProductosActualizados"
        />

        <p style="text-align:right; margin-top:10px">Subtotal: Bs {{ detalle.subtotal }} · Impuestos: Bs {{ detalle.impuestos }} · <strong>Total: Bs {{ detalle.total_estimado }}</strong></p>
        <div class="fila-acciones" style="justify-content:flex-end"><button class="btn secundario" @click="modalVer=false">Cerrar</button></div>
      </div>
    </div>
  </AppLayout>
</template>
