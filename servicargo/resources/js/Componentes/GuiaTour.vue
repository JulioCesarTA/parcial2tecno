<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  rol: { type: String, default: 'cliente' },
});
const emit = defineEmits(['update:modelValue', 'terminado']);

const pasosPorRol = {
  cliente: [
    { selector: 'a[href$="/cotizaciones"]', titulo: 'Cotizaciones', texto: 'Pedí tu propia cotización de envío desde acá: contanos qué vas a mandar y adjuntá fotos. No hace falta ir a la tienda.' },
    { selector: 'a[href$="/encomiendas"]', titulo: 'Encomiendas', texto: 'Seguí el estado de tus envíos con el número de guía, hasta que lleguen a destino.' },
    { selector: 'a[href$="/pagos"]', titulo: 'Pagos', texto: 'Pagá tus ventas con QR cuando quieras, desde cualquier lugar.' },
    { selector: 'a[href$="/facturas"]', titulo: 'Facturas', texto: 'Cada pago genera una factura. Podés descargarla en PDF cuando quieras.' },
  ],
  asesor: [
    { selector: 'a[href$="/solicitudes"]', titulo: 'Solicitudes', texto: 'Acá llegan los pedidos de cotización que los clientes mandan desde su cuenta, con sus fotos. Revisalos y agregales los productos para armar el precio.' },
    { selector: 'a[href$="/cotizaciones"]', titulo: 'Cotizaciones', texto: 'Todas las cotizaciones: las que creás vos y las que pidieron los clientes.' },
    { selector: 'a[href$="/encomiendas"]', titulo: 'Encomiendas', texto: 'Una vez aprobada la cotización, generá acá la encomienda y movela por sus estados.' },
    { selector: 'a[href$="/ventas"]', titulo: 'Ventas', texto: 'Creá la nota de venta para cobrar el envío.' },
    { selector: 'a[href$="/pagos"]', titulo: 'Pagos', texto: 'Cobrá en efectivo o QR, y mirá el historial de pagos.' },
  ],
};
pasosPorRol.admin = [
  ...pasosPorRol.asesor,
  { selector: 'a[href$="/usuarios"]', titulo: 'Usuarios', texto: 'Alta de personal (asesores) y clientes del sistema.' },
  { selector: 'a[href$="/permisos"]', titulo: 'Matriz de Acceso', texto: 'Qué puede ver, crear, editar o eliminar cada rol.' },
];

const pasos = computed(() => (pasosPorRol[props.rol] || pasosPorRol.cliente).filter((p) => document.querySelector(p.selector)));
const paso = ref(0);
const rect = ref(null);

function medir() {
  const el = document.querySelector(pasos.value[paso.value]?.selector || '');
  rect.value = el ? el.getBoundingClientRect() : null;
}

const posicionTarjeta = computed(() => {
  if (!rect.value) return {};
  const alturaEstimada = 190;
  const arriba = (window.innerHeight - rect.value.bottom) < (alturaEstimada + 20);
  return {
    left: Math.min(Math.max(12, rect.value.left), window.innerWidth - 300) + 'px',
    top: (arriba ? Math.max(12, rect.value.top - alturaEstimada - 10) : rect.value.bottom + 14) + 'px',
  };
});

function siguiente() {
  if (paso.value < pasos.value.length - 1) { paso.value++; nextTick(medir); }
  else cerrar();
}
function anterior() {
  if (paso.value > 0) { paso.value--; nextTick(medir); }
}
function cerrar() {
  emit('update:modelValue', false);
  emit('terminado');
}

watch(() => props.modelValue, async (visible) => {
  if (visible) {
    paso.value = 0;
    await nextTick();
    medir();
    window.addEventListener('resize', medir);
  } else {
    window.removeEventListener('resize', medir);
  }
});
onBeforeUnmount(() => window.removeEventListener('resize', medir));
</script>

<template>
  <div v-if="modelValue && rect" class="tour-fondo">
    <div
      class="tour-recorte"
      :style="{ top: (rect.top - 6) + 'px', left: (rect.left - 6) + 'px', width: (rect.width + 12) + 'px', height: (rect.height + 12) + 'px' }"
    ></div>
    <div class="tour-tarjeta" :style="posicionTarjeta">
      <div class="tour-paso">Paso {{ paso + 1 }} de {{ pasos.length }}</div>
      <h4>{{ pasos[paso]?.titulo }}</h4>
      <p>{{ pasos[paso]?.texto }}</p>
      <div class="tour-acciones">
        <button type="button" class="btn chico secundario" @click="cerrar">Saltar</button>
        <button v-if="paso > 0" type="button" class="btn chico secundario" @click="anterior">Atrás</button>
        <button type="button" class="btn chico" @click="siguiente">{{ paso < pasos.length - 1 ? 'Siguiente' : 'Listo' }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tour-fondo { position: fixed; inset: 0; z-index: 1200; }
.tour-recorte {
  position: fixed; border-radius: 10px; box-shadow: 0 0 0 4000px rgba(15, 23, 42, .6);
  border: 2px solid #fff; pointer-events: none; transition: all .2s ease;
}
.tour-tarjeta {
  position: fixed; background: var(--color-superficie); color: var(--color-texto);
  border-radius: 12px; padding: 16px; width: 280px; box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
  z-index: 1201; transition: all .2s ease;
}
.tour-tarjeta h4 { margin: 4px 0 8px; }
.tour-tarjeta p { margin: 0 0 12px; font-size: 13px; color: var(--color-texto-suave); }
.tour-paso { font-size: 11px; text-transform: uppercase; color: var(--color-primario); font-weight: 700; }
.tour-acciones { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
</style>
