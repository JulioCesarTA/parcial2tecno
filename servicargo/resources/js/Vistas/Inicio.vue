<script setup>
import { computed } from 'vue';
import AppLayout from '../Plantillas/AppLayout.vue';
import AdminDashboard from '../Componentes/Dashboards/AdminDashboard.vue';
import AsesorDashboard from '../Componentes/Dashboards/AsesorDashboard.vue';
import ClienteDashboard from '../Componentes/Dashboards/ClienteDashboard.vue';
import { useAuth } from '../servicios/useAuth';

const { sesion } = useAuth();

const rol = computed(() => sesion.usuario?.rol);
const dashboard = computed(() => ({
  admin: AdminDashboard,
  vendedor: AsesorDashboard,
  cliente: ClienteDashboard,
}[rol.value] || ClienteDashboard));

const saludoRol = computed(() => ({
  admin: 'Panel de administración — visión global del negocio.',
  vendedor: 'Panel del asesor — cotizaciones, encomiendas y ventas.',
  cliente: 'Tu panel — cotizaciones, seguimiento de encomiendas y facturas.',
}[rol.value] || 'Panel de Servicargo.'));
</script>

<template>
  <AppLayout>
    <h1 class="titulo-pagina">Hola, {{ sesion.usuario?.nombre }}</h1>
    <p class="subtitulo">{{ saludoRol }}</p>

    <!-- Cada actor ve su propio dashboard, independiente -->
    <component :is="dashboard" />
  </AppLayout>
</template>
