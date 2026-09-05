<script setup>
import { computed, ref, nextTick, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import BrandLogo from '@/Components/BrandLogo.vue';

const props = defineProps({ assignments: Array, campaign: Object });
const filter = ref('Todos');
const filters = ['Todos', 'Disponibles', 'Comida', 'Bebidas', 'Canjeados'];
const category = a => /vino|tinto|bebida|descorche|bandejeable/i.test(a.coupon.name) ? 'Bebidas' : 'Comida';
const filtered = computed(() => props.assignments.filter(a => filter.value === 'Todos' || (filter.value === 'Disponibles' ? a.status === 'available' : filter.value === 'Canjeados' ? a.status === 'redeemed' : category(a) === filter.value)));
const statusLabel = a => ({available:'Ver cupón',redeemed:'Canjeado',expired:'Vencido',upcoming:'Próximamente',inactive:'No disponible'}[a.status] || 'No disponible');
const code = ref('');
const dialog = ref(null);
let generation = 0;
let opener;
let expiresAt = 0;

const selected = ref(null);
const qrImage = ref('');
const secondsLeft = ref(0);
const qrLoading = ref(false);
const qrError = ref('');
let timer = null;

const logout = () => router.post(route('logout'));

const countdown = () => `${String(Math.floor(secondsLeft.value / 60)).padStart(2, '0')}:${String(Math.max(0, secondsLeft.value % 60)).padStart(2, '0')}`;

async function refreshQr() {
    if (!selected.value || qrLoading.value) return;
    const request = ++generation;
    const assignmentId = selected.value.id;
    qrLoading.value = true;
    qrError.value = '';
    code.value = '';
    qrImage.value = '';
    secondsLeft.value = 0;
    clearInterval(timer);
    try {
        const { data } = await window.axios.post(route('member.coupons.qr', assignmentId));
        if (request !== generation || selected.value?.id !== assignmentId) return;
        code.value = data.code;
        expiresAt = data.expires_at;
        secondsLeft.value = Math.max(0, expiresAt - Math.floor(Date.now()/1000));
        timer = setInterval(() => {
            secondsLeft.value = Math.max(0, expiresAt - Math.floor(Date.now()/1000));
            if (!secondsLeft.value) {
                clearInterval(timer); code.value = ''; qrImage.value = '';
                qrError.value = 'El código venció. Genera uno nuevo cuando el personal esté listo.';
            }
        }, 1000);
        const image = await QRCode.toDataURL(data.token, {width:300, margin:4, color:{dark:'#332820',light:'#ffffff'}, errorCorrectionLevel:'M'});
        if (request === generation) qrImage.value = image;
    } catch {
        if (request === generation) qrError.value = code.value ? 'No pudimos dibujar el QR. Puedes dictar el código numérico.' : 'No se pudo generar el código. Reintenta o consulta al personal.';
    } finally {
        if (request === generation) qrLoading.value = false;
    }
}
async function openCoupon(a, event) {
    if (a.status !== 'available') return;
    opener = event?.currentTarget;
    selected.value = a;
    await nextTick();
    dialog.value.showModal();
    refreshQr();
}
function closeCoupon() {
    generation++;
    clearInterval(timer);
    dialog.value?.close();
    selected.value = null;
    qrLoading.value = false;
    code.value = ''; qrImage.value = ''; qrError.value = '';
    opener?.focus();
    router.reload({only:['assignments','campaign'],preserveScroll:true});
}

onBeforeUnmount(() => { generation++; clearInterval(timer); });
</script>
<template>
    <Head title="Mis cupones"/>
    <main class="hmr-brand member-page">
        <header>
            <BrandLogo/>
            <button @click="logout">Cerrar sesión</button>
        </header>
        <section class="welcome">
            <span>CLUB DE SOCIOS · TU CUPONERA</span>
            <h1>Un gusto tenerte aquí, {{ $page.props.auth.user.name.split(' ')[0] }}</h1>
            <p>Tu próxima visita tiene algo especial. Elige tu beneficio y disfrútalo en Hacienda MonteRico.</p>
        </section>
        <div class="section-title"><h2>Momentos para compartir</h2><span>{{ assignments.length }} cupones en tu cuenta</span></div>
        <nav class="filters" aria-label="Filtrar cupones"><button v-for="item in filters" :key="item" :aria-pressed="filter===item" @click="filter=item">{{item}}</button></nav>
        <section class="grid" aria-live="polite">
            <article v-for="a in filtered" :key="a.id" :class="a.status" >
                <button class="coupon" :class="{'long-title':a.coupon.name.length>20}" :disabled="a.status!=='available'" :aria-label="`${statusLabel(a)}: ${a.coupon.name}`" @click="openCoupon(a,$event)"><div class="coupon-title">
                    <span>BENEFICIO PARA SOCIOS</span>
                    <h2>{{ a.coupon.name }}</h2>
                    </div><div class="coupon-detail"><i :class="category(a)==='Bebidas'?'ri-goblet-line':'ri-restaurant-line'" aria-hidden="true"></i><p>{{ a.coupon.description }}</p><span>{{ statusLabel(a) }} →</span>
                    </div></button>
                <footer>
                    <div>
                        <small>DEL {{ a.coupon.valid_from }} AL</small>
                        <b>{{ a.coupon.valid_until }}</b>
                    </div>
                    <strong>{{ statusLabel(a) }}</strong>
                </footer>
            </article>
        </section>
        <div v-if="!filtered.length" class="empty"><h2>{{ assignments.length?'No hay cupones en esta categoría':'Tu cuponera está por comenzar' }}</h2><p>{{ assignments.length?'Prueba con otro filtro para ver tus beneficios.':'Consulta al restaurante para conocer los beneficios de tu tarjeta.' }}</p></div>
        <aside v-if="campaign" class="incentive"><i class="ri-service-bell-line" aria-hidden="true"></i><div><h2>Vuelve por una cena doble</h2><p>Cada cupón de la campaña canjeado suma una participación para {{ campaign.prize }}.</p><p>Del {{ campaign.starts_on }} al {{ campaign.ends_on }} · Sorteo: {{ campaign.draw_on }}.</p></div><strong>{{ campaign.entries }}<small>TUS PARTICIPACIONES</small></strong></aside>
        <dialog ref="dialog" class="voucher" aria-labelledby="voucher-title" @cancel.prevent="closeCoupon"><template v-if="selected">
                <button aria-label="Cerrar cupón" class="close" @click="closeCoupon"><i class="ri-close-line"></i></button>
                <div class="voucher-head">
                    <BrandLogo light/>
                </div>
                <div class="voucher-body">
                    <span>CUPÓN DISPONIBLE</span>
                    <h2 id="voucher-title">{{ selected.coupon.name }}</h2>
                    <p>{{ selected.coupon.description }}</p>
                    <div class="qr">
                        <img v-if="qrImage && secondsLeft>0" :src="qrImage" alt="Código QR del cupón" width="230" height="230">
                        <div v-if="qrLoading" class="qr-placeholder" role="status">Preparando tu cupón…</div>
                        <p v-if="qrError" class="qr-error">{{ qrError }}</p>
                        <button v-if="qrError" class="retry" :disabled="qrLoading" @click="refreshQr">Generar de nuevo</button>
                        <div v-if="code && secondsLeft>0" class="manual-code"><span>O DICTA ESTE CÓDIGO AL PERSONAL</span><strong>{{ code.match(/.{1,5}/g).join(' ') }}</strong><small>Válido por {{ countdown() }}</small></div>
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
                    </dl><details v-if="selected.coupon.terms"><summary>Condiciones del beneficio</summary><p>{{selected.coupon.terms}}</p></details>
                </div>
                <div class="staff-note">
                    <i class="ri-shield-check-line"></i>
                    <div>
                        <b>Presenta el QR o el código al personal</b>
                        <span>Mostrar el cupón no lo marca como utilizado. Solo el personal puede confirmar el canje.</span>
                    </div>
                </div>
                <button class="ready" @click="closeCoupon">Entendido</button>
            </template></dialog>
        <footer class="product-footer">© {{ new Date().getFullYear() }} Jaketec. Todos los derechos reservados.</footer>
    </main>
</template>
<style scoped>
.member-page{background:#fff8e9;padding:0 max(20px,calc((100vw - 1160px)/2)) 32px}.member-page>header{height:115px}.member-page>header .hmr-logo{width:176px}.member-page>header button{min-height:44px;padding:10px 14px;border:1px solid #9c8a7266;border-radius:5px}.member-page .welcome{max-width:720px;padding:48px 0 36px}.member-page .welcome h1{color:#332820;font-size:clamp(29px,3.4vw,42px);margin:16px 0}.member-page .welcome p{font-size:14px;color:#706052;line-height:1.8}.section-title{display:flex;justify-content:space-between;align-items:center;gap:16px}.section-title h2{font-size:22px;color:#332820;margin:0}.section-title>span{font-size:11px;color:#706052}.filters{display:flex;gap:8px;padding:22px 2px;overflow-x:auto}.filters button{min-height:44px;padding:10px 18px;white-space:nowrap;border:1px solid #9c8a7270;background:transparent;color:#332820;border-radius:5px;font-size:12px}.filters button[aria-pressed=true]{background:#332820;color:#fff8e9;border-color:#332820}
.member-page .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.member-page .grid article{background:transparent;border-radius:0;overflow:visible;min-width:0}.member-page .grid article.available:hover{transform:none;box-shadow:none;outline:none}.member-page .grid .coupon{display:grid;grid-template-columns:1.08fr 1fr;width:100%;text-align:left;border:0;border-radius:9px;padding:24px;min-height:190px;position:relative;background-color:#8f3b28;background-image:repeating-radial-gradient(circle at 0 0,transparent 0,transparent 22px,#fff8e909 23px,transparent 24px);color:#fff8e9;box-shadow:0 5px 12px #33282012}.member-page .coupon:before{content:'';position:absolute;inset:9px;border:1px solid #fff8e9aa;border-radius:4px;pointer-events:none}.member-page .coupon:after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 0 50%,#fff8e9 0 12px,transparent 13px),radial-gradient(circle at 100% 50%,#fff8e9 0 12px,transparent 13px);pointer-events:none}.member-page .coupon-title{padding:10px 18px 10px 0;display:flex;flex-direction:column;justify-content:center}.member-page .coupon-title>span{font-size:8px;letter-spacing:.08em}.member-page .coupon h2{color:#fff8e9;font-size:clamp(21px,2.2vw,29px);text-transform:none;line-height:1.25;overflow-wrap:anywhere;margin:12px 0 0}.member-page .coupon-detail{border-left:1px dashed #fff8e980;padding:10px 0 10px 18px;display:flex;flex-direction:column;justify-content:center}.member-page .coupon-detail>i{font-size:23px}.member-page .coupon-detail p{font-size:12px;line-height:1.6;margin:10px 0 12px;opacity:1}.member-page .coupon-detail>span{font-size:10px;font-weight:600;letter-spacing:0}.member-page .grid footer{padding:10px 2px;font-size:10px;color:#706052;gap:12px}.member-page .grid footer small{display:inline;margin-right:4px;font-size:9px}.member-page .grid footer b{display:inline;font-weight:500}.member-page .grid footer strong{color:#8f3b28;font-size:10px}.member-page .coupon:disabled{opacity:1;cursor:default;background-color:#766356}.member-page .redeemed{opacity:1}.member-page .coupon:not(:disabled):hover{background-color:#793222}.incentive{display:flex;align-items:center;gap:24px;background:#9c8a7210;border:1px dashed #9c8a72;border-radius:9px;padding:26px;margin-top:32px}.incentive>i{font-size:42px;color:#8f3b28}.incentive h2{font-size:21px;margin:0 0 8px;color:#332820}.incentive p{font-size:12px;color:#706052;margin:0}.incentive>strong{margin-left:auto;text-align:center;font:32px 'Libre Baskerville',serif}.incentive small{display:block;font:8px Montserrat,sans-serif;white-space:nowrap;margin-top:8px}
dialog.voucher{position:fixed;padding:0;border:0;margin:auto;max-height:calc(100dvh - 24px);width:min(470px,calc(100vw - 24px));color:#332820;overscroll-behavior:contain}dialog.voucher::backdrop{background:#191919b8}dialog.voucher .voucher-head{align-items:center;padding:20px}dialog.voucher .voucher-head .hmr-logo{width:160px}dialog.voucher .close{width:44px;height:44px}dialog.voucher .voucher-body{padding:24px}dialog.voucher h2{color:#332820;font-size:26px;text-transform:none}dialog.voucher .voucher-body>p{font-size:12px}dialog.voucher .staff-note{background:#9c8a7218;color:#332820}.manual-code{width:100%;border:1px dashed #9c8a72;border-radius:8px;padding:12px;margin-top:12px}.manual-code>span{font-size:8px;letter-spacing:.08em}.manual-code strong{display:block;font-size:26px;letter-spacing:.1em;color:#332820;font-variant-numeric:tabular-nums}.manual-code small{display:block}.retry{padding:10px 16px;min-height:44px;border:1px solid #8f3b28;background:transparent;color:#8f3b28;border-radius:5px}dialog.voucher details{text-align:left;font-size:12px;margin-top:18px}dialog.voucher summary{padding:10px 0;cursor:pointer}dialog.voucher dl{font-size:11px;gap:10px}dialog.voucher dl div{padding:0}dialog.voucher .qr-placeholder{height:auto;min-height:80px}dialog.voucher .ready{min-height:48px}
@media(max-width:700px){.member-page>header{height:100px}.member-page>header .hmr-logo{width:150px}.member-page .welcome{padding:30px 0}.member-page .welcome p{font-size:12px}.section-title{align-items:start;flex-direction:column;gap:8px}.section-title h2{font-size:20px}.member-page .grid{grid-template-columns:1fr;gap:18px}.member-page .grid .coupon{padding:22px;min-height:172px}.member-page .coupon h2{font-size:24px}.member-page .coupon-detail p{font-size:11px}.member-page .coupon-title{padding-right:14px}.member-page .coupon-detail{padding-left:14px}.filters button{padding:9px 13px;font-size:11px}.incentive{gap:14px;padding:20px 16px;flex-wrap:wrap}.incentive>div{flex:1}.incentive>i{font-size:32px}.incentive h2{font-size:17px}.incentive>strong{width:100%;border-top:1px solid #9c8a7240;padding-top:16px}dialog.voucher .voucher-body{padding:20px}dialog.voucher h2{font-size:23px}dialog.voucher dl{grid-template-columns:1fr}.member-page .empty h2{font-size:22px}}@media(max-width:360px){.member-page{padding-left:12px;padding-right:12px}.member-page .coupon h2{font-size:21px}.member-page .coupon-detail p{font-size:10px}}
</style>
<style scoped>
@media(max-width:700px){.member-page .grid .coupon.long-title{grid-template-columns:1.2fr 1fr}.member-page .coupon.long-title h2{font-size:clamp(16px,4.7vw,21px);overflow-wrap:normal;hyphens:auto}}
</style>
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
.brand{font:11px 'Libre Baskerville',Georgia,serif;display:flex;flex-direction:column}
.brand b{font-size:22px}
.brand small{font:7px Arial;letter-spacing:.22em;color:#8f3b28}
header button{border:0;background:transparent;color:#8f3b28}
.welcome{padding:55px 0 30px}
.welcome span{font-size:9px;letter-spacing:.2em;color:#8f3b28;font-weight:800}
h1{font:40px 'Libre Baskerville',Georgia,serif;margin:8px 0}
.welcome p{color:#786f67}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.grid article{background:#fff;border-radius:16px;overflow:hidden;transition:.2s}
.grid article.available{cursor:pointer}
.grid article.available:hover,.grid article.available:focus{transform:translateY(-4px);box-shadow:0 18px 35px rgba(51,40,32,.14);outline:2px solid #bfb574}
.coupon{background:#8f3b28;color:#fff8e9;padding:28px;min-height:180px;position:relative}
.coupon span{font-size:8px;letter-spacing:.18em}
.coupon h2{font:28px 'Libre Baskerville',Georgia,serif;text-transform:uppercase}
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
.voucher-head{background:#332820;color:#fff8e9;padding:28px;text-align:center;display:flex;flex-direction:column;font-family:'Libre Baskerville',Georgia,serif}
.voucher-head small{letter-spacing:.12em}
.voucher-head b{font-size:20px;color:#bfb574}
.voucher-body{text-align:center;padding:32px}
.voucher-body>span{font-size:9px;letter-spacing:.18em;color:#8f3b28;font-weight:800}
.voucher-body h2{font:32px 'Libre Baskerville',Georgia,serif;text-transform:uppercase;margin:12px}
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
