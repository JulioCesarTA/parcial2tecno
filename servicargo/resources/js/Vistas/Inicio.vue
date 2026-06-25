<script setup>
import { Link } from '@inertiajs/vue3';
import * as L from 'lucide-vue-next';
import AppLayout from '../Plantillas/AppLayout.vue';
import { useAuth } from '../servicios/useAuth';

const { sesion } = useAuth();
const iconos = L;
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Hola, {{ sesion.usuario?.nombre }}</h1>
    <p class="subtitulo">Panel de Servicargo — rol {{ sesion.usuario?.rol }}</p>

    <div class="card">
      <h3>Flujo del negocio</h3>
      <p style="color:var(--color-texto-suave)">
        Cotización → (cliente aprueba) → Encomienda → Nota de Venta → Pago(s) → Factura(s)
      </p>
    </div>

    <h3>Accesos</h3>
    <div class="grid grid-3">
      <Link v-for="item in sesion.menu" :key="item.clave" :href="'/' + item.ruta" class="metric" style="text-decoration:none">
        <component :is="iconos[item.icono] || iconos.Package" :size="22" color="var(--color-primario)" />
        <div class="etiqueta">{{ item.nombre }}</div>
      </Link>
    </div>
  </AppLayout>
</template>
