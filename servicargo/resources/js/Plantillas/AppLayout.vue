<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, Shield, ScrollText,
  Search, LogOut, Sun, Moon, Baby, Sparkles, Type, Contrast, Settings, LayoutDashboard,
} from 'lucide-vue-next';
import { useAuth } from '../servicios/useAuth';
import { api, getToken } from '../servicios/useApi';
import { withBase, stripBase } from '../servicios/useBase';
import ToastHost from '../Componentes/ToastHost.vue';
import ConfirmDialog from '../Componentes/ConfirmDialog.vue';

const { sesion, logout, refrescar, autenticado } = useAuth();

const iconos = {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, Shield, ScrollText,
};

const rutaActual = computed(() => stripBase(window.location.pathname).replace(/^\//, '') || 'inicio');

// Iniciales para el avatar
const iniciales = computed(() => {
  const n = sesion.usuario?.nombre || '';
  const a = sesion.usuario?.apellido || '';
  return ((n[0] || '') + (a[0] || n[1] || '')).toUpperCase() || 'U';
});

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
const mostrarConfig = ref(false);

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
function setTema(t) { modoTema.value = 'manual'; tema.value = t; aplicar(); persistir(); }
function temaAuto() { modoTema.value = 'auto'; tema.value = autoHora(); aplicar(); persistir(); }
function cambiarFuente(f) { fuente.value = f; aplicar(); persistir(); }
function setContraste(c) { contraste.value = c; aplicar(); persistir(); }

// Persistencia por usuario (requisito 5): guarda en BD; 'auto' se guarda tal cual
async function persistir() {
  if (!getToken()) return; // invitado: solo localStorage
  const temaVal = modoTema.value === 'auto' ? 'auto' : tema.value;
  try {
    await api('/preferencias', { method: 'PUT', body: { tema: temaVal, fuente: fuente.value, contraste: contraste.value } });
  } catch (e) { /* si falla, queda igual en localStorage */ }
}

// Carga las preferencias del usuario desde BD y las aplica (override de localStorage)
async function cargarPreferencias() {
  try {
    const p = await api('/preferencias');
    if (p.tema === 'auto') { modoTema.value = 'auto'; tema.value = autoHora(); }
    else { modoTema.value = 'manual'; tema.value = p.tema; }
    fuente.value = p.fuente || 'md';
    contraste.value = p.contraste || 'normal';
    aplicar();
  } catch (e) { /* mantiene lo de localStorage */ }
}

const temaActivo = computed(() => (modoTema.value === 'auto' ? 'auto' : tema.value));

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
  if (!getToken()) { router.visit(withBase('/login')); return; }
  await refrescar();
  if (!autenticado.value) { router.visit(withBase('/login')); return; }
  await cargarPreferencias();
  try {
    const d = await api('/visitas/' + rutaActual.value, { method: 'POST' });
    visitas.value = d.contador;
  } catch (e) { /* ignore */ }
});
</script>

<template>
  <div class="shell">
    <!-- Riel oscuro de íconos -->
    <aside class="rail">
      <div class="rail-grupo">
        <div class="rail-logo"><Truck :size="24" /></div>
        <Link :href="withBase('/inicio')" class="rail-btn" :class="{ activo: rutaActual === 'inicio' }" title="Inicio">
          <LayoutDashboard :size="20" />
        </Link>
      </div>
      <div class="rail-grupo">
        <button class="rail-btn" :class="{ activo: mostrarConfig }" title="Configuración" @click="mostrarConfig = !mostrarConfig">
          <Settings :size="20" />
        </button>
        <button class="rail-btn" title="Cerrar sesión" @click="logout"><LogOut :size="20" /></button>
      </div>
    </aside>

    <!-- Popover de configuración (temas + accesibilidad, con nombres) -->
    <div v-if="mostrarConfig" class="config-pop">
      <div class="grupo">
        <h4>Tema</h4>
        <div class="opciones">
          <button class="chip" :class="{ activo: temaActivo === 'dia' }" @click="setTema('dia')">Día</button>
          <button class="chip" :class="{ activo: temaActivo === 'noche' }" @click="setTema('noche')">Noche</button>
          <button class="chip" :class="{ activo: temaActivo === 'ninos' }" @click="setTema('ninos')">Niños</button>
          <button class="chip" :class="{ activo: temaActivo === 'jovenes' }" @click="setTema('jovenes')">Jóvenes</button>
          <button class="chip" :class="{ activo: temaActivo === 'auto' }" @click="temaAuto">Automático</button>
        </div>
      </div>
      <div class="grupo">
        <h4>Tamaño de letra</h4>
        <div class="opciones">
          <button class="chip" :class="{ activo: fuente === 'sm' }" @click="cambiarFuente('sm')">Pequeña</button>
          <button class="chip" :class="{ activo: fuente === 'md' }" @click="cambiarFuente('md')">Normal</button>
          <button class="chip" :class="{ activo: fuente === 'lg' }" @click="cambiarFuente('lg')">Grande</button>
        </div>
      </div>
      <div class="grupo" style="margin-bottom:0">
        <h4>Contraste</h4>
        <div class="opciones">
          <button class="chip" :class="{ activo: contraste === 'normal' }" @click="setContraste('normal')">Normal</button>
          <button class="chip" :class="{ activo: contraste === 'alto' }" @click="setContraste('alto')">Alto</button>
        </div>
      </div>
    </div>

    <!-- Tarjeta principal: navegación + contenido -->
    <div class="board">
      <!-- Panel de navegación con secciones nombradas -->
      <aside class="sidebar">
        <div class="perfil">
          <div class="avatar">{{ iniciales }}</div>
          <div style="min-width:0">
            <div class="perfil-nombre">{{ sesion.usuario?.nombre }}</div>
            <div class="perfil-correo">{{ sesion.usuario?.correo }}</div>
          </div>
        </div>
        <span class="badge info" style="align-self:flex-start; margin:0 6px 4px">{{ sesion.usuario?.rol }}</span>

        <div class="nav-label">Navegación</div>
        <nav class="nav">
          <Link :href="withBase('/inicio')" :class="{ activo: rutaActual === 'inicio' }">
            <LayoutDashboard :size="18" /> Inicio
          </Link>
          <Link v-for="item in sesion.menu" :key="item.clave" :href="withBase('/' + item.ruta)"
                :class="{ activo: rutaActual === item.ruta }">
            <component :is="iconos[item.icono] || Package" :size="18" />
            {{ item.nombre }}
          </Link>
        </nav>
      </aside>

      <div class="main">
        <!-- Topbar: búsqueda + usuario -->
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

          <div style="display:flex; align-items:center; gap:10px; margin-left:auto">
            <button class="icon-btn" title="Configuración" @click="mostrarConfig = !mostrarConfig"><Settings :size="16" /></button>
            <span v-if="sesion.usuario">
              <strong>{{ sesion.usuario.nombre }}</strong>
            </span>
            <button class="icon-btn" title="Cerrar sesión" @click="logout"><LogOut :size="16" /></button>
          </div>
        </header>

        <nav class="breadcrumb" aria-label="Ruta de navegación">
          <Link :href="withBase('/inicio')">Inicio</Link>
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
    </div>

    <ToastHost />
    <ConfirmDialog />
  </div>
</template>
