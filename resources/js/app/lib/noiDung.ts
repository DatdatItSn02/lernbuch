// Kiểu dữ liệu và đường dẫn của API nội dung (/api/v1): loại thẻ, cây chủ đề, trang chủ đề, thẻ từ, sổ tay, tra từ.
import { so, t, type Khoa } from '../i18n';

export type MaoTu = 'der' | 'die' | 'das';
export type NgonNguNghia = 'vi' | 'en' | 'zh';

/** Tóm tắt thẻ trong danh sách. word = mặt trước bỏ mạo từ đứng đầu (để tô màu mạo từ riêng). */
export type TheTomTat = {
    id: number;
    collection: string;
    front: string;
    article: MaoTu | null;
    word: string;
    gender: 'm' | 'f' | 'n' | null;
    pos: string | null;
    /** Số nhiều, dạng chia hay giới từ đi kèm; "–" là không có (vd danh từ không có số nhiều). */
    forms: string | null;
    /** "A1", "A1–B2"; null là chưa xếp cấp. */
    level: string | null;
    image: string | null;
    meanings: Partial<Record<NgonNguNghia, string>>;
    starred: boolean;
};

export type DemCap = { level: number; count: number };
export type DemLoaiTu = { pos: string; count: number };
export type TenGon = { slug: string; name: string };

export type ChuDeNho = TenGon & { icon: string | null; card_count: number };
export type NhomChuDe = TenGon & { topic_count: number; card_count: number; parents: (TenGon & { topics: ChuDeNho[] })[] };

export type CayChuDe = {
    collection: { code: string; name: string };
    topic_count: number;
    card_count: number;
    levels: DemCap[];
    pos: DemLoaiTu[];
    groups: NhomChuDe[];
};

export type BoThe = { code: string; name: string; card_count: number };

export type TrangChuDe = {
    data: {
        topic: TenGon & { name_en: string | null; icon: string | null; collection: { code: string; name: string }; card_count: number };
        parent: TenGon | null;
        group: TenGon | null;
        levels: DemCap[];
        pos: DemLoaiTu[];
        cards: TheTomTat[];
    };
    meta: PhanTrang;
};

export type PhanTrang = { page: number; per_page: number; total: number; pages: number };

export type DongTu = {
    infinitive: string;
    auxiliary: string | null;
    is_separable: boolean;
    prefix: string | null;
    form_du: string | null;
    form_er: string | null;
    preterite: string | null;
    perfect: string | null;
    /** pres, prat, perf: 6 ngôi (ich, du, er/sie/es, wir, ihr, sie/Sie); imp: 3 dạng mệnh lệnh. */
    conjugation: Partial<Record<'pres' | 'prat' | 'perf' | 'imp' | 'more', string[]>> & { example?: string } | null;
};

export type SoTay = { starred: boolean; note: string | null };

export type TheChiTiet = TheTomTat & {
    collection_name: string;
    note: string | null;
    tip: string | null;
    lektion: string | null;
    extra: Record<string, unknown> | null;
    examples: { sentence: string; translations: Partial<Record<NgonNguNghia, string>> }[];
    verb: DongTu | null;
    topic: TenGon | null;
    nav: { index: number; total: number; prev: { id: number; front: string } | null; next: { id: number; front: string } | null } | null;
    bookmark: SoTay;
};

export type KetQuaTra = TheTomTat & { match: 'exact' | 'form' | 'close' | 'contains' };
export type MucLichSu = { id: number; term: string; card: { id: number; front: string } | null; searched_at: number };
export type TheSoTay = TheTomTat & { bookmark: SoTay & { updated_at: number } };
export type DemSoTay = { all: number; starred: number; noted: number };

/** Tên cấp: 1–6 là A1–C2, 0 là chưa xếp cấp. */
export const TEN_CAP: Record<number, string> = { 1: 'A1', 2: 'A2', 3: 'B1', 4: 'B2', 5: 'C1', 6: 'C2' };
export const tenCap = (c: number) => TEN_CAP[c] ?? t('noiDung.chuaXepCap');

const LOAI_TU: Record<string, Khoa> = {
    noun: 'loaiTu.danhTu',
    verb: 'loaiTu.dongTu',
    sepverb: 'loaiTu.dongTuTach',
    adj: 'loaiTu.tinhTu',
    adj_adv: 'loaiTu.tinhTuTrangTu',
    adv: 'loaiTu.trangTu',
    phrase: 'loaiTu.cumCoDinh',
    pattern: 'loaiTu.mauCau',
    qa: 'loaiTu.hoiDap',
    slang: 'loaiTu.tiengLong',
    sentence: 'loaiTu.cau',
    expr: 'loaiTu.cachDienDat',
    other: 'loaiTu.khac',
};

/** Tên loại từ viết thường ("danh từ"); mã lạ thì hiện "khác". */
export const tenLoaiTu = (pos: string | null) => (pos ? t(LOAI_TU[pos] ?? 'loaiTu.khac') : '');

/** Tên trang danh sách chứa một loại thẻ: Từ vựng theo chủ đề, Giao tiếp, còn lại ở Cấp độ. */
export function trangCuaBo(bo: string): { to: string; khoa: Khoa } {
    if (bo === 'vocab') return { to: '/chu-de', khoa: 'khung.chuDe' };
    if (bo === 'comm') return { to: '/giao-tiep', khoa: 'noiDung.giaoTiep' };
    return { to: `/cap-do?bo=${bo}`, khoa: 'noiDung.capDo' };
}

/** Đơn vị đếm: câu cho Giao tiếp, Mẫu câu; từ cho các loại khác. */
export const demThe = (bo: string, n: number) => t(bo === 'comm' || bo === 'pattern' ? 'noiDung.soCau' : 'noiDung.soTu', { n: so(n) });

/** ?cap=1,2,0 → [1, 2, 0]; bỏ giá trị lạ. */
export function docCap(chu: string | null): number[] {
    if (!chu) return [];
    return [...new Set(chu.split(',').map((c) => Number(c.trim())).filter((c) => Number.isInteger(c) && c >= 0 && c <= 6))];
}

/** Ghép tham số truy vấn, bỏ giá trị rỗng. */
export function truyVan(thamSo: Record<string, string | number | null | undefined>): string {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(thamSo)) {
        if (v !== null && v !== undefined && v !== '') q.set(k, String(v));
    }
    const s = q.toString();
    return s ? `?${s}` : '';
}
