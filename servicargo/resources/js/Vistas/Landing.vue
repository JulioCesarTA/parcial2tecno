<script setup>
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Truck, FileCheck, CreditCard, BarChart3 } from 'lucide-vue-next';
import { api } from '../servicios/useApi';

onMounted(() => { api('/visitas/landing', { method: 'POST' }).catch(() => {}); });
</script>

<template>
  <div style="min-height:100vh; background: linear-gradient(135deg,#1f6feb,#0d9488); color:#fff">
    <header style="display:flex; justify-content:space-between; align-items:center; padding:20px 32px">
      <strong style="font-size:22px; display:flex; align-items:center; gap:8px"><Truck :size="26"/> Servicargo</strong>
      <div style="display:flex; gap:10px">
        <Link href="/login" class="btn secundario" style="color:#fff; border-color:#fff">Iniciar sesión</Link>
        <Link href="/registro" class="btn" style="background:#fff; color:#1f6feb">Crear cuenta</Link>
      </div>
    </header>

    <section style="max-width:900px; margin:60px auto; text-align:center; padding:0 20px">
      <h1 style="font-size:46px; margin:0 0 12px">Logística de encomiendas, de punta a punta</h1>
      <p style="font-size:19px; opacity:.92">
        Cotiza, aprueba, registra tu encomienda, paga y factura — todo en línea.
      </p>
      <Link href="/registro" class="btn" style="background:#fff; color:#1f6feb; margin-top:18px; font-size:16px">
        Empezar ahora
      </Link>
    </section>

    <section style="max-width:1000px; margin:0 auto 60px; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; padding:0 20px">
      <div class="card" style="background:rgba(255,255,255,.12); border:none; color:#fff">
        <FileCheck :size="30"/><h3>Cotizaciones</h3><p>Solicita y aprueba envíos con validez configurable.</p>
      </div>
      <div class="card" style="background:rgba(255,255,255,.12); border:none; color:#fff">
        <Truck :size="30"/><h3>Seguimiento</h3><p>Guía de rastreo e historial de estados en tiempo real.</p>
      </div>
      <div class="card" style="background:rgba(255,255,255,.12); border:none; color:#fff">
        <CreditCard :size="30"/><h3>Pagos QR</h3><p>Contado o crédito en cuotas, con factura automática.</p>
      </div>
      <div class="card" style="background:rgba(255,255,255,.12); border:none; color:#fff">
        <BarChart3 :size="30"/><h3>Reportes</h3><p>Ventas, encomiendas, inventario y conversión.</p>
      </div>
    </section>
  </div>
</template>
