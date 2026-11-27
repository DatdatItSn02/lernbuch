// Ngữ pháp, bài tập, động từ theo Lernbuch (exercises.js): kiểu dữ liệu của API, chấm câu, gửi kết quả.
// Chấm câu chạy ngay trên máy; máy chủ chấm lại theo cùng quy tắc (bảng ca thử chung tests/chung/cham-bai-tap.json).
import { api, LoiApi } from './api';
import type { DongTu, NgonNguNghia, TenGon, TheTomTat } from './noiDung';
import { fold } from './luyen';
import { quenDaTai } from './useApi';

export type Diem = 0 | 1 | 2;
export type LoaiCau = 'fill' | 'mc' | 'order' | 'fix';
export type Nghia = Partial<Record<NgonNguNghia, string>>;

// ---------------------------------------------------------------- ngữ pháp

export type Chuong = { code: string; level: string | null; no: number; name: string };
export type BaiNguPhapTomTat = { slug: string; title: string; level: string | null; chapter: string | null; exercises: number };
export type DanhSachNguPhap = { chapters: Chuong[]; lessons: BaiNguPhapTomTat[] };

/** Ô bảng: [chữ, số cột gộp, kiểu] với kiểu 0 ô thường, 1 ô tiêu đề cột, 2 ô tiêu đề hàng. */
export type OBang = [string, number, number];

/** Khối nội dung bài ngữ pháp (Lernbuch: renderBlocks). L: cùng một ý theo nhiều thứ tiếng. */
export type Khoi =
    | { t: 'h2' | 'h3' | 'p' | 'li' | 'tip' | 'recap' | 'note'; x: string }
    | { t: 'kv'; k?: string; x: string }
    | { t: 'fm'; x: string; L?: Nghia }
    | { t: 'tipl'; L?: Nghia }
    | { t: 'ex'; x: string; L?: Nghia; tr?: string; who?: string }
    | { t: 'tbl'; rows: OBang[][] };

export type BaiNguPhap = {
    slug: string;
    title: string;
    level: string | null;
    chapter: Chuong | null;
    blocks: Khoi[];
    sets: { slug: string; title: string; count: number }[];
    prev: { slug: string; title: string } | null;
    next: { slug: string; title: string } | null;
};

// ---------------------------------------------------------------- bài tập

export type BoBai = {
    slug: string;
    title: string;
    /** level: theo cấp; review: Ôn tập; plural: Số nhiều. */
    section: 'level' | 'review' | 'plural';
    level: string | null;
    lesson: string | null;
    /** Nhóm hiện trên trang (vd "Chương 1 · Artikel & Genus"); bộ có nhóm làm cả bộ theo thứ tự. */
    group: string | null;
    note: string | null;
    count: number;
    /** Câu đã thuộc (đúng 2 lần liên tiếp) và câu đang sai. */
    done: number;
    weak: number;
};

export type DanhSachBaiTap = { sets: BoBai[]; lessons: { slug: string; title: string; level: string | null; chapter: string | null }[]; chapters: Chuong[] };

export type CauBaiTap = {
    id: number;
    kind: LoaiCau;
    /** Đề; "____" là chỗ trống, **chữ** là chữ đậm. */
    prompt: string;
    answers: string[];
    options: string[] | null;
    hint: string | null;
    explanation: string | null;
    label: string | null;
    instr: string | null;
    case_sensitive: boolean;
    /** Từ cho sẵn để bấm chèn vào ô trả lời. */
    bank: string[] | null;
    /** Sắp xếp: các mảnh câu và phần đầu câu cố định. */
    tokens: string[] | null;
    lead: string | null;
    /** Sửa lỗi: câu có chứa đúng phần đã sửa cũng tính là đúng. */
    frag: string[];
    set: { slug: string; title: string; group: string | null; note: string | null };
};

export type CauChiaDongTu = {
    sig: string;
    kind: 'fill';
    prompt: string;
    answers: string[];
    explanation: string;
    meanings: Nghia;
    verb: string;
    audio: string | null;
};

export type TheDienCau = TheTomTat & {
    cloze: string;
    answer: string;
    case_sensitive: boolean;
    /** Câu đầy đủ kèm bản dịch (hiện sau khi trả lời); null khi không tìm thấy. */
    sentence: { sentence: string; translations: Nghia } | null;
    topic: TenGon | null;
    box: number;
};

export type DongTuBang = DongTu & { level: string | null; meanings: Nghia; card_id: number | null };

/** Loại lượt làm bài: theo bộ, Luyện nhanh, Số nhiều, Ôn câu sai, Chia động từ (máy chủ: BaiTap::LOAI). */
export type LoaiLuot = 'set' | 'quick' | 'plural' | 'wrong_review' | 'conjugate';
export type CachCham = 'instant' | 'sheet';

/** Xu mỗi câu đúng (Lernbuch EX_COINS; máy chủ: BaiTap::XU_CAU, XU_DONG_TU). */
export const XU_CAU = 5;
export const XU_DONG_TU = 10;

// ---------------------------------------------------------------- chấm câu

type CauDeCham = Pick<CauBaiTap, 'kind' | 'prompt' | 'answers' | 'options' | 'case_sensitive' | 'frag'>;

/** Bỏ khoảng trắng thừa và dấu câu cuối (. ! ? , ; :), giữ hoa thường. */
export const strip = (s: string) =>
    s
        .normalize('NFC')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/[.!?,;:]+$/, '');

export const laTracNghiem = (c: Pick<CauBaiTap, 'kind' | 'options'>) => c.kind === 'mc' && (c.options?.length ?? 0) > 1;

/**
 * Chấm như Lernbuch: trắc nghiệm đúng nguyên chữ của lựa chọn; sắp xếp so khi bỏ dấu, hoa thường; câu điền khớp đúng chữ
 * (bỏ dấu câu cuối) được 2, khớp khi bỏ dấu và hoa thường được 2 (câu phân biệt hoa thường thì 1); sửa lỗi để nguyên câu
 * sai thì 0, viết đúng cả câu hoặc câu có chứa đúng phần đã sửa thì 2.
 */
export function chamCau(c: CauDeCham, traLoi: string): Diem {
    if (!traLoi.trim()) return 0;
    if (laTracNghiem(c)) return c.answers.includes(traLoi) ? 2 : 0;
    if (c.kind === 'order') {
        const f = fold(traLoi);
        return f && c.answers.some((a) => fold(a) === f) ? 2 : 0;
    }
    const v = strip(traLoi);
    if (c.kind === 'fix') {
        const fv = fold(v);
        if (!fv || fv === fold(c.prompt)) return 0;
        if (c.answers.some((a) => fold(a) === fv)) return 2;
        return c.frag.some((f) => ` ${fv} `.includes(` ${fold(f)} `)) ? 2 : 0;
    }
    if (c.answers.some((a) => strip(a) === v)) return 2;
    const fv = fold(v);
    if (fv && c.answers.some((a) => fold(a) === fv)) return c.case_sensitive ? 1 : 2;
    return 0;
}

/** Đề có chữ đậm (**…**) và chỗ trống (hai gạch dưới trở lên): tách thành các mảnh để vẽ. */
export type ManhDe = { kieu: 'chu' | 'dam' | 'trong'; chu: string };

export function tachDe(de: string): ManhDe[] {
    const ra: ManhDe[] = [];
    de.split(/_{2,}/).forEach((doan, i) => {
        if (i > 0) ra.push({ kieu: 'trong', chu: '' });
        for (const p of doan.split(/(\*\*[^*]+\*\*)/)) {
            if (!p) continue;
            ra.push(/^\*\*[^*]+\*\*$/.test(p) ? { kieu: 'dam', chu: p.slice(2, -2) } : { kieu: 'chu', chu: p });
        }
    });
    return ra;
}

/** Đề bỏ dấu chữ đậm (câu sửa lỗi điền sẵn vào ô). */
export const deTron = (de: string) => de.replace(/\*\*/g, '');

// ---------------------------------------------------------------- gửi kết quả

export type KetQuaGui = {
    uid: string;
    mode: LoaiLuot;
    grading: CachCham;
    set: string | null;
    started_at_ms: number;
    finished_at_ms: number;
    items: ({ id: number; answer: string } | { sig: string; answer: string })[];
};

export type KetQuaNhan = {
    duplicate: boolean;
    coins: number;
    correct: number;
    total: number;
    too_fast: boolean;
    items: ({ id: number; score: Diem } | { sig: string; score: Diem })[];
    today: { coins: number; coins_today: number; exercises_today: number };
};

/** Kết quả chưa tới máy chủ (mất mạng, đóng tab): nằm chờ trên máy, lần sau mở app hay có mạng lại thì gửi tiếp. */
export const CHO_KET_QUA = 'lernbuch.baitap.cho';

function docCho(): KetQuaGui[] {
    try {
        const v: unknown = JSON.parse(window.localStorage.getItem(CHO_KET_QUA) ?? '[]');
        return Array.isArray(v) ? (v as KetQuaGui[]) : [];
    } catch {
        return [];
    }
}

function ghiCho(ds: KetQuaGui[]) {
    try {
        if (ds.length > 0) window.localStorage.setItem(CHO_KET_QUA, JSON.stringify(ds));
        else window.localStorage.removeItem(CHO_KET_QUA);
    } catch {
        // trình duyệt chặn lưu: chỉ mất phần gửi lại lần sau
    }
}

const boCho = (uid: string) => ghiCho(docCho().filter((k) => k.uid !== uid));

/** Máy chủ từ chối hẳn (sai dạng, câu đã bị gỡ): gửi lại cũng vậy, bỏ khỏi hàng chờ. */
const tuChoiHan = (e: unknown) => e instanceof LoiApi && (e.trangThai === 404 || e.trangThai === 422);

/** Gửi kết quả một lượt; chưa gửi được (mất mạng, máy chủ lỗi tạm) thì trả null và giữ lại để gửi sau (máy chủ bỏ lượt trùng mã). */
export async function guiKetQua(kq: KetQuaGui): Promise<KetQuaNhan | null> {
    ghiCho([...docCho().filter((k) => k.uid !== kq.uid), kq]);
    try {
        const { data } = await api.gui<{ data: KetQuaNhan }>('/bai-tap/ket-qua', kq);
        boCho(kq.uid);
        quenDaTai();
        return data;
    } catch (e) {
        if (!tuChoiHan(e)) return null;
        boCho(kq.uid);
        throw e;
    }
}

/** Gửi lại các kết quả còn chờ, theo thứ tự; dừng ở lần hỏng vì mạng để thử lại sau. */
export async function guiLaiKetQua(): Promise<void> {
    for (const kq of docCho()) {
        try {
            await api.gui('/bai-tap/ket-qua', kq);
            boCho(kq.uid);
            quenDaTai();
        } catch (e) {
            if (!tuChoiHan(e)) return;
            boCho(kq.uid);
        }
    }
}

export const soKetQuaCho = () => docCho().length;

// ---------------------------------------------------------------- cách chấm (nhớ trên máy)

const CACH_CHAM_KEY = 'lernbuch.baitap.cachcham';

export function docCachCham(): CachCham {
    try {
        return window.localStorage.getItem(CACH_CHAM_KEY) === 'sheet' ? 'sheet' : 'instant';
    } catch {
        return 'instant';
    }
}

export function ghiCachCham(c: CachCham): void {
    try {
        window.localStorage.setItem(CACH_CHAM_KEY, c);
    } catch {
        // trình duyệt chặn lưu: lần sau về mặc định
    }
}
