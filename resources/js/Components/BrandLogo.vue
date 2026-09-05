<script setup>
import logo from '@/../images/brand/hmr-logo.png';
defineProps({ light: Boolean });
</script>
<template><span class="hmr-logo" :class="{ light }" role="img" aria-label="Hacienda MonteRico, desde 1906" :style="{ '--logo': `url(${logo})` }"></span></template>
<style scoped>
.hmr-logo{display:inline-block;width:210px;max-width:100%;aspect-ratio:2.2;background:#191919;mask:var(--logo) center/contain no-repeat;-webkit-mask:var(--logo) center/contain no-repeat;flex-shrink:0}
.hmr-logo.light{background:#fff8e9}
</style>
