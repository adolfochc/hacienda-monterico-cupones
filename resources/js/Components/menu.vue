<script setup>
import { Link } from '@inertiajs/vue3';
const items = [
  { label: 'Resumen', href: '/', icon: 'ri-layout-grid-line' },
  { label: 'Socios', href: '/socios', icon: 'ri-group-line' },
  { label: 'Cupones', href: '/cupones', icon: 'ri-coupon-3-line' },
];
const closeMobileMenu = () => document.body.classList.remove('vertical-sidebar-enable');
</script>
<template><div class="hmr-sidebar"><p class="hmr-menu-label">GESTIÓN</p><ul class="navbar-nav" id="navbar-nav"><li v-for="item in items" :key="item.href" class="nav-item"><Link :href="item.href" class="nav-link menu-link" :class="{ active: $page.url === item.href }" @click="closeMobileMenu"><i :class="item.icon"></i><span>{{ item.label }}</span></Link></li></ul><div class="hmr-help"><i class="ri-customer-service-2-line"></i><strong>¿Necesitas ayuda?</strong><span>Contacta al equipo de soporte.</span></div></div></template>
<style scoped>.hmr-sidebar{height:100%;padding:22px 14px;display:flex;flex-direction:column}.hmr-menu-label{font-size:10px;letter-spacing:.18em;color:#bfb574;margin:4px 16px 12px;font-weight:700}.menu-link{border-radius:10px;margin-bottom:6px;color:#fff8e9!important;font-weight:600;padding:12px 14px!important}.menu-link i{color:#bfb574;font-size:20px;margin-right:12px}.menu-link:hover,.menu-link.active{background:rgba(191,181,116,.18)!important;color:#fff!important}.hmr-help{margin-top:auto;border:1px solid rgba(255,248,233,.14);border-radius:14px;padding:16px;color:#fff8e9;display:grid;gap:4px}.hmr-help i{font-size:24px;color:#bfb574}.hmr-help span{font-size:12px;opacity:.65}</style>
