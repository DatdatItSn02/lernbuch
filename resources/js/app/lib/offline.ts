// Học khi mất mạng (PWA): tải một chủ đề về máy (thẻ, mức và hạn ôn hiện tại, đáp án nhiễu, audio), rồi khi không gọi được
// máy chủ thì tự lập phiên như máy chủ (PhienHoc.php) từ các chủ đề đã tải. Phiên lập lúc có mạng được giữ trên máy để mở lại
// app lúc mất mạng vẫn học tiếp phần còn lại. Lượt chấm vẫn đi qua hàng chờ (gửi khi có mạng); phiên lập trên máy không có mã.
import { api } from './api';
import { boHetLuot, guiDi, khiNhanLo, type KetQuaLo } from './hangCho';
import type { CheDo, PhienHoc, TheHoc } from './hoc';
import { BANG_CHU_DE, BANG_PHIEN, giaoDich } from './khoMay';
import type { TenGon } from './noiDung';
import { cham, dauNgay, dauNgayMai, type SoCham } from './srs';

/** Gói của một chủ đề đã tải (như máy chủ trả ở GET /api/v1/hoc/tai/{slug}). */
export type GoiChuDe = {
    slug: string;
    topic: TenGon & { collection: string };
    /** Múi giờ của người học lúc tải: hạn ôn tính theo ngày ở múi giờ này. */
    timezone: string;
    /** Số từ mới mỗi phiên; 0 là tất cả (một phiên tối đa MOI_TOI_DA). */
    new_per_session: number;
    /** Lúc máy chủ lập gói (Unix, giây). */
    saved_at: number;
    cards: TheHoc[];
};

export type TomTatGoi = { slug: string; name: string; so_the: number; saved_at: number };

/** Kho audio của chủ đề đã tải (Cache Storage, service worker phát từ đây khi mất mạng). Khớp KHO_AUDIO trong resources/sw/sw.js. */
export const KHO_AUDIO = 'lernbuch-audio-v1';
/** Bản lưu API chỉ đọc của service worker (khớp KHO_API trong sw.js): đăng xuất thì xoá. */
export const KHO_API = 'lernbuch-api-v1';

/** Như máy chủ: tối đa 50 thẻ đến hạn mỗi phiên (PhienHoc::ON_TOI_DA), "tất cả từ mới" tối đa 200 (MOI_TOI_DA). */
export const ON_TOI_DA = 50;
export const MOI_TOI_DA = 200;
/** Chế độ chỉ học thẻ có câu ví dụ chính (PhienHoc::CHE_DO_CAN_VI_DU). */
const CAN_VI_DU: readonly CheDo[] = ['sentence'];
/** Tải audio vài file một lúc. */
const TAI_CUNG_LUC = 6;

const KHOA_PHIEN = 'hien-tai';

type PhienDo = { khoa: typeof KHOA_PHIEN; pham_vi: string; phien: PhienHoc; da_cham: number[] };

// Mọi lần sửa gói và phiên dở chạy nối đuôi nhau: chấm nhanh nhiều thẻ (Ghép cặp) không ghi đè lên nhau
let hangGhi: Promise<unknown> = Promise.resolve();
function noiDuoi<T>(viec: () => Promise<T>): Promise<T> {
    const lan = hangGhi.then(viec, viec);
    hangGhi = lan.catch(() => {});
    return lan;
}

// ---------------------------------------------------------------- chủ đề đã tải

export async function cacGoi(): Promise<GoiChuDe[]> {
    try {
        const ds = ((await giaoDich<GoiChuDe[]>(BANG_CHU_DE, 'readonly', (b) => b.getAll())) ?? []) as GoiChuDe[];
        // Thứ tự tải: cũ trước (từ mới khi học tổng hợp lấy theo thứ tự này)
        return ds.sort((a, b) => a.saved_at - b.saved_at || (a.slug < b.slug ? -1 : 1));
    } catch {
        return [];
    }
}

export async function goiCua(slug: string): Promise<GoiChuDe | null> {
    try {
        return ((await giaoDich<GoiChuDe | undefined>(BANG_CHU_DE, 'readonly', (b) => b.get(slug))) as GoiChuDe | undefined) ?? null;
    } catch {
        return null;
    }
}

export const tomTatGoi = (g: GoiChuDe): TomTatGoi => ({ slug: g.slug, name: g.topic.name, so_the: g.cards.length, saved_at: g.saved_at });

async function ghiGoi(g: GoiChuDe): Promise<void> {
    await giaoDich(BANG_CHU_DE, 'readwrite', (b) => b.put(g));
}

const cungNguon = (url: string) => new URL(url, window.location.href).origin === window.location.origin;

/** Chép audio vào kho audio; trả về số file có trong kho sau khi chép. Lỗi một file thì bỏ file đó (app đọc bằng giọng trình duyệt). */
async function luuAudio(urls: string[], baoTienDo: (xong: number) => void): Promise<number> {
    if (typeof caches === 'undefined' || urls.length === 0) return 0;
    const kho = await caches.open(KHO_AUDIO);
    const con = [...urls];
    let xong = 0;
    let co = 0;
    const chay = async () => {
        for (let u = con.shift(); u !== undefined; u = con.shift()) {
            try {
                if (await kho.match(u)) co++;
                else {
                    // Kho audio khác tên miền (CDN) chưa chắc cho đọc chéo: lưu bản "opaque", service worker vẫn phát được
                    const r = await fetch(u, cungNguon(u) ? { credentials: 'same-origin' } : { mode: 'no-cors' });
                    if (r.ok || r.type === 'opaque') {
                        await kho.put(u, r);
                        co++;
                    }
                }
            } catch {
                // file lỗi hay mất mạng giữa chừng: bỏ qua
            }
            baoTienDo(++xong);
        }
    };
    await Promise.all(Array.from({ length: Math.min(TAI_CUNG_LUC, urls.length) }, chay));
    return co;
}

const audioCua = (ds: GoiChuDe[]) => new Set(ds.flatMap((g) => g.cards.map((c) => c.audio).filter((u): u is string => !!u)));

export type TienDoTai = { buoc: 'the' | 'audio'; xong: number; tong: number };

/**
 * Tải một chủ đề về máy: thẻ (máy chủ lập gói), rồi audio của từng thẻ. Tải lại thì thay gói cũ (nội dung và tiến độ mới nhất).
 * Trả về tóm tắt gói và số audio đã có trên máy.
 */
export async function taiChuDe(slug: string, baoTienDo: (t: TienDoTai) => void = () => {}): Promise<TomTatGoi & { so_audio: number }> {
    baoTienDo({ buoc: 'the', xong: 0, tong: 1 });
    const { data } = await api.lay<{ data: Omit<GoiChuDe, 'slug'> }>(`/hoc/tai/${encodeURIComponent(slug)}`);
    const goi: GoiChuDe = { ...data, slug: data.topic.slug };
    await noiDuoi(() => ghiGoi(goi));
    // Xin trình duyệt giữ dữ liệu này (không tự xoá khi máy thiếu chỗ); không được cũng không sao
    void navigator.storage?.persist?.().catch(() => false);
    const urls = [...audioCua([goi])];
    baoTienDo({ buoc: 'audio', xong: 0, tong: urls.length });
    const soAudio = await luuAudio(urls, (xong) => baoTienDo({ buoc: 'audio', xong, tong: urls.length }));
    return { ...tomTatGoi(goi), so_audio: soAudio };
}

/** Xoá một chủ đề đã tải và audio chỉ chủ đề đó dùng. */
export async function xoaChuDeTai(slug: string): Promise<void> {
    await noiDuoi(async () => {
        const g = await goiCua(slug);
        await giaoDich(BANG_CHU_DE, 'readwrite', (b) => b.delete(slug));
        if (!g || typeof caches === 'undefined') return;
        const conDung = audioCua(await cacGoi());
        const kho = await caches.open(KHO_AUDIO);
        await Promise.all([...audioCua([g])].filter((u) => !conDung.has(u)).map((u) => kho.delete(u)));
    });
}

// ---------------------------------------------------------------- tiến độ trong gói

/**
 * Một lượt chấm (có mạng hay không): đổi mức và hạn ôn của thẻ trong gói đã tải như máy chủ sẽ tính (srs.ts = LapLich.php),
 * để phiên sau lập trên máy không hỏi lại thẻ vừa ôn. Máy chủ nhận lượt thì gửi lại mức thật (capNhatTuMayChu).
 */
export function chamTrenMay(theId: number, soCham: SoCham, luc: number): Promise<void> {
    return noiDuoi(async () => {
        for (const g of await cacGoi()) {
            const c = g.cards.find((x) => x.id === theId);
            if (!c) continue;
            const kq = cham(c.box, soCham, luc, g.timezone);
            c.box = kq.muc;
            c.due_at = kq.han;
            await ghiGoi(g);
        }
    }).catch(() => {});
}

/** Máy chủ đã nhận một lô lượt chấm: lấy mức và hạn ôn máy chủ tính cho các thẻ trong gói. */
export function capNhatTuMayChu(cards: KetQuaLo['cards']): Promise<void> {
    if (cards.length === 0) return Promise.resolve();
    const theoId = new Map(cards.map((c) => [c.id, c]));
    return noiDuoi(async () => {
        for (const g of await cacGoi()) {
            let doi = false;
            for (const c of g.cards) {
                const m = theoId.get(c.id);
                if (!m) continue;
                c.box = m.box;
                c.due_at = m.due_at;
                doi = true;
            }
            if (doi) await ghiGoi(g);
        }
    }).catch(() => {});
}

// ---------------------------------------------------------------- lập phiên trên máy

/** Xen từ mới vào giữa thẻ ôn như máy chủ (PhienHoc::xenKe): cứ k thẻ ôn thì một từ mới, còn dư để cuối. */
export function xenKe<T>(on: T[], moi: T[]): T[] {
    if (on.length === 0 || moi.length === 0) return [...on, ...moi];
    const k = Math.max(1, Math.floor(on.length / moi.length));
    const ra: T[] = [];
    let j = 0;
    on.forEach((x, i) => {
        ra.push(x);
        if (j < moi.length && (i + 1) % k === 0) ra.push(moi[j++]);
    });
    return [...ra, ...moi.slice(j)];
}

export type TuyChonPhien = { cheDo: CheDo; chiOn: boolean; now: number };

/**
 * Lập phiên từ các chủ đề đã tải, như máy chủ: thẻ đến hạn trước đầu ngày mai (hạn sớm nhất trước, tối đa 50) cộng từ mới
 * theo thứ tự trong chủ đề (số từ mới mỗi phiên; "tất cả" thì hết từ mới của một chủ đề, tối đa 200), xen kẽ ôn và mới.
 * Đặt câu chỉ lấy thẻ có câu ví dụ chính. Phiên không có mã (id null): lượt chấm gửi sau, không gắn với phiên nào.
 */
export function lapPhienTrenMay(goi: GoiChuDe[], { cheDo, chiOn, now }: TuyChonPhien, chuDe: TenGon | null): PhienHoc {
    const hop = (c: TheHoc) => !CAN_VI_DU.includes(cheDo) || c.example !== null;
    const daCo = new Set<number>();
    const tatCa: TheHoc[] = [];
    for (const g of goi) {
        for (const c of g.cards) {
            if (daCo.has(c.id) || !hop(c)) continue;
            daCo.add(c.id);
            tatCa.push(c);
        }
    }

    const muiGio = goi[0]?.timezone ?? 'UTC';
    const het = dauNgayMai(now, muiGio);
    const on = tatCa
        .filter((c) => c.box > 0 && c.due_at !== null && c.due_at < het)
        .sort((a, b) => (a.due_at ?? 0) - (b.due_at ?? 0) || a.id - b.id)
        .slice(0, ON_TOI_DA);

    const moi: TheHoc[] = [];
    if (!chiOn) {
        const muon = goi[0]?.new_per_session ?? 10;
        const n = muon === 0 ? MOI_TOI_DA : muon;
        for (const g of goi) {
            const lay = g.cards.filter((c) => c.box === 0 && hop(c) && !moi.includes(c)).slice(0, n - moi.length);
            moi.push(...lay);
            if (moi.length >= n || (muon === 0 && lay.length > 0)) break;
        }
    }

    const moiDau = moi[0];
    return {
        id: null,
        mode: cheDo,
        started_at: now,
        topic: chuDe,
        new_topic: moiDau?.topic ?? null,
        counts: { review: on.length, new: moi.length },
        cards: xenKe(on, moi).map((c) => ({ ...c })),
        offline: true,
    };
}

// ---------------------------------------------------------------- phiên đang dở

/** Phạm vi của một phiên (chủ đề, chỉ ôn, chế độ): chỉ học tiếp phiên dở khi mở lại đúng phạm vi đó. */
export const phamVi = (chuDe: string | null, chiOn: boolean, cheDo: CheDo) => `${chuDe ?? '*'}|${chiOn ? 'on' : 'moi'}|${cheDo}`;

/** Máy chủ vừa lập phiên: giữ trên máy (thay phiên dở trước đó). */
export function giuPhien(phien: PhienHoc, phamViPhien: string): Promise<void> {
    if (phien.id === null || phien.cards.length === 0) return Promise.resolve();
    const dong: PhienDo = { khoa: KHOA_PHIEN, pham_vi: phamViPhien, phien, da_cham: [] };
    return noiDuoi(() => giaoDich(BANG_PHIEN, 'readwrite', (b) => b.put(dong)).then(() => {})).catch(() => {});
}

async function docPhienDo(): Promise<PhienDo | null> {
    return ((await giaoDich<PhienDo | undefined>(BANG_PHIEN, 'readonly', (b) => b.get(KHOA_PHIEN))) as PhienDo | undefined) ?? null;
}

/** Thẻ đã chấm lượt đầu trong phiên dở: học tiếp thì không hỏi lại. */
export function danhDauDaCham(phienId: number | null, theId: number): Promise<void> {
    if (phienId === null) return Promise.resolve();
    return noiDuoi(async () => {
        const d = await docPhienDo();
        if (!d || d.phien.id !== phienId || d.da_cham.includes(theId)) return;
        d.da_cham.push(theId);
        await giaoDich(BANG_PHIEN, 'readwrite', (b) => b.put(d));
    }).catch(() => {});
}

/** Xong phiên (hoặc bỏ hẳn): không giữ nữa. */
export function boPhienDo(phienId: number | null): Promise<void> {
    if (phienId === null) return Promise.resolve();
    return noiDuoi(async () => {
        const d = await docPhienDo();
        if (d && d.phien.id === phienId) await giaoDich(BANG_PHIEN, 'readwrite', (b) => b.delete(KHOA_PHIEN));
    }).catch(() => {});
}

/** Phiên dở cùng phạm vi, lập trong hôm nay (theo múi giờ người học), còn thẻ chưa chấm: phần còn lại, giữ mã phiên. */
export async function phienDoCon(phamViPhien: string, now: number, muiGio: string): Promise<PhienHoc | null> {
    const d = await docPhienDo().catch(() => null);
    if (!d || d.pham_vi !== phamViPhien || d.phien.started_at < dauNgay(now, muiGio)) return null;
    const daCham = new Set(d.da_cham);
    const con = d.phien.cards.filter((c) => !daCham.has(c.id));
    if (con.length === 0) return null;
    return {
        ...d.phien,
        cards: con,
        counts: { review: con.filter((c) => c.box > 0).length, new: con.filter((c) => c.box === 0).length },
        offline: true,
    };
}

// ---------------------------------------------------------------- khi không gọi được máy chủ

export type PhienMatMang = { phien: PhienHoc; nguon: 'phien-do' | 'chu-de-tai' };

/**
 * Không lập được phiên trên máy chủ (mất mạng): học tiếp phiên dở cùng phạm vi nếu có; không thì lập phiên từ chủ đề đã tải
 * (học riêng một chủ đề: chỉ chủ đề đó; ôn chung: mọi chủ đề đã tải). Không có gì để học trên máy thì null.
 */
export async function phienKhiMatMang(chuDe: string | null, tuyChon: TuyChonPhien, muiGio: string | null): Promise<PhienMatMang | null> {
    const goi = chuDe !== null ? [await goiCua(chuDe)].filter((g): g is GoiChuDe => g !== null) : await cacGoi();
    const gio = muiGio ?? goi[0]?.timezone ?? 'UTC';
    const doDang = await phienDoCon(phamVi(chuDe, tuyChon.chiOn, tuyChon.cheDo), tuyChon.now, gio);
    if (doDang) return { phien: doDang, nguon: 'phien-do' };
    if (goi.length === 0) return null;
    const tenChuDe = chuDe !== null ? { slug: goi[0].topic.slug, name: goi[0].topic.name } : null;
    return { phien: lapPhienTrenMay(goi, tuyChon, tenChuDe), nguon: 'chu-de-tai' };
}

// ---------------------------------------------------------------- mở app, đăng xuất

let daBatDau = false;

/** Mở app: mỗi lô lượt chấm máy chủ nhận xong thì cập nhật mức của thẻ trong các chủ đề đã tải. Gọi một lần. */
export function batDauOffline(): void {
    if (daBatDau) return;
    daBatDau = true;
    khiNhanLo((kq) => void capNhatTuMayChu(kq.cards));
}

/**
 * Trước khi đăng xuất: gửi nốt lượt chấm (chờ tối đa vài giây), rồi xoá mọi thứ của người này trên máy (lượt còn chờ,
 * chủ đề đã tải, phiên dở, bản lưu API và audio của service worker), để người đăng nhập sau trên máy này không thấy, không gửi nhầm.
 */
export async function donMay(choGuiMs = 3000): Promise<void> {
    await Promise.race([guiDi(), new Promise((xong) => setTimeout(xong, choGuiMs))]);
    await boHetLuot().catch(() => {});
    await noiDuoi(async () => {
        await giaoDich(BANG_CHU_DE, 'readwrite', (b) => b.clear());
        await giaoDich(BANG_PHIEN, 'readwrite', (b) => b.clear());
    }).catch(() => {});
    if (typeof caches !== 'undefined') await Promise.all([caches.delete(KHO_API), caches.delete(KHO_AUDIO)]).catch(() => {});
}

/** Chỉ dùng trong test. */
export function datLaiOfflineChoTest(): void {
    daBatDau = false;
    hangGhi = Promise.resolve();
}
