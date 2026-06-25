<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, Shield, ScrollText,
  Search, LogOut, Sun, Moon, Baby, Sparkles, Type, Contrast,
} from 'lucide-vue-next';
import { useAuth } from '../servicios/useAuth';
import { api, getToken } from '../servicios/useApi';
import ToastHost from '../Componentes/ToastHost.vue';
import ConfirmDialog from '../Componentes/ConfirmDialog.vue';

const { sesion, logout, refrescar, autenticado } = useAuth();

const iconos = {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, Shield, ScrollText,
};

const rutaActual = computed(() => window.location.pathname.replace(/^\//, '') || 'inicio');

// Breadcrumb (elemento de navegación)
const migaActual = computed(() => {
  if (rutaActual.value === 'inicio') return 'Inicio';
  const item = sesion.menu.find((m) => m.ruta === rutaActual.value);
  return item ? item.nombre : rutaActual.value.charAt(0).toUpperCase() + rutaActual.value.slice(1);
});

/* ---------- Temas + accesibilidad ---------- */
function autoHora() {
  const h = new Date().getHours();
  return (h >= 6 && h < 18) ? 'dia' : 'noche';
}
// Modo 'auto' (por defecto): Día/Noche según la hora del cliente en cada carga
const modoTema = ref(localStorage.getItem('sc_tema_modo') || 'auto');
const tema = ref(modoTema.value === 'auto' ? autoHora() : (localStorage.getItem('sc_tema') || 'dia'));
const fuente = ref(localStorage.getItem('sc_fuente') || 'md');
const contraste = ref(localStorage.getItem('sc_contraste') || 'normal');

function aplicar() {
  const html = document.documentElement;
  html.setAttribute('data-theme', tema.value);
  html.setAttribute('data-font', fuente.value);
  html.setAttribute('data-contrast', contraste.value);
  localStorage.setItem('sc_tema', tema.value);
  localStorage.setItem('sc_tema_modo', modoTema.value);
  localStorage.setItem('sc_fuente', fuente.value);
  localStorage.setItem('sc_contraste', contraste.value);
}
function setTema(t) { modoTema.value = 'manual'; tema.value = t; aplicar(); }
function temaAuto() { modoTema.value = 'auto'; tema.value = autoHora(); aplicar(); }
function cambiarFuente(f) { fuente.value = f; aplicar(); }
function toggleContraste() { contraste.value = contraste.value === 'alto' ? 'normal' : 'alto'; aplicar(); }

/* ---------- Búsqueda ---------- */
const q = ref('');
const resultados = ref(null);
let tmr = null;
function buscar() {
  clearTimeout(tmr);
  tmr = setTimeout(async () => {
    if (q.value.trim().length < 1) { resultados.value = null; return; }
    try { resultados.value = await api('/buscar', { params: { q: q.value } }); }
    catch (e) { resultados.value = null; }
  }, 300);
}

/* ---------- Visitas ---------- */
const visitas = ref(0);

onMounted(async () => {
  aplicar();
  if (!getToken()) { router.visit('/login'); return; }
  await refrescar();
  if (!autenticado.value) { router.visit('/login'); return; }
  try {
    const d = await api('/visitas/' + rutaActual.value, { method: 'POST' });
    visitas.value = d.contador;
  } catch (e) { /* ignore */ }
});
</script>

<template>
  <div class="app-shell">
    <!-- Sidebar con menú dinámico (desde BD según rol) -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <Truck :size="24" /> Servicargo
      </div>
      <nav class="nav">
        <Link href="/inicio" :class="{ activo: rutaActual === 'inicio' }">
          <BarChart3 :size="18" /> Inicio
        </Link>
        <Link v-for="item in sesion.menu" :key="item.clave" :href="'/' + item.ruta"
              :class="{ activo: rutaActual === item.ruta }">
          <component :is="iconos[item.icono] || Package" :size="18" />
          {{ item.nombre }}
        </Link>
      </nav>
    </aside>

    <div class="main">
      <!-- Topbar: búsqueda + temas + accesibilidad + usuario -->
      <header class="topbar">
        <div class="buscador">
          <input class="input" v-model="q" @input="buscar" placeholder="Buscar guía, cotización, destino…" />
          <div v-if="resultados" class="resultados-busqueda">
            <div v-if="resultados.encomiendas.length" style="font-weight:700">Encomiendas</div>
            <div v-for="e in resultados.encomiendas" :key="'e'+e.id">Guía {{ e.guia_rastreo }} → {{ e.destino }} ({{ e.estado }})</div>
            <div v-if="resultados.cotizaciones.length" style="font-weight:700">Cotizaciones</div>
            <div v-for="c in resultados.cotizaciones" :key="'c'+c.id">#{{ c.id }} → {{ c.destino }} ({{ c.estado }})</div>
            <div v-for="p in (resultados.productos||[])" :key="'p'+p.id">{{ p.codigo }} — {{ p.nombre }}</div>
            <div v-if="!resultados.encomiendas.length && !resultados.cotizaciones.length && !(resultados.productos||[]).length"
                 style="color:var(--color-texto-suave)">Sin resultados</div>
          </div>
        </div>

        <div class="selector-tema">
          <button class="icon-btn" title="Día" @click="setTema('dia')"><Sun :size="16" /></button>
          <button class="icon-btn" title="Noche" @click="setTema('noche')"><Moon :size="16" /></button>
          <button class="icon-btn" title="Niños" @click="setTema('ninos')"><Baby :size="16" /></button>
          <button class="icon-btn" title="Jóvenes" @click="setTema('jovenes')"><Sparkles :size="16" /></button>
          <button class="icon-btn" title="Auto día/noche" @click="temaAuto"><Type :size="16" /></button>
        </div>

        <div class="selector-tema">
          <button class="icon-btn" title="Letra pequeña" @click="cambiarFuente('sm')">A-</button>
          <button class="icon-btn" title="Letra normal" @click="cambiarFuente('md')">A</button>
          <button class="icon-btn" title="Letra grande" @click="cambiarFuente('lg')">A+</button>
          <button class="icon-btn" title="Alto contraste" @click="toggleContraste"><Contrast :size="16" /></button>
        </div>

        <div style="display:flex; align-items:center; gap:10px">
          <span v-if="sesion.usuario">
            <strong>{{ sesion.usuario.nombre }}</strong>
            <span class="badge info" style="margin-left:6px">{{ sesion.usuario.rol }}</span>
          </span>
          <button class="icon-btn" title="Cerrar sesión" @click="logout"><LogOut :size="16" /></button>
        </div>
      </header>

      <nav class="breadcrumb" aria-label="Ruta de navegación">
        <Link href="/inicio">Inicio</Link>
        <template v-if="rutaActual !== 'inicio'">
          <span class="sep">/</span><span class="actual">{{ migaActual }}</span>
        </template>
      </nav>

      <main class="content">
        <slot />
      </main>

      <footer class="footer">
        <span>Servicargo · Sistema de logística y encomiendas</span>
        <span>Visitas a esta página: <strong>{{ visitas }}</strong></span>
      </footer>
    </div>

    <ToastHost />
    <ConfirmDialog />
  </div>
</template>
