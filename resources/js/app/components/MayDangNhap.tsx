import { Laptop, Smartphone, Tablet } from 'lucide-react';
import { useCallback, useEffect, useId, useState } from 'react';
import { ngonNgu, t } from '../i18n';
import { api, LoiApi } from '../lib/api';

export type Phien = {
    browser: string | null;
    os: string | null;
    device_type: 'phone' | 'tablet' | 'desktop';
    ip: string | null;
    last_active_at: number;
    is_current: boolean;
};

const HINH = { phone: Smartphone, tablet: Tablet, desktop: Laptop };

function tenMay(p: Phien): string {
    if (p.browser && p.os) return t('mayDangNhap.trinhDuyetTren', { trinhDuyet: p.browser, heDieuHanh: p.os });
    return p.browser ?? p.os ?? t('mayDangNhap.khongRo');
}

function luc(giay: number): string {
    return new Intl.DateTimeFormat(ngonNgu(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(giay * 1000));
}

/** Các máy đang đăng nhập tài khoản này, và nút đăng xuất mọi máy khác (cần mật khẩu). */
export function MayDangNhap() {
    const uid = useId();
    const [ds, setDs] = useState<Phien[] | null>(null);
    const [coHoTro, setCoHoTro] = useState(true);
    const [loiTai, setLoiTai] = useState(false);
    const [moForm, setMoForm] = useState(false);
    const [matKhau, setMatKhau] = useState('');
    const [loiMatKhau, setLoiMatKhau] = useState<string | null>(null);
    const [tin, setTin] = useState<string | null>(null);
    const [dangGui, setDangGui] = useState(false);

    const tai = useCallback(async () => {
        try {
            const r = await api.lay<{ data: Phien[]; supported: boolean }>('/toi/phien');
            setDs(r.data);
            setCoHoTro(r.supported);
            setLoiTai(false);
        } catch {
            setLoiTai(true);
        }
    }, []);

    useEffect(() => {
        let huy = false;
        api.lay<{ data: Phien[]; supported: boolean }>('/toi/phien')
            .then((r) => {
                if (huy) return;
                setDs(r.data);
                setCoHoTro(r.supported);
            })
            .catch(() => {
                if (!huy) setLoiTai(true);
            });
        return () => {
            huy = true;
        };
    }, []);

    const dangXuatMayKhac = async () => {
        setLoiMatKhau(null);
        setTin(null);
        if (!matKhau) return setLoiMatKhau(t('mayDangNhap.canMatKhau'));
        setDangGui(true);
        try {
            await api.gui('/toi/phien/dang-xuat-may-khac', { password: matKhau });
            setMatKhau('');
            setMoForm(false);
            setTin(t('mayDangNhap.daDangXuat'));
            await tai();
        } catch (e) {
            if (e instanceof LoiApi && e.trangThai === 422) setLoiMatKhau(e.loiCua('password') ?? t('loi.chung'));
            else if (e instanceof LoiApi && e.trangThai === 429) setLoiMatKhau(t('loi.quaNhanh'));
            else setLoiMatKhau(e instanceof LoiApi && e.trangThai === 0 ? t('loi.matMang') : t('loi.chung'));
        } finally {
            setDangGui(false);
        }
    };

    const coMayKhac = (ds ?? []).some((p) => !p.is_current);

    return (
        <section aria-labelledby={`${uid}-tieu-de`} className="mt-10 border-t border-line pt-6">
            <h2 id={`${uid}-tieu-de`} className="text-title font-bold">
                {t('mayDangNhap.tieuDe')}
            </h2>
            {loiTai && <p className="mt-2 text-danger">{t('mayDangNhap.khongTaiDuoc')}</p>}
            {!coHoTro && <p className="mt-2 text-ink-2">{t('mayDangNhap.khongHoTro')}</p>}
            {ds === null && !loiTai && <p className="mt-2 text-ink-2">{t('chung.dangTai')}</p>}
            {ds !== null && ds.length > 0 && (
                <ul className="mt-3 divide-y divide-line border-y border-line">
                    {ds.map((p, i) => {
                        const Hinh = HINH[p.device_type] ?? Laptop;
                        return (
                            <li key={`${p.last_active_at}-${i}`} className="flex items-start gap-3 py-3">
                                <Hinh size={22} aria-hidden="true" className="mt-0.5 shrink-0 text-ink-2" />
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {tenMay(p)}
                                        {p.is_current && <span className="ml-2 rounded-sm bg-tim-tint px-1.5 text-label font-semibold">{t('mayDangNhap.mayNay')}</span>}
                                    </p>
                                    <p className="text-label text-ink-2">
                                        {p.is_current ? t('mayDangNhap.dangDung') : t('mayDangNhap.lanCuoi', { luc: luc(p.last_active_at) })}
                                        {p.ip ? ` · ${p.ip}` : ''}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}

            <p role="status" className="mt-3 min-h-6 text-label text-ink-2">
                {tin}
            </p>

            {coMayKhac && !moForm && (
                <button type="button" onClick={() => setMoForm(true)} className="min-h-11 rounded-md border border-line-strong px-4 font-semibold hover:bg-sunken">
                    {t('mayDangNhap.dangXuatMayKhac')}
                </button>
            )}
            {moForm && (
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void dangXuatMayKhac();
                    }}
                >
                    <div className="min-w-60 flex-1">
                        <label htmlFor={`${uid}-mat-khau`} className="block text-label font-semibold">
                            {t('mayDangNhap.nhapMatKhau')}
                        </label>
                        <input
                            id={`${uid}-mat-khau`}
                            type="password"
                            autoComplete="current-password"
                            value={matKhau}
                            onChange={(e) => setMatKhau(e.target.value)}
                            aria-invalid={loiMatKhau ? true : undefined}
                            aria-describedby={loiMatKhau ? `${uid}-mat-khau-loi` : undefined}
                            className="mt-1.5 block min-h-11 w-full rounded-md border border-line-strong bg-paper px-3 py-2 text-body"
                        />
                        {loiMatKhau && (
                            <p id={`${uid}-mat-khau-loi`} className="mt-1.5 text-label text-danger">
                                {loiMatKhau}
                            </p>
                        )}
                    </div>
                    <button type="submit" disabled={dangGui} className="min-h-11 rounded-md bg-tim px-4 font-bold text-on-tim hover:bg-tim-strong disabled:opacity-60">
                        {t('mayDangNhap.xacNhan')}
                    </button>
                    <button type="button" onClick={() => setMoForm(false)} className="min-h-11 rounded-md px-3 font-semibold text-tim hover:underline">
                        {t('chung.huy')}
                    </button>
                </form>
            )}
        </section>
    );
}
