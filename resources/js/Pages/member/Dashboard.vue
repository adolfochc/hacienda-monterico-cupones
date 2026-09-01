<script setup>
import { ref, onBeforeUnmount, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import QRCode from 'qrcode';

defineProps({ assignments: Array });

const selected = ref(null);
const qrImage = ref('');
const secondsLeft = ref(0);
const qrLoading = ref(false);
const qrError = ref('');
let timer = null;

const logout = () => router.post(route('logout'));

const countdown = () => `${String(Math.floor(secondsLeft.value / 60)).padStart(2, '0')}:${String(Math.max(0, secondsLeft.value % 60)).padStart(2, '0')}`;

async function refreshQr() {
    if (!selected.value) {
        return;
    }

    qrLoading.value = true;
    qrError.value = '';

    try {
        const { data } = await window.axios.post(route('member.coupons.qr', selected.value.id));
        secondsLeft.value = Math.max(0, data.expires_at - Math.floor(Date.now() / 1000));
        qrImage.value = await QRCode.toDataURL(data.token, {
            width: 460,
            margin: 2,
            color: { dark: '#332820', light: '#fff8e9' },
            errorCorrectionLevel: 'H',
        });
    } catch (e) {
        qrImage.value = '';
        qrError.value = e.response?.data?.message || 'No se pudo generar el QR.';
    } finally {
        qrLoading.value = false;
    }
}

async function openCoupon(a) {
    if (a.status !== 'available') {
        return;
    }

    selected.value = a;
    qrImage.value = '';
    await refreshQr();
    clearInterval(timer);
    timer = setInterval(async () => {
        secondsLeft.value--;
        if (secondsLeft.value <= 0) {
            await refreshQr();
        }
    }, 1000);
}

watch(selected, (value) => {
    if (!value) {
        clearInterval(timer);
        timer = null;
        qrImage.value = '';
        qrError.value = '';
    }
});

onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
    <Head title="Mis cupones"/>
    <main>
        <header>
            <div class="brand">HACIENDA <b>MONTE RICO</b><small>CLUB DE SOCIOS</small></div>
            <button @click="logout">Cerrar sesión</button>
        </header>
        <section class="welcome">
            <span>BIENVENIDO</span>
            <h1>Hola, {{ $page.props.auth.user.name.split(' ')[0] }}</h1>
            <p>Selecciona un cupón disponible y preséntalo al personal del restaurante.</p>
        </section>
        <section class="grid">
            <article v-for="a in assignments" :key="a.id" :class="a.status" :tabindex="a.status==='available'?0:-1" @click="openCoupon(a)" @keydown.enter="openCoupon(a)">
                <div class="coupon">
                    <span>BENEFICIO PARA SOCIOS</span>
                    <h2>{{ a.coupon.name }}</h2>
                    <p>{{ a.coupon.description }}</p>
                    <i v-if="a.status==='available'" class="ri-arrow-right-up-line open-icon"></i>
                </div>
                <footer>
                    <div>
                        <small>VÁLIDO HASTA</small>
                        <b>{{ a.coupon.valid_until }}</b>
                    </div>
                    <strong>{{ a.status==='available' ? 'Ver cupón' : 'Canjeado' }}</strong>
                </footer>
            </article>
        </section>
        <div v-if="!assignments.length" class="empty">Aún no tienes cupones asignados.</div>
        <div v-if="selected" class="mask" @click.self="selected=null">
            <section class="voucher">
                <button class="close" @click="selected=null"><i class="ri-close-line"></i></button>
                <div class="voucher-head">
                    <small>HACIENDA MONTE RICO</small>
                    <b>CLUB DE SOCIOS</b>
                </div>
                <div class="voucher-body">
                    <span>CUPÓN DISPONIBLE</span>
                    <h2>{{ selected.coupon.name }}</h2>
                    <p>{{ selected.coupon.description }}</p>
                    <div class="qr">
                        <img v-if="qrImage" :src="qrImage" alt="Código QR del cupón" width="230" height="230">
                        <div v-else class="qr-placeholder">{{ qrLoading ? 'Generando código…' : ' ' }}</div>
                        <p v-if="qrError" class="qr-error">{{ qrError }}</p>
                        <small>QR CIFRADO · SE RENUEVA EN {{ countdown() }}</small>
                    </div>
                    <dl>
                        <div>
                            <dt>Socio</dt>
                            <dd>{{ $page.props.auth.user.name }}</dd>
                        </div>
                        <div>
                            <dt>Válido hasta</dt>
                            <dd>{{ selected.coupon.valid_until }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="staff-note">
                    <i class="ri-shield-check-line"></i>
                    <div>
                        <b>Presenta este QR al personal</b>
                        <span>El cupón solo se bloqueará cuando el restaurante lo escanee y confirme el canje.</span>
                    </div>
                </div>
                <button class="ready" @click="selected=null">Entendido</button>
            </section>
        </div>
        <footer class="product-footer">© {{ new Date().getFullYear() }} Jaketec. Todos los derechos reservados.</footer>
    </main>
</template>
<style scoped>
.qr{margin:20px auto;display:flex;flex-direction:column;align-items:center}
.qr img,.qr-placeholder{width:230px;height:230px;max-width:min(230px,70vw);max-height:min(230px,70vw);aspect-ratio:1;border-radius:10px;display:block;background:#fff8e9}
.qr img{-webkit-user-select:none;user-select:none}
.qr-placeholder{display:grid;place-items:center;color:#9c8a72;font-size:12px}
.qr-error{margin:8px 0 0;color:#8f3b28;font-size:12px}
.qr small{font-size:8px;letter-spacing:.12em;color:#8f3b28;margin-top:7px}
</style>
<style scoped>
main{min-height:100vh;background:#f5f0e6;color:#332820;padding:0 6vw 60px}
header{height:90px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #ded5c7}
.brand{font:11px Georgia,serif;display:flex;flex-direction:column}
.brand b{font-size:22px}
.brand small{font:7px Arial;letter-spacing:.22em;color:#8f3b28}
header button{border:0;background:transparent;color:#8f3b28}
.welcome{padding:55px 0 30px}
.welcome span{font-size:9px;letter-spacing:.2em;color:#8f3b28;font-weight:800}
h1{font:40px Georgia,serif;margin:8px 0}
.welcome p{color:#786f67}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.grid article{background:#fff;border-radius:16px;overflow:hidden;transition:.2s}
.grid article.available{cursor:pointer}
.grid article.available:hover,.grid article.available:focus{transform:translateY(-4px);box-shadow:0 18px 35px rgba(51,40,32,.14);outline:2px solid #bfb574}
.coupon{background:#8f3b28;color:#fff8e9;padding:28px;min-height:180px;position:relative}
.coupon span{font-size:8px;letter-spacing:.18em}
.coupon h2{font:28px Georgia,serif;text-transform:uppercase}
.coupon p{opacity:.8}
.open-icon{position:absolute;right:20px;bottom:18px;font-size:25px}
.grid footer{display:flex;justify-content:space-between;padding:18px 22px;align-items:center}
.grid footer small,.grid footer b{display:block}
.grid footer strong{color:#576443}
.redeemed{opacity:.55}
.redeemed .coupon{background:#70675f}
.empty{text-align:center;padding:50px}
.mask{position:fixed;inset:0;background:rgba(25,25,25,.68);display:grid;place-items:center;padding:20px;padding:max(20px,env(safe-area-inset-top)) max(20px,env(safe-area-inset-right)) max(20px,env(safe-area-inset-bottom)) max(20px,env(safe-area-inset-left));z-index:1100;overflow:auto;-webkit-overflow-scrolling:touch}
.voucher{width:min(470px,100%);max-height:100%;background:#fff8e9;border-radius:20px;overflow:auto;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.3)}
.close{position:absolute;right:12px;top:12px;border:0;background:rgba(255,255,255,.12);color:#fff;border-radius:50%;width:35px;height:35px;font-size:20px}
.voucher-head{background:#332820;color:#fff8e9;padding:28px;text-align:center;display:flex;flex-direction:column;font-family:Georgia,serif}
.voucher-head small{letter-spacing:.12em}
.voucher-head b{font-size:20px;color:#bfb574}
.voucher-body{text-align:center;padding:32px}
.voucher-body>span{font-size:9px;letter-spacing:.18em;color:#8f3b28;font-weight:800}
.voucher-body h2{font:32px Georgia,serif;text-transform:uppercase;margin:12px}
.voucher-body>p{color:#786f67}
.code{background:#eee5d6;border:1px dashed #9c8a72;border-radius:12px;padding:18px;margin:25px 0}
.code small,.code strong{display:block}
.code small{font-size:8px;letter-spacing:.15em;color:#8f3b28}
.code strong{font-size:25px;letter-spacing:.08em;margin-top:5px}
dl{display:grid;grid-template-columns:1fr 1fr;text-align:left;margin:0}
dl div{padding:0 12px}
dt{font-size:9px;color:#9c8a72;text-transform:uppercase}
dd{font-weight:700;margin:4px 0}
.staff-note{display:flex;gap:12px;background:#e7ecdf;color:#4b5c40;padding:16px 24px}
.staff-note i{font-size:27px}
.staff-note span{display:block;font-size:11px;margin-top:3px;line-height:1.5}
.ready{display:block;width:calc(100% - 48px);margin:18px 24px;border:0;border-radius:9px;background:#8f3b28;color:white;padding:12px;font-weight:700}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.grid{grid-template-columns:1fr}h1{font-size:32px}}
</style>
