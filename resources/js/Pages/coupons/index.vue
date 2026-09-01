<script setup>
import { ref, computed, nextTick, onBeforeUnmount } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import jsQR from 'jsqr';
import Layout from '@/Layouts/main.vue';

const props = defineProps({ coupons: Array, members: Array, assignments: Array });
const search = ref('');
const showCreate = ref(false);
const assigning = ref(null);
const filtered = computed(() => props.assignments.filter((a) => (a.member.name + a.member.member_code + a.coupon.name).toLowerCase().includes(search.value.toLowerCase())));
const createForm = useForm({ name: '', description: '', valid_from: new Date().toISOString().slice(0, 10), valid_until: '' });
const assignForm = useForm({ user_ids: [] });
const create = () => createForm.post(route('coupons.store'), { onSuccess: () => (showCreate.value = false) });
const assign = () => assignForm.post(route('coupons.assign', assigning.value.id), { onSuccess: () => (assigning.value = null) });
const redeem = (a) => confirm(`¿Canjear ${a.coupon.name} para ${a.member.name}?`) && router.post(route('coupons.redeem', a.id));

const scanning = ref(false);
const video = ref(null);
const scanError = ref('');
const qrData = ref(null);
const qrToken = ref('');
let stream;
let frame;
let detecting = false;
let scanCanvas;
let scanCtx;
let nativeDetector;

function getScanSurface() {
    if (!scanCanvas) {
        scanCanvas = document.createElement('canvas');
        scanCtx = scanCanvas.getContext('2d', { willReadFrequently: true });
    }

    return { canvas: scanCanvas, ctx: scanCtx };
}

function getNativeDetector() {
    if (nativeDetector === undefined) {
        try {
            nativeDetector = 'BarcodeDetector' in window
                ? new window.BarcodeDetector({ formats: ['qr_code'] })
                : null;
        } catch {
            nativeDetector = null;
        }
    }

    return nativeDetector;
}

async function openCamera() {
    const constraints = [
        { audio: false, video: { facingMode: { ideal: 'environment' } } },
        { audio: false, video: true },
    ];

    let lastError;
    for (const constraint of constraints) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraint);
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError || new Error('No se pudo abrir la cámara.');
}

function prepareVideo(el, mediaStream) {
    el.srcObject = mediaStream;
    el.setAttribute('playsinline', 'true');
    el.setAttribute('webkit-playsinline', 'true');
    el.muted = true;
    el.autoplay = true;
    el.playsInline = true;

    const play = () => el.play().catch(() => {});
    el.onloadedmetadata = play;

    return play();
}

function readQrFromSource(source, width, height) {
    if (!width || !height) {
        return null;
    }

    const { canvas, ctx } = getScanSurface();
    const maxSide = 480;
    const scale = Math.min(1, maxSide / Math.max(width, height));
    const dw = Math.max(1, Math.floor(width * scale));
    const dh = Math.max(1, Math.floor(height * scale));
    canvas.width = dw;
    canvas.height = dh;
    ctx.drawImage(source, 0, 0, dw, dh);
    const image = ctx.getImageData(0, 0, dw, dh);
    const result = jsQR(image.data, dw, dh, { inversionAttempts: 'attemptBoth' });

    return result?.data || null;
}

async function detectFromVideo(el) {
    const detector = getNativeDetector();
    if (detector) {
        try {
            const found = await detector.detect(el);
            if (found.length) {
                return found[0].rawValue;
            }
        } catch {
            // Safari/iOS no implementa BarcodeDetector; se usa jsQR.
        }
    }

    if (el.readyState < 2 || !el.videoWidth) {
        return null;
    }

    return readQrFromSource(el, el.videoWidth, el.videoHeight);
}

async function tick() {
    if (!scanning.value || detecting) {
        return;
    }

    detecting = true;
    try {
        const value = video.value ? await detectFromVideo(video.value) : null;
        if (value) {
            await validateToken(value);
            return;
        }
    } catch {
        // El siguiente frame reintenta.
    } finally {
        detecting = false;
    }

    if (scanning.value) {
        frame = requestAnimationFrame(tick);
    }
}

async function startScanner() {
    scanError.value = '';
    qrData.value = null;
    scanning.value = true;
    await nextTick();

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        scanError.value = 'Safari necesita HTTPS para abrir la cámara. También puedes subir una foto del QR.';
        return;
    }

    try {
        stream = await openCamera();
        await prepareVideo(video.value, stream);
        tick();
    } catch (e) {
        scanError.value = 'No se pudo abrir la cámara. Revisa los permisos o sube una foto del QR.';
    }
}

function stopScanner() {
    scanning.value = false;
    detecting = false;
    if (frame) {
        cancelAnimationFrame(frame);
        frame = null;
    }
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    if (video.value) {
        video.value.srcObject = null;
    }
}

async function scanFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
        return;
    }

    scanError.value = '';
    const image = new Image();
    const url = URL.createObjectURL(file);
    try {
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = reject;
            image.src = url;
        });
        const value = readQrFromSource(image, image.naturalWidth, image.naturalHeight);
        if (!value) {
            scanError.value = 'No encontramos un QR en esa imagen.';
            return;
        }
        await validateToken(value);
    } catch {
        scanError.value = 'No se pudo leer esa imagen.';
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function validateToken(token) {
    try {
        const { data } = await window.axios.post(route('coupons.qr.validate'), { token });
        qrToken.value = token;
        qrData.value = data;
        stopScanner();
    } catch (e) {
        scanError.value = e.response?.data?.errors?.token?.[0] || 'QR inválido.';
        if (scanning.value) {
            frame = requestAnimationFrame(tick);
        }
    }
}

function confirmQr() {
    router.post(route('coupons.qr.redeem'), { token: qrToken.value }, { onSuccess: () => { qrData.value = null; qrToken.value = ''; } });
}

onBeforeUnmount(stopScanner);
</script>
<template>
    <Layout>
        <Head title="Cupones"/>
        <div class="page">
            <header>
                <div>
                    <small>CUPONERA VIGENTE</small>
                    <h1>Cupones</h1>
                    <p>Crea, asigna y registra canjes.</p>
                </div>
                <div>
                    <button class="scan" @click="startScanner"><i class="ri-qr-scan-2-line"></i> Escanear QR</button>
                    <button class="primary" @click="showCreate=true">Crear cupón</button>
                </div>
            </header>
            <section class="cards">
                <article v-for="c in coupons" :key="c.id">
                    <div class="ticket">
                        <small>BENEFICIO PARA SOCIOS</small>
                        <h2>{{ c.name }}</h2>
                        <p>{{ c.description }}</p>
                    </div>
                    <dl>
                        <div><dt>Asignados</dt><dd>{{ c.assignments_count }}</dd></div>
                        <div><dt>Canjeados</dt><dd>{{ c.redeemed_count }}</dd></div>
                        <div><dt>Disponibles</dt><dd>{{ c.available_count }}</dd></div>
                    </dl>
                    <button @click="assigning=c">Asignar</button>
                </article>
            </section>
            <section class="list">
                <div class="list-head">
                    <h2>Canje manual</h2>
                    <input v-model="search" placeholder="Buscar socio o cupón">
                </div>
                <table>
                    <thead>
                        <tr><th>Socio</th><th>Cupón</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in filtered" :key="a.id">
                            <td><b>{{ a.member.name }}</b><small>{{ a.member.member_code }}</small></td>
                            <td>{{ a.coupon.name }}</td>
                            <td><button @click="redeem(a)">Canjear</button></td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <div v-if="scanning" class="mask">
                <section class="scanner">
                    <button class="close" @click="stopScanner">×</button>
                    <h2>Escanear cupón</h2>
                    <div class="camera">
                        <video ref="video" autoplay muted playsinline></video>
                        <i></i>
                    </div>
                    <p>Apunta la cámara al QR mostrado por el socio.</p>
                    <label class="file">
                        <input type="file" accept="image/*" capture="environment" @change="scanFile">
                        Subir foto del QR
                    </label>
                    <div v-if="scanError" class="error">{{ scanError }}</div>
                </section>
            </div>
            <div v-if="qrData" class="mask">
                <section class="confirm">
                    <span>QR VERIFICADO</span>
                    <h2>{{ qrData.coupon.name }}</h2>
                    <p>{{ qrData.coupon.description }}</p>
                    <dl>
                        <div><dt>Socio</dt><dd>{{ qrData.member.name }}</dd></div>
                        <div><dt>Código</dt><dd>{{ qrData.member.member_code }}</dd></div>
                        <div><dt>Vigencia</dt><dd>{{ qrData.coupon.valid_until }}</dd></div>
                    </dl>
                    <div class="secure"><i class="ri-shield-check-line"></i> Token cifrado y válido</div>
                    <footer>
                        <button @click="qrData=null">Cancelar</button>
                        <button class="primary" @click="confirmQr">Confirmar canje</button>
                    </footer>
                </section>
            </div>
            <div v-if="showCreate" class="mask">
                <form class="form" @submit.prevent="create">
                    <h2>Crear cupón</h2>
                    <label>Nombre<input v-model="createForm.name" required></label>
                    <label>Descripción<textarea v-model="createForm.description"></textarea></label>
                    <div class="dates">
                        <label>Inicio<input v-model="createForm.valid_from" type="date" required></label>
                        <label>Fin<input v-model="createForm.valid_until" type="date" required></label>
                    </div>
                    <footer>
                        <button type="button" @click="showCreate=false">Cancelar</button>
                        <button class="primary">Crear</button>
                    </footer>
                </form>
            </div>
            <div v-if="assigning" class="mask">
                <form class="form" @submit.prevent="assign">
                    <h2>Asignar {{ assigning.name }}</h2>
                    <label v-for="m in members" :key="m.id" class="member">
                        <input v-model="assignForm.user_ids" type="checkbox" :value="m.id"> {{ m.name }} · {{ m.member_code }}
                    </label>
                    <footer>
                        <button type="button" @click="assigning=null">Cancelar</button>
                        <button class="primary">Asignar</button>
                    </footer>
                </form>
            </div>
        </div>
    </Layout>
</template>
<style scoped>
.page{max-width:1400px;margin:auto;color:#332820}
.page>header{display:flex;justify-content:space-between;align-items:end}
.page>header small,.confirm>span{color:#8f3b28;letter-spacing:.18em;font-weight:800}
h1,h2{font-family:Georgia,serif}
h1{font-size:34px;margin:5px 0}
button{border:1px solid #8f3b28;background:white;color:#8f3b28;border-radius:9px;padding:10px 14px}
.primary{background:#8f3b28;color:white}
.scan{background:#576443;color:white;border-color:#576443;margin-right:8px}
.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:25px}
.cards article,.list{background:white;border-radius:15px;overflow:hidden}
.ticket{background:#576443;color:#fff8e9;padding:24px;min-height:150px}
.ticket h2{text-transform:uppercase}
.cards dl{display:grid;grid-template-columns:repeat(3,1fr);padding:14px}
.cards dt{font-size:8px;color:#9c8a72}
.cards dd{font-weight:700}
.cards article>button{margin:0 14px 14px}
.list{margin-top:22px;padding:20px}
.list-head{display:flex;justify-content:space-between}
.list-head input,.form input,.form textarea{border:1px solid #ded5c7;border-radius:8px;padding:10px}
.list table{width:100%;margin-top:12px;border-collapse:collapse}
td,th{padding:12px;border-top:1px solid #eee7da;text-align:left}
td small{display:block;color:#91877e}
.mask{position:fixed;inset:0;background:rgba(0,0,0,.68);z-index:1200;display:grid;place-items:center;padding:20px;overflow:auto;-webkit-overflow-scrolling:touch}
.scanner,.confirm,.form{width:min(520px,100%);background:#fff8e9;border-radius:18px;padding:26px;position:relative;max-height:100%;overflow:auto}
.close{position:absolute;right:12px;top:12px;border:0;font-size:24px}
.camera{background:#191919;aspect-ratio:1;border-radius:14px;overflow:hidden;position:relative}
.camera video{width:100%;height:100%;object-fit:cover;display:block;background:#191919}
.camera i{position:absolute;inset:18%;border:3px solid #bfb574;border-radius:12px;pointer-events:none}
.scanner p{text-align:center}
.file{display:block;margin:12px 0 0;text-align:center;color:#576443;font-weight:700;cursor:pointer}
.file input{display:none}
.error{background:#f4dfd9;color:#8f3b28;padding:12px;margin-top:12px}
.confirm dl{display:grid;grid-template-columns:repeat(3,1fr);background:#eee7da;padding:14px;border-radius:10px}
.confirm dt{font-size:8px;color:#8f3b28}
.confirm dd{margin:4px 0;font-weight:700}
.secure{color:#576443;padding:15px 0}
.confirm footer,.form footer{display:flex;justify-content:flex-end;gap:10px}
.form label{display:block;margin-top:12px}
.form input:not([type=checkbox]),.form textarea{display:block;width:100%}
.dates{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.member{background:white;padding:9px}
@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.cards{grid-template-columns:1fr}.page>header,.list-head{align-items:start;gap:12px;flex-direction:column}}
</style>
