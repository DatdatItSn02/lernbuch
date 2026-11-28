import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { TrangThaiAi } from '../lib/ai';
import { api } from '../lib/api';
import { sanitizeSettings, useStore, type Season, type Settings } from './store';

export type MucTieu = 'study_abroad' | 'vocational' | 'work' | 'exam' | 'travel' | 'other';
export const MUC_TIEU: readonly MucTieu[] = ['study_abroad', 'vocational', 'work', 'exam', 'travel', 'other'];

export type CaiDatApi = {
    new_per_session: number;
    daily_goal: number;
    theme: 'light' | 'dark';
    font_size: 'sm' | 'md' | 'lg';
    season: Season;
    meaning_langs: string[];
    /** AI tự giải thích khi trả lời sai (mỗi lần sai tốn một lượt AI); mặc định tắt. */
    ai_auto_explain?: boolean;
};

export type Toi = {
    id: string;
    name: string;
    email: string;
    ui_locale: string;
    timezone: string;
    created_at: number | null;
    course: { slug: string; level_min: number; level_max: number; goal: MucTieu | null; level: number | null } | null;
    settings: CaiDatApi;
    /** AI có mở cho người này không và còn thiếu bước nào (khai ngày sinh, đồng ý); xem lib/ai.ts. */
    ai: TrangThaiAi;
    /** Chế độ xem thử (tạm thời): đang dùng tài khoản khách chung, không có phần tài khoản và đăng xuất. */
    guest?: boolean;
};

export type HoSoMoi = Partial<{ name: string; timezone: string; goal: MucTieu | null; level: number | null }>;

/** Cài đặt của máy chủ (snake_case, 0 từ mới = tất cả) sang cài đặt của app; giá trị lạ về mặc định. */
export function tuApi(c: CaiDatApi): Settings {
    const nghia = Array.isArray(c.meaning_langs) ? c.meaning_langs : [];
    return sanitizeSettings({
        theme: c.theme,
        fontSize: c.font_size,
        season: c.season,
        langs: { vi: nghia.includes('vi'), en: nghia.includes('en'), zh: nghia.includes('zh') },
        newPerSession: c.new_per_session === 0 ? 'all' : c.new_per_session,
    });
}

/** Phần cài đặt đã đổi, viết theo dạng của API. */
export function veApi(s: Partial<Settings>): Partial<CaiDatApi> {
    const kq: Partial<CaiDatApi> = {};
    if (s.theme) kq.theme = s.theme;
    if (s.fontSize) kq.font_size = s.fontSize;
    if (s.season) kq.season = s.season;
    if (s.newPerSession !== undefined) kq.new_per_session = s.newPerSession === 'all' ? 0 : s.newPerSession;
    if (s.langs) kq.meaning_langs = (['vi', 'en', 'zh'] as const).filter((l) => s.langs![l]);
    return kq;
}

/** Những khoá khác nhau giữa hai bản cài đặt; giống hết thì null. */
export function khacNhau(truoc: Settings, sau: Settings): Partial<Settings> | null {
    const kq: Partial<Settings> = {};
    if (truoc.theme !== sau.theme) kq.theme = sau.theme;
    if (truoc.fontSize !== sau.fontSize) kq.fontSize = sau.fontSize;
    if (truoc.season !== sau.season) kq.season = sau.season;
    if (truoc.newPerSession !== sau.newPerSession) kq.newPerSession = sau.newPerSession;
    if (truoc.langs.vi !== sau.langs.vi || truoc.langs.en !== sau.langs.en || truoc.langs.zh !== sau.langs.zh) kq.langs = sau.langs;
    return Object.keys(kq).length > 0 ? kq : null;
}

export type TrangThaiLuu = 'yen' | 'dang-luu' | 'da-luu' | 'loi';

type TaiKhoan = {
    /** khong-co: không có tài khoản (test màn hình riêng lẻ); dang-tai; xong; loi: không tải được (mất mạng…). */
    trangThai: 'khong-co' | 'dang-tai' | 'xong' | 'loi';
    toi: Toi | null;
    luu: TrangThaiLuu;
    capNhatHoSo: (thayDoi: HoSoMoi) => Promise<void>;
    /** Nhận hồ sơ mới mà máy chủ vừa trả (khai ngày sinh, đồng ý AI). */
    nhanToi: (toi: Toi) => void;
    /** Tải lại hồ sơ (vd AI vừa bị tắt hay cần đồng ý lại), lỗi thì giữ bản đang có. */
    taiLaiToi: () => Promise<void>;
};

const MAC_DINH: TaiKhoan = { trangThai: 'khong-co', toi: null, luu: 'yen', capNhatHoSo: async () => {}, nhanToi: () => {}, taiLaiToi: async () => {} };
const TaiKhoanContext = createContext<TaiKhoan>(MAC_DINH);

/** Chờ người dùng ngừng bấm một chút rồi mới gửi, để đổi liền mấy ô chỉ gửi một lần. */
export const CHO_GUI_MS = 400;

/** Phần cài đặt đã đổi trên máy này mà máy chủ chưa nhận (tải lại trang, đóng tab hay mất mạng giữa chừng thì lần sau gửi tiếp). */
export const CHO_KEY = 'lernbuch.caidat.cho';

function docCho(): Partial<Settings> | null {
    try {
        const raw = window.localStorage.getItem(CHO_KEY);
        const v: unknown = raw ? JSON.parse(raw) : null;
        return v !== null && typeof v === 'object' ? (v as Partial<Settings>) : null;
    } catch {
        return null;
    }
}

function ghiCho(thayDoi: Partial<Settings> | null) {
    try {
        if (thayDoi) window.localStorage.setItem(CHO_KEY, JSON.stringify(thayDoi));
        else window.localStorage.removeItem(CHO_KEY);
    } catch {
        // trình duyệt chặn lưu: chỉ mất phần gửi lại lần sau
    }
}

/**
 * Tài khoản đang đăng nhập: tải hồ sơ và cài đặt từ máy chủ khi mở app, áp cài đặt đó (máy nào cũng giống nhau),
 * rồi mỗi lần đổi cài đặt thì gửi phần đã đổi lên máy chủ. Phần chưa gửi được nằm chờ trên máy và thắng bản của máy chủ.
 */
export function TaiKhoanProvider({ children }: { children: ReactNode }) {
    const { settings, dispatch } = useStore();
    const [toi, setToi] = useState<Toi | null>(null);
    const [trangThai, setTrangThai] = useState<TaiKhoan['trangThai']>('dang-tai');
    const [luu, setLuu] = useState<TrangThaiLuu>('yen');
    // Cài đặt máy chủ đang có (lần tải hoặc lần lưu gần nhất); null khi chưa tải xong
    const daDongBo = useRef<Settings | null>(null);
    const moiNhat = useRef(settings);
    // Cài đặt lúc mở app: người dùng đổi gì trước khi hồ sơ tải xong thì giữ lựa chọn đó (rồi gửi lên), không để bản máy chủ đè
    const lucMo = useRef(settings);

    useEffect(() => {
        moiNhat.current = settings;
    }, [settings]);

    useEffect(() => {
        let huy = false;
        api.lay<{ data: Toi }>('/toi')
            .then(({ data }) => {
                if (huy) return;
                const mayChu = tuApi(data.settings);
                const cho = docCho();
                const doiTruocKhiTai = khacNhau(lucMo.current, moiNhat.current);
                daDongBo.current = mayChu;
                dispatch({ type: 'settings', patch: cho || doiTruocKhiTai ? sanitizeSettings({ ...mayChu, ...cho, ...doiTruocKhiTai }) : mayChu });
                setToi(data);
                setTrangThai('xong');
            })
            .catch(() => {
                if (!huy) setTrangThai('loi');
            });
        return () => {
            huy = true;
        };
    }, [dispatch]);

    useEffect(() => {
        const truoc = daDongBo.current;
        const thayDoi = truoc ? khacNhau(truoc, settings) : null;
        if (!truoc) return;
        ghiCho(thayDoi);
        if (!thayDoi) return;
        const hen = window.setTimeout(() => {
            setLuu('dang-luu');
            api.sua<{ data: CaiDatApi }>('/toi/cai-dat', veApi(thayDoi))
                .then(({ data }) => {
                    daDongBo.current = tuApi(data);
                    ghiCho(khacNhau(daDongBo.current, moiNhat.current));
                    setLuu('da-luu');
                })
                .catch(() => setLuu('loi'));
        }, CHO_GUI_MS);
        return () => window.clearTimeout(hen);
    }, [settings]);

    // Đóng tab hay chuyển trang ngay sau khi đổi: gửi luôn, không chờ (lần mở sau vẫn gửi lại phần chờ nếu lần này hỏng)
    useEffect(() => {
        const guiNgay = () => {
            const thayDoi = daDongBo.current ? khacNhau(daDongBo.current, moiNhat.current) : null;
            if (thayDoi) api.guiKhiRoiTrang('/toi/cai-dat', veApi(thayDoi));
        };
        window.addEventListener('pagehide', guiNgay);
        return () => window.removeEventListener('pagehide', guiNgay);
    }, []);

    const capNhatHoSo = useCallback(async (thayDoi: HoSoMoi) => {
        const { data } = await api.sua<{ data: Toi }>('/toi/ho-so', thayDoi);
        setToi(data);
    }, []);

    const nhanToi = useCallback((moi: Toi) => setToi(moi), []);
    const taiLaiToi = useCallback(async () => {
        try {
            const { data } = await api.lay<{ data: Toi }>('/toi');
            setToi(data);
        } catch {
            // giữ bản đang có
        }
    }, []);

    const value = useMemo(() => ({ trangThai, toi, luu, capNhatHoSo, nhanToi, taiLaiToi }), [trangThai, toi, luu, capNhatHoSo, nhanToi, taiLaiToi]);
    return <TaiKhoanContext.Provider value={value}>{children}</TaiKhoanContext.Provider>;
}

export function useTaiKhoan(): TaiKhoan {
    return useContext(TaiKhoanContext);
}
