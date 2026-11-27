import { createContext, useContext, useEffect, useMemo, useReducer, type ReactNode } from 'react';

export type Theme = 'light' | 'dark';
export type FontSize = 'sm' | 'md' | 'lg';
export type Season = 'auto' | 'spring' | 'summer' | 'autumn' | 'winter';
export type Lang = 'vi' | 'en' | 'zh';
export type NewCount = 10 | 20 | 30 | 50 | 100 | 'all';

export type Settings = {
    theme: Theme;
    fontSize: FontSize;
    season: Season;
    langs: Record<Lang, boolean>;
    newPerSession: NewCount;
};

// Tiến độ học, xu, sổ tay nằm ở máy chủ; ở đây chỉ còn cài đặt (đồng bộ theo tài khoản trong taiKhoan.tsx).
type State = {
    settings: Settings;
};

type Action = { type: 'settings'; patch: Partial<Settings> };

export const SETTINGS_KEY = 'lernbuch.caidat.v1';

export const defaultSettings: Settings = {
    theme: 'light',
    fontSize: 'md',
    season: 'auto',
    langs: { vi: true, en: true, zh: true },
    newPerSession: 10,
};

const THEMES: readonly Theme[] = ['light', 'dark'];
const FONT_SIZES: readonly FontSize[] = ['sm', 'md', 'lg'];
const SEASONS: readonly Season[] = ['auto', 'spring', 'summer', 'autumn', 'winter'];
export const NEW_COUNTS: readonly NewCount[] = [10, 20, 30, 50, 100, 'all'];

const pick = <T,>(v: unknown, allowed: readonly T[], fallback: T): T => ((allowed as readonly unknown[]).includes(v) ? (v as T) : fallback);

/** Lấy cài đặt đã lưu, bỏ mọi giá trị lạ (bản cũ, sửa tay, hỏng) về mặc định. */
export function sanitizeSettings(raw: unknown): Settings {
    const s = raw !== null && typeof raw === 'object' ? (raw as Record<string, unknown>) : {};
    const langs = s.langs !== null && typeof s.langs === 'object' ? (s.langs as Record<string, unknown>) : {};
    const lang = (k: Lang) => (typeof langs[k] === 'boolean' ? (langs[k] as boolean) : defaultSettings.langs[k]);
    return {
        theme: pick(s.theme, THEMES, defaultSettings.theme),
        fontSize: pick(s.fontSize, FONT_SIZES, defaultSettings.fontSize),
        season: pick(s.season, SEASONS, defaultSettings.season),
        langs: { vi: lang('vi'), en: lang('en'), zh: lang('zh') },
        newPerSession: pick(s.newPerSession, NEW_COUNTS, defaultSettings.newPerSession),
    };
}

// Cài đặt nhớ cả trên trình duyệt, để mở app là có giao diện đúng ngay trước khi tải xong tài khoản.
export function loadSettings(): Settings {
    try {
        const raw = window.localStorage.getItem(SETTINGS_KEY);
        return raw ? sanitizeSettings(JSON.parse(raw)) : defaultSettings;
    } catch {
        return defaultSettings;
    }
}

/** Trình duyệt có cho lưu không (chế độ riêng tư hoặc chặn cookie thì không). */
export function canPersist(): boolean {
    try {
        const key = 'lernbuch.thu-luu';
        window.localStorage.setItem(key, '1');
        window.localStorage.removeItem(key);
        return true;
    } catch {
        return false;
    }
}

function saveSettings(s: Settings) {
    try {
        window.localStorage.setItem(SETTINGS_KEY, JSON.stringify(s));
    } catch {
        // chế độ riêng tư hoặc hết chỗ: bỏ qua, chỉ mất phần nhớ cài đặt
    }
}

export function reducer(state: State, action: Action): State {
    switch (action.type) {
        case 'settings':
            return { ...state, settings: { ...state.settings, ...action.patch } };
        default:
            return state;
    }
}

export function initialState(settings: Settings = defaultSettings): State {
    return { settings };
}

export function seasonOf(date: Date): Exclude<Season, 'auto'> {
    const m = date.getMonth() + 1;
    if (m >= 3 && m <= 5) return 'spring';
    if (m >= 6 && m <= 8) return 'summer';
    if (m >= 9 && m <= 11) return 'autumn';
    return 'winter';
}

type Store = State & { dispatch: (a: Action) => void; season: Exclude<Season, 'auto'> };

const StoreContext = createContext<Store | null>(null);

export function StoreProvider({ children, initial }: { children: ReactNode; initial?: State }) {
    const [state, dispatch] = useReducer(reducer, initial, (init) => init ?? initialState(loadSettings()));
    const season = state.settings.season === 'auto' ? seasonOf(new Date()) : state.settings.season;

    useEffect(() => {
        const html = document.documentElement;
        html.dataset.theme = state.settings.theme;
        html.dataset.fontSize = state.settings.fontSize;
        html.dataset.season = season;
        saveSettings(state.settings);
    }, [state.settings, season]);

    const value = useMemo(() => ({ ...state, dispatch, season }), [state, season]);
    return <StoreContext.Provider value={value}>{children}</StoreContext.Provider>;
}

export function useStore(): Store {
    const store = useContext(StoreContext);
    if (!store) throw new Error('useStore cần nằm trong StoreProvider');
    return store;
}
