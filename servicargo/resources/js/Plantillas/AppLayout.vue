<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, PieChart, Shield, ScrollText, Inbox,
  Search, LogOut, Settings, LayoutDashboard, User, HelpCircle,
  Menu, X, ChevronLeft, ChevronRight,
} from 'lucide-vue-next';
import { useAuth } from '../servicios/useAuth';
import { api, getToken } from '../servicios/useApi';
import { withBase, stripBase } from '../servicios/useBase';
import { coincide } from '../servicios/texto';
import ToastHost from '../Componentes/ToastHost.vue';
import ConfirmDialog from '../Componentes/ConfirmDialog.vue';
import GuiaTour from '../Componentes/GuiaTour.vue';

const { sesion, logout, refrescar, autenticado } = useAuth();

const iconos = {
  Users, Package, Warehouse, Boxes, FileText, Truck, ShoppingCart,
  CreditCard, Receipt, BarChart3, PieChart, Shield, ScrollText, Inbox,
};

const rutaActual = computed(() => stripBase(window.location.pathname).replace(/^\//, '') || 'inicio');

// Iniciales para el avatar
const iniciales = computed(() => {
  const n = sesion.usuario?.nombre || '';
  const a = sesion.usuario?.apellido || '';
  return ((n[0] || '') + (a[0] || n[1] || '')).toUpperCase() || 'U';
});

// Foto de perfil (ruta estática dentro de public/); si no hay, se usan las iniciales.
const fotoUrl = computed(() => {
  const f = sesion.usuario?.foto;
  return f ? withBase('/' + f) : null;
});

// Breadcrumb (elemento de navegación)
const migaActual = computed(() => {
  if (rutaActual.value === 'inicio') return 'Inicio';
  if (rutaActual.value === 'perfil') return 'Mi Perfil';
  const item = sesion.menu.find((m) => m.ruta === rutaActual.value);
  return item ? item.nombre : rutaActual.value.charAt(0).toUpperCase() + rutaActual.value.slice(1);
});

/* ============ Tema: persona (paleta) + modo (claro/oscuro) ============ */
const PERSONAS = ['navy', 'nino', 'joven', 'adulto'];
const PERSONA_INFO = {
  navy:   { nombre: 'Predeterminado', gradiente: 'linear-gradient(135deg,#35577d,#141e30)' },
  nino:   { nombre: 'Niño',           gradiente: 'linear-gradient(135deg,#0ea5a5,#fb7185)' },
  joven:  { nombre: 'Joven',          gradiente: 'linear-gradient(135deg,#7c3aed,#ec4899)' },
  adulto: { nombre: 'Adulto',         gradiente: 'linear-gradient(135deg,#0f766e,#1f2937)' },
};

function autoHora() {
  const h = new Date().getHours();
  return (h >= 6 && h < 18) ? 'light' : 'dark';
}

// Convierte un valor de tema guardado (compuesto "persona:modo" o formato antiguo)
function parsearTema(valor) {
  if (valor && valor.includes(':')) {
    const [p, m] = valor.split(':');
    return {
      persona: PERSONAS.includes(p) ? p : 'navy',
      modoPref: ['auto', 'light', 'dark'].includes(m) ? m : 'auto',
    };
  }
  // Compatibilidad con el formato antiguo
  const legado = {
    auto: { persona: 'navy', modoPref: 'auto' },
    dia: { persona: 'navy', modoPref: 'light' },
    noche: { persona: 'navy', modoPref: 'dark' },
    ninos: { persona: 'nino', modoPref: 'light' },
    jovenes: { persona: 'joven', modoPref: 'light' },
  };
  return legado[valor] || { persona: 'navy', modoPref: 'auto' };
}

const persona = ref('navy');
const modoPref = ref(localStorage.getItem('sc_modo_pref') || 'auto'); // auto | light | dark
const fuente = ref(localStorage.getItem('sc_fuente') || 'md');
const contraste = ref(localStorage.getItem('sc_contraste') || 'normal');
const mostrarConfig = ref(false);

// Rehidrata persona desde localStorage antes del primer render
persona.value = localStorage.getItem('sc_persona') || 'navy';

// Modo efectivo (resuelve "auto" según la hora)
const modoEfectivo = computed(() => (modoPref.value === 'auto' ? autoHora() : modoPref.value));

function aplicar() {
  const html = document.documentElement;
  html.setAttribute('data-persona', persona.value);
  html.setAttribute('data-mode', modoEfectivo.value);
  html.setAttribute('data-font', fuente.value);
  html.setAttribute('data-contrast', contraste.value);
  localStorage.setItem('sc_persona', persona.value);
  localStorage.setItem('sc_modo_pref', modoPref.value);
  localStorage.setItem('sc_fuente', fuente.value);
  localStorage.setItem('sc_contraste', contraste.value);
}
function setPersona(p) { persona.value = p; aplicar(); persistir(); }
function setModo(m) { modoPref.value = m; aplicar(); persistir(); }
function cambiarFuente(f) { fuente.value = f; aplicar(); persistir(); }
function setContraste(c) { contraste.value = c; aplicar(); persistir(); }

// Persistencia por usuario (en BD); el modo se guarda tal cual (incluye "auto")
async function persistir() {
  if (!getToken()) return; // invitado: solo localStorage
  try {
    await api('/preferencias', {
      method: 'PUT',
      body: { tema: `${persona.value}:${modoPref.value}`, fuente: fuente.value, contraste: contraste.value },
    });
  } catch (e) { /* si falla, queda igual en localStorage */ }
}

// Carga las preferencias del usuario desde BD y las aplica
async function cargarPreferencias() {
  try {
    const p = await api('/preferencias');
    const { persona: per, modoPref: mp } = parsearTema(p.tema);
    persona.value = per;
    modoPref.value = mp;
    fuente.value = p.fuente || 'md';
    contraste.value = p.contraste || 'normal';
    aplicar();
  } catch (e) { /* mantiene lo de localStorage */ }
}

/* ============ Barra lateral: colapsar (escritorio) / drawer (móvil) ============ */
const colapsado = ref(localStorage.getItem('sc_sidebar_colapsado') === '1');
const drawerAbierto = ref(false);

function toggleColapsar() {
  colapsado.value = !colapsado.value;
  localStorage.setItem('sc_sidebar_colapsado', colapsado.value ? '1' : '0');
}
function abrirDrawer() { drawerAbierto.value = true; }
function cerrarDrawer() { drawerAbierto.value = false; }

function alRedimensionar() {
  if (window.innerWidth > 1024) drawerAbierto.value = false;
}

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

/* Búsqueda "universal": sugiere vistas a las que navegar */
const destinosBase = computed(() => [
  { clave: 'inicio', nombre: 'Inicio', ruta: 'inicio' },
  { clave: 'perfil', nombre: 'Mi Perfil', ruta: 'perfil' },
  ...sesion.menu,
]);
const destinosSugeridos = computed(() => {
  if (q.value.trim().length < 1) return [];
  return destinosBase.value.filter((d) => coincide(d.nombre, q.value)).slice(0, 5);
});
const mostrarPanelBusqueda = computed(() => q.value.trim().length >= 1);
function limpiarBusqueda() { q.value = ''; resultados.value = null; }

/* ---------- Visitas ---------- */
const visitas = ref(0);

/* ---------- Guía de bienvenida (tour interactivo) ---------- */
const mostrarTour = ref(false);
function iniciarTour() { mostrarTour.value = true; }

onMounted(async () => {
  aplicar();
  window.addEventListener('resize', alRedimensionar);
  if (!getToken()) { router.visit(withBase('/login')); return; }
  await refrescar();
  if (!autenticado.value) { router.visit(withBase('/login')); return; }
  await cargarPreferencias();
  try {
    const d = await api('/visitas/' + rutaActual.value, { method: 'POST' });
    visitas.value = d.contador;
  } catch (e) { /* ignore */ }

  // Primer login de este usuario en este navegador: lanza el tour una sola vez.
  const claveTourVisto = `sc_tour_visto_${sesion.usuario?.id}`;
  if (sesion.usuario && !localStorage.getItem(claveTourVisto)) {
    localStorage.setItem(claveTourVisto, '1');
    setTimeout(() => { mostrarTour.value = true; }, 500);
  }
});

onUnmounted(() => window.removeEventListener('resize', alRedimensionar));
</script>

<template>
  <div class="shell" :class="{ 'sidebar-colapsado': colapsado }">
    <!-- Fondo oscuro para el drawer en móvil/tablet -->
    <div v-if="drawerAbierto" class="sidebar-overlay" @click="cerrarDrawer"></div>

    <!-- Barra lateral (navegación) -->
    <aside class="sidebar" :class="{ colapsado, abierto: drawerAbierto }">
      <div class="sidebar-top">
        <div class="sidebar-marca">
          <div class="sidebar-logo"><Truck :size="21" /></div>
          <span class="sidebar-marca-texto">Servicargo</span>
        </div>
        <button class="sidebar-toggle" title="Colapsar menú" @click="toggleColapsar">
          <ChevronLeft v-if="!colapsado" :size="18" />
          <ChevronRight v-else :size="18" />
        </button>
      </div>

      <Link :href="withBase('/perfil')" class="sidebar-perfil" @click="cerrarDrawer" title="Mi Perfil">
        <div class="avatar">
          <img v-if="fotoUrl" :src="fotoUrl" alt="Foto de perfil" class="avatar-img" />
          <template v-else>{{ iniciales }}</template>
        </div>
        <div class="sidebar-perfil-info">
          <div class="sidebar-perfil-nombre">{{ sesion.usuario?.nombre }} {{ sesion.usuario?.apellido }}</div>
          <span class="sidebar-perfil-rol">{{ sesion.usuario?.rol }}</span>
        </div>
      </Link>

      <nav class="sidebar-nav">
        <div class="nav-label">Navegación</div>
        <Link :href="withBase('/inicio')" class="nav-item" :class="{ activo: rutaActual === 'inicio' }"
              @click="cerrarDrawer" title="Inicio">
          <LayoutDashboard :size="19" /> <span class="nav-item-texto">Inicio</span>
        </Link>
        <Link :href="withBase('/perfil')" class="nav-item" :class="{ activo: rutaActual === 'perfil' }"
              @click="cerrarDrawer" title="Mi Perfil">
          <User :size="19" /> <span class="nav-item-texto">Mi Perfil</span>
        </Link>
        <Link v-for="item in sesion.menu" :key="item.clave" :href="withBase('/' + item.ruta)"
              class="nav-item" :class="{ activo: rutaActual === item.ruta }"
              @click="cerrarDrawer" :title="item.nombre">
          <component :is="iconos[item.icono] || Package" :size="19" />
          <span class="nav-item-texto">{{ item.nombre }}</span>
        </Link>
      </nav>

      <div class="sidebar-bottom">
        <button class="nav-item" title="Configuración" @click="mostrarConfig = true; cerrarDrawer()">
          <Settings :size="19" /> <span class="nav-item-texto">Configuración</span>
        </button>
        <button class="nav-item" title="Cerrar sesión" @click="logout">
          <LogOut :size="19" /> <span class="nav-item-texto">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Contenido principal -->
    <div class="board">
      <header class="topbar">
        <button class="hamburguesa" title="Menú" @click="abrirDrawer"><Menu :size="20" /></button>

        <nav class="breadcrumb" aria-label="Ruta de navegación">
          <Link :href="withBase('/inicio')">Inicio</Link>
          <template v-if="rutaActual !== 'inicio'">
            <span class="sep">/</span><span class="actual">{{ migaActual }}</span>
          </template>
        </nav>

        <div class="buscador">
          <input class="input" v-model="q" @input="buscar" placeholder="Buscar guía, cotización, destino…" />
          <div v-if="mostrarPanelBusqueda" class="resultados-busqueda">
            <template v-if="destinosSugeridos.length">
              <div style="font-weight:700; color:var(--color-texto-suave)">Ir a</div>
              <Link
                v-for="d in destinosSugeridos" :key="'dest'+d.clave"
                :href="withBase('/' + d.ruta)" @click="limpiarBusqueda"
              >→ {{ d.nombre }}</Link>
            </template>
            <template v-if="resultados">
              <div v-if="resultados.encomiendas.length" style="font-weight:700">Encomiendas</div>
              <div v-for="e in resultados.encomiendas" :key="'e'+e.id">Guía {{ e.guia_rastreo }} → {{ e.destino }} ({{ e.estado }})</div>
              <div v-if="resultados.cotizaciones.length" style="font-weight:700">Cotizaciones</div>
              <div v-for="c in resultados.cotizaciones" :key="'c'+c.id">#{{ c.id }} → {{ c.destino }} ({{ c.estado }})</div>
              <div v-for="p in (resultados.productos||[])" :key="'p'+p.id">{{ p.codigo }} — {{ p.nombre }}</div>
            </template>
            <div v-if="!destinosSugeridos.length && resultados && !resultados.encomiendas.length && !resultados.cotizaciones.length && !(resultados.productos||[]).length"
                 style="color:var(--color-texto-suave)">Sin resultados</div>
          </div>
        </div>

        <div class="topbar-acciones">
          <button class="icon-btn" title="Cómo funciona Servicargo" @click="iniciarTour"><HelpCircle :size="17" /></button>
          <button class="icon-btn" title="Configuración" @click="mostrarConfig = true"><Settings :size="17" /></button>
          <div class="topbar-user" v-if="sesion.usuario">
            <span class="topbar-user-nombre">{{ sesion.usuario.nombre }}</span>
            <div class="avatar-mini">
              <img v-if="fotoUrl" :src="fotoUrl" alt="Foto de perfil" />
              <template v-else>{{ iniciales }}</template>
            </div>
          </div>
        </div>
      </header>

      <main class="content">
        <slot />
      </main>

      <footer class="footer">
        <span>Servicargo · Sistema de logística y encomiendas</span>
        <span>Visitas a esta página: <strong>{{ visitas }}</strong></span>
      </footer>
    </div>

    <!-- Panel de configuración (tema + accesibilidad) -->
    <div v-if="mostrarConfig" class="config-fondo" @click.self="mostrarConfig = false">
      <div class="config-panel">
        <div style="display:flex; align-items:center; justify-content:space-between">
          <h3>Apariencia</h3>
          <button class="icon-btn" title="Cerrar" @click="mostrarConfig = false"><X :size="18" /></button>
        </div>
        <p class="subtitulo" style="margin:0 0 6px">Personalizá cómo se ve Servicargo para vos.</p>

        <div class="config-grupo">
          <h4>Paleta</h4>
          <div class="config-opciones">
            <button v-for="p in PERSONAS" :key="p" class="chip chip-persona" :class="{ activo: persona === p }" @click="setPersona(p)">
              <span class="muestra" :style="{ background: PERSONA_INFO[p].gradiente }"></span>
              {{ PERSONA_INFO[p].nombre }}
            </button>
          </div>
        </div>

        <div class="config-grupo">
          <h4>Modo</h4>
          <div class="config-opciones">
            <button class="chip" :class="{ activo: modoPref === 'light' }" @click="setModo('light')">Claro</button>
            <button class="chip" :class="{ activo: modoPref === 'dark' }" @click="setModo('dark')">Oscuro</button>
            <button class="chip" :class="{ activo: modoPref === 'auto' }" @click="setModo('auto')">Automático</button>
          </div>
        </div>

        <div class="config-grupo">
          <h4>Tamaño de letra</h4>
          <div class="config-opciones">
            <button class="chip" :class="{ activo: fuente === 'sm' }" @click="cambiarFuente('sm')">Pequeña</button>
            <button class="chip" :class="{ activo: fuente === 'md' }" @click="cambiarFuente('md')">Normal</button>
            <button class="chip" :class="{ activo: fuente === 'lg' }" @click="cambiarFuente('lg')">Grande</button>
          </div>
        </div>

        <div class="config-grupo">
          <h4>Contraste</h4>
          <div class="config-opciones">
            <button class="chip" :class="{ activo: contraste === 'normal' }" @click="setContraste('normal')">Normal</button>
            <button class="chip" :class="{ activo: contraste === 'alto' }" @click="setContraste('alto')">Alto</button>
          </div>
        </div>
      </div>
    </div>

    <ToastHost />
    <ConfirmDialog />
    <GuiaTour v-model="mostrarTour" :rol="sesion.usuario?.rol" />
  </div>
</template>

<style scoped>
.resultados-busqueda a {
  display: block; padding: 8px 12px; border-radius: 8px;
  text-decoration: none; font-weight: 600; color: var(--color-primario);
}
.resultados-busqueda a:hover { background: var(--color-superficie-2); }

/* Avatar del perfil en la barra lateral */
.avatar {
  width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0; overflow: hidden;
  background: var(--gradiente-marca);
  color: #fff; display: grid; place-items: center; font-weight: 800; font-size: calc(15px * var(--escala));
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; display: block; }
</style>
