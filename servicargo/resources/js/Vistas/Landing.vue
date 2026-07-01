<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
  Truck, FileCheck, CreditCard, BarChart3, Search,
  PackageCheck, Warehouse, ShieldCheck, ArrowRight,
} from 'lucide-vue-next';
import { api } from '../servicios/useApi';
import { withBase } from '../servicios/useBase';

const guia = ref('');

onMounted(() => { api('/visitas/landing', { method: 'POST' }).catch(() => {}); });

// El rastreo requiere cuenta: llevamos al login (con la guía como referencia).
function rastrear() {
  router.visit(withBase('/login'));
}
</script>

<template>
  <div class="landing">
    <!-- Barra superior -->
    <header class="lp-nav">
      <strong class="lp-logo"><Truck :size="26" /> Servicargo</strong>
      <nav class="lp-nav-links">
        <a href="#servicios">Servicios</a>
        <a href="#como">Cómo funciona</a>
        <Link :href="withBase('/login')" class="btn secundario">Iniciar sesión</Link>
        <Link :href="withBase('/registro')" class="btn">Crear cuenta</Link>
      </nav>
    </header>

    <!-- Hero + buscador del negocio (requisito 9) -->
    <section class="lp-hero">
      <span class="lp-pill">Logística internacional de encomiendas</span>
      <h1>Envía, cotiza y rastrea tu carga<br>de punta a punta</h1>
      <p>
        Servicargo gestiona tu encomienda desde el origen hasta la entrega: cotización en línea,
        seguimiento en tiempo real, pagos con QR y factura automática.
      </p>

      <form class="lp-buscador" @submit.prevent="rastrear">
        <Search :size="20" class="lp-buscador-icono" />
        <input v-model="guia" placeholder="Ingresá tu N° de guía para rastrear tu encomienda…" />
        <button class="btn" type="submit">Rastrear</button>
      </form>
      <p class="lp-hint">¿Primera vez? <Link :href="withBase('/registro')">Creá tu cuenta gratis</Link> y empezá a enviar en minutos.</p>
    </section>

    <!-- Servicios -->
    <section id="servicios" class="lp-seccion">
      <h2>Todo el ciclo de tu envío, en un solo lugar</h2>
      <div class="lp-grid">
        <div class="lp-card"><FileCheck :size="30" /><h3>Cotizaciones</h3><p>Solicitá y aprobá tu envío con precios y validez claros, todo en línea.</p></div>
        <div class="lp-card"><Truck :size="30" /><h3>Seguimiento</h3><p>Guía de rastreo e historial de estados de tu carga en tiempo real.</p></div>
        <div class="lp-card"><CreditCard :size="30" /><h3>Pagos QR</h3><p>Pagá al contado o en cuotas, con factura generada automáticamente.</p></div>
        <div class="lp-card"><BarChart3 :size="30" /><h3>Reportes</h3><p>Ventas, encomiendas, inventario y conversión para decidir mejor.</p></div>
      </div>
    </section>

    <!-- Cómo funciona -->
    <section id="como" class="lp-seccion lp-como">
      <h2>Cómo funciona</h2>
      <div class="lp-pasos">
        <div class="lp-paso"><span class="lp-num">1</span><FileCheck :size="22" /><h4>Cotizás</h4><p>Pedís tu cotización y la aprobás cuando estés listo.</p></div>
        <div class="lp-paso"><span class="lp-num">2</span><PackageCheck :size="22" /><h4>Registrás</h4><p>Se crea la encomienda con su número de guía único.</p></div>
        <div class="lp-paso"><span class="lp-num">3</span><Warehouse :size="22" /><h4>Enviamos</h4><p>Recolección, almacén, aduana y transporte, con seguimiento.</p></div>
        <div class="lp-paso"><span class="lp-num">4</span><ShieldCheck :size="22" /><h4>Recibís</h4><p>Pagás, se factura y retirás tu carga. ¡Listo!</p></div>
      </div>
    </section>

    <!-- CTA final -->
    <section class="lp-cta">
      <h2>Empezá a mover tu carga hoy</h2>
      <p>Creá tu cuenta y solicitá tu primera cotización sin costo.</p>
      <Link :href="withBase('/registro')" class="btn lp-cta-btn">Crear cuenta <ArrowRight :size="18" /></Link>
    </section>

    <footer class="lp-footer">
      <span><Truck :size="16" /> Servicargo · Sistema de logística y encomiendas</span>
      <span>© {{ new Date().getFullYear() }} Servicargo</span>
    </footer>
  </div>
</template>

<style scoped>
.landing { color: var(--color-texto); background: var(--color-fondo); }
.landing h1, .landing h2, .landing h3, .landing h4 { color: var(--color-texto); }

/* Nav */
.lp-nav { display: flex; align-items: center; justify-content: space-between; padding: 18px 6vw; position: sticky; top: 0; background: color-mix(in srgb, var(--color-superficie) 88%, transparent); backdrop-filter: blur(8px); z-index: 30; border-bottom: 1px solid var(--color-borde); }
.lp-logo { font-size: 22px; display: flex; align-items: center; gap: 8px; color: var(--color-primario); }
.lp-nav-links { display: flex; align-items: center; gap: 16px; }
.lp-nav-links a { color: var(--color-texto-suave); font-weight: 600; }
.lp-nav-links a:hover { color: var(--color-primario); text-decoration: none; }

/* Hero */
.lp-hero { text-align: center; padding: 70px 6vw 60px; max-width: 900px; margin: 0 auto; }
.lp-pill { display: inline-block; padding: 6px 14px; border-radius: 999px; background: color-mix(in srgb, var(--color-primario) 12%, transparent); color: var(--color-primario); font-weight: 700; font-size: 13px; margin-bottom: 18px; }
.lp-hero h1 { font-size: clamp(30px, 5vw, 52px); line-height: 1.1; margin: 0 0 16px; }
.lp-hero > p { font-size: 18px; color: var(--color-texto-suave); max-width: 620px; margin: 0 auto 26px; }
.lp-buscador { display: flex; align-items: center; gap: 8px; background: var(--color-superficie); border: 1px solid var(--color-borde); border-radius: 999px; padding: 8px 8px 8px 16px; max-width: 620px; margin: 0 auto; box-shadow: var(--sombra); }
.lp-buscador-icono { color: var(--color-texto-suave); flex-shrink: 0; }
.lp-buscador input { flex: 1; border: none; background: transparent; outline: none; color: var(--color-texto); font-size: 15px; }
.lp-buscador .btn { border-radius: 999px; }
.lp-hint { margin-top: 14px; color: var(--color-texto-suave); font-size: 14px; }

/* Secciones */
.lp-seccion { padding: 56px 6vw; max-width: 1100px; margin: 0 auto; }
.lp-seccion h2 { text-align: center; font-size: clamp(24px, 3.5vw, 34px); margin: 0 0 34px; }
.lp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; }
.lp-card { background: var(--color-superficie); border: 1px solid var(--color-borde); border-radius: 18px; padding: 24px; box-shadow: var(--sombra); }
.lp-card svg { color: var(--color-primario); margin-bottom: 8px; }
.lp-card h3 { margin: 6px 0; font-size: 18px; }
.lp-card p { color: var(--color-texto-suave); margin: 0; font-size: 14px; }

/* Pasos */
.lp-pasos { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
.lp-paso { background: var(--color-superficie); border: 1px solid var(--color-borde); border-radius: 18px; padding: 24px; position: relative; }
.lp-paso svg { color: var(--color-acento); }
.lp-num { position: absolute; top: 16px; right: 18px; font-size: 34px; font-weight: 800; color: color-mix(in srgb, var(--color-primario) 18%, transparent); }
.lp-paso h4 { margin: 10px 0 4px; }
.lp-paso p { color: var(--color-texto-suave); margin: 0; font-size: 14px; }

/* CTA */
.lp-cta { text-align: center; padding: 64px 6vw; background: linear-gradient(135deg, var(--color-primario), var(--color-acento)); color: #fff; margin-top: 20px; }
.lp-cta h2 { color: #fff; margin: 0 0 8px; font-size: clamp(24px, 3.5vw, 32px); }
.lp-cta p { margin: 0 0 22px; opacity: .92; }
.lp-cta-btn { background: #fff; color: var(--color-primario); font-size: 16px; }
.lp-cta-btn:hover { background: #f1f5f9; }

/* Footer */
.lp-footer { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 22px 6vw; color: var(--color-texto-suave); font-size: 14px; border-top: 1px solid var(--color-borde); }
.lp-footer span { display: inline-flex; align-items: center; gap: 6px; }

@media (max-width: 640px) {
  .lp-nav-links a[href^='#'] { display: none; }
}
</style>
