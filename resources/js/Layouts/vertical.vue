<script>
import { Link } from '@inertiajs/vue3';
// import router from "@/router";
import simplebar from "simplebar-vue";
import { layoutComputed } from "@/state/helpers";

import NavBar from "@/Components/nav-bar.vue";
import Menu from "@/Components/menu.vue";
import RightBar from "@/Components/right-bar.vue";
import Footer from "@/Components/footer.vue";
localStorage.setItem('hoverd', false);

/**
 * Vertical layout
 */
export default {
  components: { NavBar, RightBar, Footer, simplebar, Menu, Link },
  data() {
    return {
      isMenuCondensed: false,
    };
  },
  computed: {
    ...layoutComputed,
    sidebarSize: {
      get() {
        return this.$store ? this.$store.state.layout.sidebarSize : {} || {};
      },
      set(type) {
        return this.changeSidebarSize({
          sidebarSize: type,
        });
      },
    },
  },
  created: () => {
    document.body.removeAttribute("data-layout", "horizontal");
    document.body.removeAttribute("data-topbar", "dark");
    document.body.removeAttribute("data-layout-size", "boxed");
  },
  methods: {
    initActiveMenu() {
      if (document.documentElement.getAttribute('data-sidebar-size') === 'sm-hover') {
        localStorage.setItem('hoverd', true);
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover-active');
      } else if (document.documentElement.getAttribute('data-sidebar-size') === 'sm-hover-active') {
        localStorage.setItem('hoverd', false);
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover');
      } else {
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover');
      }
    },
    toggleMenu() {
      document.body.classList.toggle("sidebar-enable");

      if (window.screen.width >= 992) {
        // eslint-disable-next-line no-unused-vars
        router.afterEach((routeTo, routeFrom) => {
          document.body.classList.remove("sidebar-enable");
          document.body.classList.remove("vertical-collpsed");
        });
        document.body.classList.toggle("vertical-collpsed");
      } else {
        // eslint-disable-next-line no-unused-vars
        router.afterEach((routeTo, routeFrom) => {
          document.body.classList.remove("sidebar-enable");
        });
        document.body.classList.remove("vertical-collpsed");
      }
      this.isMenuCondensed = !this.isMenuCondensed;
    },
    toggleRightSidebar() {
      document.body.classList.toggle("right-bar-enabled");
    },
    hideRightSidebar() {
      document.body.classList.remove("right-bar-enabled");
    },

  },
  mounted() {
    if (localStorage.getItem('hoverd') == 'true') {
      document.documentElement.setAttribute('data-sidebar-size', 'sm-hover-active');
    }

    document.getElementById('overlay').addEventListener('click', () => {
      document.body.classList.remove('vertical-sidebar-enable');
    });
  }
};
</script>
  
<template>
  <div id="layout-wrapper">
    <NavBar />
    <div>
      <!-- ========== Left Sidebar Start ========== -->
      <!-- ========== App Menu ========== -->
      <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box">
          <!-- Dark Logo-->
          <Link href="/" class="logo logo-dark hmr-brand">
            <span class="logo-sm">
              <span class="hmr-monogram">H</span>
            </span>
            <span class="logo-lg">
              <span class="hmr-wordmark">HACIENDA <b>MONTE RICO</b><small>CLUB DE SOCIOS</small></span>
            </span>
          </Link>
          <!-- Light Logo-->
          <Link href="/" class="logo logo-light hmr-brand">
            <span class="logo-sm">
              <span class="hmr-monogram">H</span>
            </span>
            <span class="logo-lg">
              <span class="hmr-wordmark">HACIENDA <b>MONTE RICO</b><small>CLUB DE SOCIOS</small></span>
            </span>
          </Link>
          <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover" @click="initActiveMenu">
            <i class="ri-record-circle-line"></i>
          </button>
        </div>

        <simplebar id="scrollbar" class="h-100" ref="scrollbar">
          <Menu></Menu>
        </simplebar>
        <div class="sidebar-background"></div>
      </div>
      <!-- Left Sidebar End -->
      <!-- Vertical Overlay-->
      <div class="vertical-overlay" id="overlay"></div>
    </div>
    <!-- ============================================================== -->
    <!-- Start Page Content here -->
    <!-- ============================================================== -->

    <div class="main-content">
      <div class="page-content">
        <!-- Start Content-->
        <b-container fluid>
          <slot />
        </b-container>
      </div>
      <Footer />
    </div>
    <RightBar />
  </div>
</template>
<style>:root{--hmr-cream:#fff8e9;--hmr-gold:#bfb574;--hmr-green:#576443;--hmr-taupe:#9c8a72;--hmr-rust:#8f3b28;--hmr-brown:#332820;--hmr-ink:#191919}.app-menu{background:var(--hmr-brown)!important;border-right:0!important}.navbar-brand-box{background:var(--hmr-brown)!important;height:88px!important}.hmr-brand{height:88px!important;padding-top:15px}.hmr-wordmark{font-family:Georgia,'Times New Roman',serif;color:var(--hmr-cream);line-height:.85;display:inline-flex;flex-direction:column;font-size:12px;letter-spacing:.08em}.hmr-wordmark b{font-size:22px;letter-spacing:-.03em}.hmr-wordmark small{font-family:Arial,sans-serif;font-size:7px;letter-spacing:.22em;margin-top:7px;color:var(--hmr-gold)}.hmr-monogram{font-family:Georgia,serif;color:var(--hmr-cream);font-size:28px;font-weight:700}.page-content{background:#f7f3ea!important;padding-top:112px!important}.main-content{min-height:100vh}.card{border:0;box-shadow:0 8px 30px rgba(51,40,32,.06)}</style>
