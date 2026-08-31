<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const menuOpen = ref(false);
const user = computed(() => page.props.auth?.user ?? {});
const initials = computed(() => (user.value.name || 'Administrador').split(' ').slice(0, 2).map(word => word[0]).join('').toUpperCase());

const toggleSidebar = () => {
  const opening = !document.body.classList.contains('vertical-sidebar-enable');
  document.body.classList.toggle('vertical-sidebar-enable', opening);
  menuOpen.value = false;
};
const logout = () => router.post(route('logout'));
</script>

<template>
  <header id="page-topbar" class="hmr-topbar">
    <div class="hmr-topbar-inner">
      <button class="hmr-menu-button" type="button" aria-label="Abrir menú de navegación" @click="toggleSidebar">
        <i class="ri-menu-2-line"></i>
      </button>

      <div class="hmr-topbar-title">
        <span>Panel administrativo</span>
        <small>Club de Socios</small>
      </div>

      <div class="hmr-user">
        <button class="hmr-user-button" type="button" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">
          <span class="hmr-avatar">{{ initials }}</span>
          <span class="hmr-user-copy">
            <strong>{{ user.name || 'Administrador' }}</strong>
            <small>Administrador</small>
          </span>
          <i class="ri-arrow-down-s-line"></i>
        </button>
        <div v-if="menuOpen" class="hmr-user-menu">
          <Link v-if="route().has('profile.edit')" :href="route('profile.edit')"><i class="ri-user-line"></i> Mi perfil</Link>
          <button type="button" @click="logout"><i class="ri-logout-box-r-line"></i> Cerrar sesión</button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.hmr-topbar{position:fixed;inset:0 0 auto 250px;height:72px;background:#fff;border-bottom:1px solid #ece5d9;z-index:1002;box-shadow:0 3px 18px rgba(51,40,32,.04)}
.hmr-topbar-inner{height:100%;display:flex;align-items:center;padding:0 28px;gap:16px}
.hmr-menu-button{display:none;width:44px;height:44px;border:0;border-radius:10px;background:#f7f3ea;color:#332820;font-size:22px}
.hmr-topbar-title{display:flex;flex-direction:column;color:#332820}.hmr-topbar-title span{font-family:Georgia,serif;font-size:19px;font-weight:700}.hmr-topbar-title small{color:#8f3b28;font-size:10px;text-transform:uppercase;letter-spacing:.14em}
.hmr-user{position:relative;margin-left:auto}.hmr-user-button{display:flex;align-items:center;gap:11px;min-height:52px;padding:6px 10px;border:0;border-radius:12px;background:transparent;color:#332820;text-align:left}.hmr-user-button:hover{background:#f7f3ea}.hmr-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:#ede8d7;color:#8f3b28;font-weight:700}.hmr-user-copy{display:flex;flex-direction:column;max-width:230px}.hmr-user-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}.hmr-user-copy small{color:#8a7b6e;font-size:11px}.hmr-user-menu{position:absolute;right:0;top:58px;width:190px;padding:7px;background:#fff;border:1px solid #ece5d9;border-radius:12px;box-shadow:0 14px 35px rgba(51,40,32,.14)}.hmr-user-menu a,.hmr-user-menu button{display:flex;align-items:center;gap:9px;width:100%;padding:10px;border:0;border-radius:8px;background:transparent;color:#332820;font-size:13px;text-align:left}.hmr-user-menu a:hover,.hmr-user-menu button:hover{background:#f7f3ea;color:#8f3b28}
@media(max-width:991.98px){.hmr-topbar{left:0;height:68px}.hmr-topbar-inner{padding:0 16px}.hmr-menu-button{display:grid;place-items:center}.hmr-topbar-title span{font-size:17px}}
@media(max-width:575.98px){.hmr-topbar-inner{padding:0 10px;gap:8px}.hmr-topbar-title small,.hmr-user-copy,.hmr-user-button>i{display:none}.hmr-topbar-title span{font-size:15px}.hmr-user-button{padding:4px}.hmr-avatar{width:40px;height:40px}}
</style>
