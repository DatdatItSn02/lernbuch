import { Download } from 'lucide-react';
import { useId, useState, type FormEvent } from 'react';
import { chuLoi } from '../components/ai/CongAi';
import { Choice } from '../components/Choice';
import { MayDangNhap } from '../components/MayDangNhap';
import { Sheet } from '../components/Shell';
import { t, type Khoa } from '../i18n';
import { guiDongY } from '../lib/ai';
import { LoiApi } from '../lib/api';
import { useCaiApp } from '../lib/caiApp';
import { cacMuiGio } from '../lib/muiGio';
import { donMay } from '../lib/offline';
import { useTitle } from '../lib/useTitle';
import { NEW_COUNTS, canPersist, useStore, type FontSize, type Lang, type NewCount, type Season, type Settings, type Theme } from '../state/store';
import { MUC_TIEU, useTaiKhoan, type HoSoMoi, type MucTieu, type TrangThaiLuu } from '../state/taiKhoan';

const SEASON_NAME = { spring: 'caiDat.muaXuan', summer: 'caiDat.muaHa', autumn: 'caiDat.muaThu', winter: 'caiDat.muaDong' } as const satisfies Record<Exclude<Season, 'auto'>, Khoa>;
const LANGS = [
    { id: 'vi', khoa: 'ngonNgu.vi' },
    { id: 'en', khoa: 'ngonNgu.en' },
    { id: 'zh', khoa: 'ngonNgu.zh' },
] as const satisfies readonly { id: Lang; khoa: Khoa }[];
const TEN_MUC_TIEU = {
    study_abroad: 'mucTieu.duHoc',
    vocational: 'mucTieu.hocNghe',
    work: 'mucTieu.diLam',
    exam: 'mucTieu.thi',
    travel: 'mucTieu.duLich',
    other: 'mucTieu.khac',
} as const satisfies Record<MucTieu, Khoa>;
const TRINH_DO = { 1: 'trinhDo.a1', 2: 'trinhDo.a2', 3: 'trinhDo.b1', 4: 'trinhDo.b2' } as const satisfies Record<number, Khoa>;
const LUU = { 'dang-luu': 'caiDat.dangLuu', 'da-luu': 'caiDat.daLuu', loi: 'caiDat.luuLoi' } as const satisfies Record<Exclude<TrangThaiLuu, 'yen'>, Khoa>;

export function CaiDat() {
    const { settings, season, dispatch } = useStore();
    const { trangThai, toi, luu } = useTaiKhoan();
    useTitle(t('khung.caiDat'));
    const [saving] = useState(canPersist);
    const uid = useId();
    const set = (patch: Partial<Settings>) => dispatch({ type: 'settings', patch });
    const theoTaiKhoan = trangThai === 'xong' && toi !== null;

    return (
        <Sheet className="mx-auto max-w-[720px] px-5 py-6 md:px-8">
            <h1 className="text-headline font-bold">{t('khung.caiDat')}</h1>
            {theoTaiKhoan ? (
                <p className="mt-1 text-ink-2">
                    {t('caiDat.luuTheoTaiKhoan')}{' '}
                    <span role="status" className={luu === 'loi' ? 'font-semibold text-danger' : ''}>
                        {luu === 'yen' ? '' : t(LUU[luu])}
                    </span>
                </p>
            ) : (
                <p role={saving ? undefined : 'alert'} className={`mt-1 ${saving ? 'text-ink-2' : 'font-semibold text-danger'}`}>
                    {saving ? t('caiDat.luuTrenTrinhDuyet') : t('caiDat.biChanLuu')}
                </p>
            )}

            <div className="mt-6 space-y-6">
                <Choice<Theme>
                    legend={t('caiDat.giaoDien')}
                    name={`${uid}-giao-dien`}
                    value={settings.theme}
                    onChange={(theme) => set({ theme })}
                    options={[
                        { value: 'light', label: t('caiDat.sang') },
                        { value: 'dark', label: t('caiDat.toi') },
                    ]}
                />
                <Choice<FontSize>
                    legend={t('caiDat.coChu')}
                    name={`${uid}-co-chu`}
                    value={settings.fontSize}
                    onChange={(fontSize) => set({ fontSize })}
                    options={[
                        { value: 'sm', label: t('caiDat.nho') },
                        { value: 'md', label: t('caiDat.vua') },
                        { value: 'lg', label: t('caiDat.lon') },
                    ]}
                />
                <Choice<Season>
                    legend={t('caiDat.canhTheoMua')}
                    name={`${uid}-mua`}
                    value={settings.season}
                    onChange={(s) => set({ season: s })}
                    options={[
                        { value: 'auto', label: t('caiDat.theoThang', { mua: t(SEASON_NAME[season]) }) },
                        { value: 'spring', label: t('caiDat.xuan') },
                        { value: 'summer', label: t('caiDat.ha') },
                        { value: 'autumn', label: t('caiDat.thu') },
                        { value: 'winter', label: t('caiDat.dong') },
                    ]}
                />

                <fieldset>
                    <legend className="text-label font-semibold">{t('caiDat.nghiaTrenThe')}</legend>
                    <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        {LANGS.map((l) => (
                            <label key={l.id} className="flex min-h-11 cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={settings.langs[l.id]}
                                    onChange={() => set({ langs: { ...settings.langs, [l.id]: !settings.langs[l.id] } })}
                                    className="size-5"
                                />
                                {t(l.khoa)}
                            </label>
                        ))}
                    </div>
                </fieldset>

                <Choice<NewCount>
                    legend={t('caiDat.tuMoiMoiLuot')}
                    name={`${uid}-tu-moi`}
                    value={settings.newPerSession}
                    onChange={(newPerSession) => set({ newPerSession })}
                    options={NEW_COUNTS.map((n) => ({ value: n, label: n === 'all' ? t('chung.tatCa') : String(n) }))}
                />
            </div>

            {theoTaiKhoan && <MucAi />}
            {theoTaiKhoan && <TaiKhoanPhan />}
        </Sheet>
    );
}

/** AI: đã đồng ý thì rút được; chưa khai, chưa đủ tuổi, chưa đồng ý thì một câu giải thích. AI chưa mở cho tài khoản này thì ẩn. */
function MucAi() {
    const { toi, nhanToi } = useTaiKhoan();
    const uid = useId();
    const [loi, setLoi] = useState<string | null>(null);
    const [dangGui, setDangGui] = useState(false);
    const ai = toi?.ai;
    if (!ai || ai.state === 'hidden') return null;

    const rut = async () => {
        setLoi(null);
        setDangGui(true);
        try {
            const { data } = await guiDongY(false);
            nhanToi(data);
        } catch (e) {
            setLoi(chuLoi(e));
        } finally {
            setDangGui(false);
        }
    };

    return (
        <section aria-labelledby={`${uid}-ai`} className="mt-10 border-t border-line pt-6">
            <h2 id={`${uid}-ai`} className="text-title font-bold">
                {t('ai.caiDat.tieuDe')}
            </h2>
            {ai.state === 'ready' ? (
                <>
                    <p className="mt-2">{t('ai.caiDat.daDongY')}</p>
                    <button
                        type="button"
                        onClick={() => void rut()}
                        disabled={dangGui}
                        className="mt-3 inline-flex min-h-11 items-center rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken disabled:opacity-60"
                    >
                        {t('ai.caiDat.rut')}
                    </button>
                </>
            ) : (
                <p className="mt-2 text-ink-2">
                    {ai.state === 'need_birth_date' ? t('ai.caiDat.chuaKhai') : ai.state === 'underage' ? t('ai.chuaDuTuoi', { tuoi: ai.min_age }) : t('ai.caiDat.chuaDongY')}
                </p>
            )}
            {loi && (
                <p role="alert" className="mt-2 text-label font-semibold text-danger">
                    {loi}
                </p>
            )}
        </section>
    );
}

/** Mục tiêu học, trình độ, hồ sơ, máy đang đăng nhập và nút đăng xuất: chỉ có khi đã tải được tài khoản. */
function TaiKhoanPhan() {
    const { toi, capNhatHoSo } = useTaiKhoan();
    const uid = useId();
    const [tin, setTin] = useState<{ loai: 'xong' | 'loi'; chu: string } | null>(null);
    const [loiTen, setLoiTen] = useState<string | null>(null);
    const [ten, setTen] = useState(toi?.name ?? '');
    if (!toi) return null;

    const luuHoSo = async (thayDoi: HoSoMoi) => {
        setTin(null);
        try {
            await capNhatHoSo(thayDoi);
            setTin({ loai: 'xong', chu: t('caiDat.daLuu') });
            return true;
        } catch (e) {
            const chu = e instanceof LoiApi ? (Object.values(e.loiO)[0]?.[0] ?? (e.trangThai === 0 ? t('loi.matMang') : t('loi.chung'))) : t('loi.chung');
            setTin({ loai: 'loi', chu });
            return false;
        }
    };

    const muiGio = cacMuiGio(toi.timezone);
    const khoa = toi.course;

    return (
        <>
            {khoa && (
                <section aria-labelledby={`${uid}-muc-tieu`} className="mt-10 space-y-6 border-t border-line pt-6">
                    <h2 id={`${uid}-muc-tieu`} className="text-title font-bold">
                        {t('caiDat.mucTieuHoc')}
                    </h2>
                    <Choice<MucTieu | ''>
                        legend={t('caiDat.hocDeLam')}
                        name={`${uid}-muc-dich`}
                        value={khoa.goal ?? ''}
                        onChange={(goal) => void luuHoSo({ goal: goal || null })}
                        options={MUC_TIEU.map((m) => ({ value: m, label: t(TEN_MUC_TIEU[m]) }))}
                    />
                    <Choice<number>
                        legend={t('caiDat.trinhDo')}
                        name={`${uid}-trinh-do`}
                        value={khoa.level ?? 0}
                        onChange={(level) => void luuHoSo({ level })}
                        options={Object.entries(TRINH_DO)
                            .map(([so, k]) => ({ value: Number(so), label: t(k) }))
                            .filter((o) => o.value >= khoa.level_min && o.value <= khoa.level_max)}
                    />
                </section>
            )}

            <section aria-labelledby={`${uid}-tai-khoan`} className="mt-10 border-t border-line pt-6">
                <h2 id={`${uid}-tai-khoan`} className="text-title font-bold">
                    {t('caiDat.taiKhoan')}
                </h2>
                <p className="mt-2 text-ink-2">{t('caiDat.email', { email: toi.email })}</p>
                <form
                    className="mt-4 flex flex-wrap items-end gap-3"
                    onSubmit={async (e) => {
                        e.preventDefault();
                        setLoiTen(null);
                        const tenMoi = ten.trim();
                        if (!tenMoi) return setLoiTen(t('caiDat.tenTrong'));
                        await luuHoSo({ name: tenMoi });
                    }}
                >
                    <div className="min-w-60 flex-1">
                        <label htmlFor={`${uid}-ten`} className="block text-label font-semibold">
                            {t('caiDat.ten')}
                        </label>
                        <input
                            id={`${uid}-ten`}
                            value={ten}
                            maxLength={100}
                            autoComplete="name"
                            onChange={(e) => setTen(e.target.value)}
                            aria-invalid={loiTen ? true : undefined}
                            aria-describedby={loiTen ? `${uid}-ten-loi` : undefined}
                            className="mt-1.5 block min-h-11 w-full rounded-md border border-line-strong bg-paper px-3 py-2 text-body"
                        />
                        {loiTen && (
                            <p id={`${uid}-ten-loi`} className="mt-1.5 text-label text-danger">
                                {loiTen}
                            </p>
                        )}
                    </div>
                    <button type="submit" className="min-h-11 rounded-md border border-line-strong px-4 font-semibold hover:bg-sunken">
                        {t('caiDat.luuTen')}
                    </button>
                </form>

                <div className="mt-5">
                    <label htmlFor={`${uid}-mui-gio`} className="block text-label font-semibold">
                        {t('caiDat.muiGio')}
                    </label>
                    <select
                        id={`${uid}-mui-gio`}
                        value={toi.timezone}
                        onChange={(e) => void luuHoSo({ timezone: e.target.value })}
                        aria-describedby={`${uid}-mui-gio-ghi-chu`}
                        className="mt-1.5 block min-h-11 w-full max-w-sm rounded-md border border-line-strong bg-paper px-3 text-body"
                    >
                        {muiGio.map((m) => (
                            <option key={m} value={m}>
                                {m}
                            </option>
                        ))}
                    </select>
                    <p id={`${uid}-mui-gio-ghi-chu`} className="mt-1.5 text-label text-ink-2">
                        {t('caiDat.muiGioGhiChu')}
                    </p>
                </div>
                <p role="status" className={`mt-3 min-h-6 text-label ${tin?.loai === 'loi' ? 'font-semibold text-danger' : 'text-ink-2'}`}>
                    {tin?.chu}
                </p>
            </section>

            <MayDangNhap />

            <CaiApp />

            <DangXuat />
        </>
    );
}

/** Cài app lên máy (PWA): nút cài của trình duyệt nếu có, iPhone thì cách thêm vào màn hình chính, đã cài thì báo. */
function CaiApp() {
    const uid = useId();
    const { cach, cai } = useCaiApp();
    return (
        <section aria-labelledby={`${uid}-cai`} className="mt-10 border-t border-line pt-6">
            <h2 id={`${uid}-cai`} className="text-title font-bold">
                {t('caiDat.caiApp')}
            </h2>
            <p className="mt-1.5 text-ink-2">{cach === 'da-cai' ? t('caiDat.daCaiApp') : t('caiDat.caiAppMoTa')}</p>
            {cach === 'nut' && (
                <button
                    type="button"
                    onClick={() => void cai()}
                    className="mt-3 inline-flex min-h-12 items-center gap-2 rounded-md bg-tim px-5 font-semibold text-on-tim transition-colors duration-200 hover:bg-tim-strong"
                >
                    <Download size={18} aria-hidden="true" />
                    {t('caiDat.caiAppNut')}
                </button>
            )}
            {cach === 'ios' && <p className="mt-2 text-label">{t('caiDat.caiAppIos')}</p>}
            {cach === 'menu' && <p className="mt-2 text-label">{t('caiDat.caiAppKhac')}</p>}
        </section>
    );
}

/**
 * Đăng xuất bằng trang của máy chủ; trước đó gửi nốt lượt chấm và dọn dữ liệu của người này trên máy (chủ đề đã tải,
 * bản lưu khi mất mạng), để người dùng máy sau không thấy. Không có mạng thì không đăng xuất được: báo như vậy.
 */
function DangXuat() {
    const [tinh, setTinh] = useState<'yen' | 'dang-don' | 'can-mang'>('yen');
    const onSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (tinh === 'dang-don') return;
        if (!navigator.onLine) {
            setTinh('can-mang');
            return;
        }
        const form = e.currentTarget;
        setTinh('dang-don');
        void donMay().finally(() => form.submit());
    };
    return (
        <form method="post" action="/dang-xuat" onSubmit={onSubmit} className="mt-10 border-t border-line pt-6">
            <input type="hidden" name="_token" value={maCsrf()} />
            <button
                type="submit"
                disabled={tinh === 'dang-don'}
                className="min-h-12 rounded-md border border-line-strong px-5 font-semibold hover:bg-sunken disabled:opacity-60"
            >
                {t('caiDat.dangXuat')}
            </button>
            <p role="status" className="mt-2 min-h-6 text-label text-ink-2">
                {tinh === 'dang-don' ? t('caiDat.dangDon') : tinh === 'can-mang' ? t('caiDat.canMang') : null}
            </p>
        </form>
    );
}

function maCsrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
