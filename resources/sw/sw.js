// Service worker của app học Lernbuch (PWA, G1 tuần 5): cài được ra màn hình chính, mở app và học tiếp khi mất mạng.
// Máy chủ phát file này ở /sw.js (ServiceWorkerController), thay __PHIEN_BAN__ bằng mã của bản build: mỗi lần build mới
// thì trình duyệt cài lại service worker, lưu khung app mới và xoá khung cũ.
//
// - Khung app (/app/...): lấy từ mạng trước (chậm quá 4 giây hay mất mạng thì dùng bản đã lưu), không có bản lưu thì
//   hiện trang báo cần mạng. Chỉ lưu khung khi đã đăng nhập (không lưu trang đăng nhập).
// - File đã build (/build/...), biểu tượng, manifest: dùng bản lưu (tên file có mã băm nên không cũ).
// - Vài API chỉ đọc (hồ sơ, Hôm nay, chủ đề, ngữ pháp…): lấy từ mạng trước, mất mạng thì trả bản đã lưu kèm đầu
//   X-Lernbuch-Ban-Luu: 1. Máy chủ trả 401 (hết đăng nhập) thì xoá bản lưu API.
// - Audio: app chép audio của chủ đề đã tải vào kho audio (offline.ts); ở đây phát từ kho, kể cả yêu cầu một đoạn (Range).
// - Lượt chấm, lập phiên (POST) không đi qua đây: app tự giữ hàng chờ và tự lập phiên khi mất mạng.

const PHIEN_BAN = '__PHIEN_BAN__';
const KHO_KHUNG = `lernbuch-khung-${PHIEN_BAN}`;
const KHO_API = 'lernbuch-api-v1';
const KHO_AUDIO = 'lernbuch-audio-v1';
const KHUNG = '/app';
const CHO_MANG_MS = 4000;
const API_TOI_DA = 200;
const API_LUU = /^\/api\/v1\/(toi|toi\/cai-dat|hom-nay|bo-the|chu-de|chu-de\/[a-z0-9-]+|the\/\d+|so-tay|ngu-phap|ngu-phap\/[A-Za-z0-9-]+|bai-tap|dong-tu)$/;
/** Lưu sẵn lúc cài, để lần mở đầu tiên khi mất mạng đã có hồ sơ, cài đặt, Hôm nay và danh sách chủ đề. */
const API_LUU_SAN = ['/api/v1/toi', '/api/v1/hom-nay', '/api/v1/chu-de', '/api/v1/bo-the'];
const FILE_GOC = ['/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png'];
/** File vào của app học trong manifest của Vite (không lấy trang quản trị). */
const LOI_VAO = ['resources/js/app/main.tsx', 'resources/css/app.css'];
const DAU_API = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

self.addEventListener('install', (event) => {
    event.waitUntil(luuLucCai().finally(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            for (const ten of await caches.keys()) {
                if (ten.startsWith('lernbuch-khung-') && ten !== KHO_KHUNG) await caches.delete(ten);
            }
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const yc = event.request;
    if (yc.method !== 'GET') return;
    const url = new URL(yc.url);

    if (yc.destination === 'audio' || (url.origin === self.location.origin && url.pathname.startsWith('/audio/'))) {
        event.respondWith(audio(yc));
        return;
    }
    if (url.origin !== self.location.origin) return;

    if (yc.mode === 'navigate') {
        if (url.pathname === KHUNG || url.pathname.startsWith(`${KHUNG}/`)) event.respondWith(khungApp(yc, event));
        return;
    }
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(banLuuTruoc(yc, event, false));
        return;
    }
    if (FILE_GOC.includes(url.pathname)) {
        event.respondWith(banLuuTruoc(yc, event, true));
        return;
    }
    if (API_LUU.test(url.pathname)) event.respondWith(apiMangTruoc(yc, event));
});

// ---------------------------------------------------------------- lúc cài

async function luuLucCai() {
    const kho = await caches.open(KHO_KHUNG);
    const tep = [...FILE_GOC];
    try {
        const r = await fetch('/build/manifest.json', { cache: 'no-store' });
        if (r.ok) tep.push(...fileCuaApp(await r.json()));
    } catch {
        // chưa build hay mất mạng: file sẽ được lưu dần khi app tải
    }
    await Promise.all(tep.map((u) => luuNeuDuoc(kho, u, (r) => r.ok)));
    await luuNeuDuoc(kho, KHUNG, laKhung, { cache: 'no-store' });
    const api = await caches.open(KHO_API);
    await Promise.all(API_LUU_SAN.map((u) => luuNeuDuoc(api, u, (r) => r.ok, { headers: DAU_API, cache: 'no-store' })));
}

/** File JS, CSS và font của app học theo manifest của Vite. */
function fileCuaApp(manifest) {
    const ra = new Set();
    const them = (khoa) => {
        const muc = manifest[khoa];
        if (!muc || ra.has(`/build/${muc.file}`)) return;
        ra.add(`/build/${muc.file}`);
        for (const f of [...(muc.css || []), ...(muc.assets || [])]) ra.add(`/build/${f}`);
        for (const k of muc.imports || []) them(k);
    };
    LOI_VAO.forEach(them);
    for (const [khoa, muc] of Object.entries(manifest)) if (khoa.endsWith('.woff2')) ra.add(`/build/${muc.file}`);
    return [...ra];
}

async function luuNeuDuoc(kho, url, dat, tuyChon = {}) {
    try {
        const r = await fetch(url, { credentials: 'same-origin', ...tuyChon });
        if (dat(r)) await kho.put(url, r);
    } catch {
        // bỏ qua: lần sau tải được thì lưu
    }
}

/** Khung app thật (đã đăng nhập, đã xác minh email): trang HTML 200, không bị chuyển sang trang khác. */
function laKhung(r) {
    return r.ok && r.type === 'basic' && !r.redirected && (r.headers.get('content-type') || '').includes('text/html');
}

// ---------------------------------------------------------------- khung app

async function khungApp(yc, event) {
    const kho = await caches.open(KHO_KHUNG);
    const mang = fetch(yc);
    // Có khung mới thì lưu, kể cả khi mạng về muộn sau lúc đã trả bản lưu (sao chép trước khi trả cho trang)
    event.waitUntil(mang.then((r) => (laKhung(r) ? kho.put(KHUNG, r.clone()) : undefined)).catch(() => {}));
    try {
        return await voiHan(mang, CHO_MANG_MS);
    } catch {
        const luu = await kho.match(KHUNG);
        if (luu) return luu;
        try {
            return await mang;
        } catch {
            return trangCanMang();
        }
    }
}

function voiHan(hua, ms) {
    return new Promise((xong, hong) => {
        const hen = setTimeout(() => hong(new Error('cham')), ms);
        hua.then(
            (r) => {
                clearTimeout(hen);
                xong(r);
            },
            (e) => {
                clearTimeout(hen);
                hong(e);
            },
        );
    });
}

function trangCanMang() {
    const html = `<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lernbuch</title><style>body{margin:0;font-family:system-ui,sans-serif;background:#f8f9fe;color:#1c1a33;display:grid;place-items:center;min-height:100vh}
main{max-width:28rem;padding:1.5rem}h1{font-size:1.5rem}button{min-height:3rem;padding:0 1.25rem;border:0;border-radius:.5rem;background:#2d2364;color:#fff;font:inherit;font-weight:600}</style></head>
<body><main><h1>Chưa mở được Lernbuch</h1><p>Lần đầu mở app trên máy này cần có mạng. Khi có mạng, mở app một lần và tải chủ đề về máy; sau đó học được cả khi mất mạng.</p>
<button type="button" onclick="location.reload()">Thử lại</button></main></body></html>`;
    return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } });
}

// ---------------------------------------------------------------- file tĩnh

async function banLuuTruoc(yc, event, lamMoi) {
    const kho = await caches.open(KHO_KHUNG);
    const co = await kho.match(yc, { ignoreVary: true, ignoreSearch: true });
    if (co && !lamMoi) return co;
    const mang = fetch(yc).then((r) => {
        if (r.ok && r.type === 'basic') event.waitUntil(kho.put(yc, r.clone()).catch(() => {}));
        return r;
    });
    if (!co) return mang;
    // Biểu tượng, manifest: trả bản lưu ngay, lấy bản mới cho lần sau
    event.waitUntil(mang.catch(() => {}));
    return co;
}

// ---------------------------------------------------------------- API chỉ đọc

async function apiMangTruoc(yc, event) {
    const kho = await caches.open(KHO_API);
    try {
        const r = await fetch(yc);
        if (r.ok) event.waitUntil(kho.put(yc, r.clone()).then(() => catBot(kho)).catch(() => {}));
        else if (r.status === 401) event.waitUntil(caches.delete(KHO_API));
        return r;
    } catch (e) {
        const co = await kho.match(yc, { ignoreVary: true });
        if (!co) throw e;
        const dau = new Headers(co.headers);
        dau.set('X-Lernbuch-Ban-Luu', '1');
        return new Response(co.body, { status: co.status, statusText: co.statusText, headers: dau });
    }
}

/** Giữ tối đa API_TOI_DA bản lưu API: bỏ bản lưu lâu nhất (thứ tự trong kho là thứ tự lưu). */
async function catBot(kho) {
    const khoa = await kho.keys();
    await Promise.all(khoa.slice(0, Math.max(0, khoa.length - API_TOI_DA)).map((k) => kho.delete(k)));
}

// ---------------------------------------------------------------- audio

async function audio(yc) {
    const kho = await caches.open(KHO_AUDIO);
    const co = await kho.match(yc.url, { ignoreVary: true });
    if (!co) return fetch(yc);
    const doan = yc.headers.get('range');
    if (!doan || co.type === 'opaque') return co;
    return motDoan(co, doan);
}

/** Trả một đoạn của file đã lưu (206), như máy chủ trả cho trình phát audio khi hỏi Range: bytes=a-b. */
async function motDoan(r, doan) {
    const m = /^bytes=(\d*)-(\d*)$/.exec(doan.trim());
    const du = await r.arrayBuffer();
    const tong = du.byteLength;
    if (!m || (m[1] === '' && m[2] === '')) return new Response(du, { status: 200, headers: r.headers });
    let dau;
    let cuoi;
    if (m[1] === '') {
        dau = Math.max(0, tong - Number(m[2]));
        cuoi = tong - 1;
    } else {
        dau = Number(m[1]);
        cuoi = m[2] === '' ? tong - 1 : Math.min(Number(m[2]), tong - 1);
    }
    if (dau >= tong || dau > cuoi) return new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${tong}` } });
    const dauRa = new Headers(r.headers);
    dauRa.set('Content-Range', `bytes ${dau}-${cuoi}/${tong}`);
    dauRa.set('Content-Length', String(cuoi - dau + 1));
    return new Response(du.slice(dau, cuoi + 1), { status: 206, statusText: 'Partial Content', headers: dauRa });
}
