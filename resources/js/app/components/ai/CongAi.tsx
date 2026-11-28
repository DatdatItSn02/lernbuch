import { useEffect, useId, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { t } from '../../i18n';
import { guiDongY, khaiNgaySinh, ngayCoThat } from '../../lib/ai';
import { LoiApi } from '../../lib/api';
import { useTaiKhoan } from '../../state/taiKhoan';

const NUT_CHINH = 'inline-flex min-h-12 items-center rounded-md bg-tim px-5 font-semibold text-on-tim transition-colors duration-200 hover:bg-tim-strong disabled:opacity-60';
const CHON = 'mt-1.5 block min-h-11 rounded-md border border-line-strong bg-paper px-3 text-body';
const THANG = Array.from({ length: 12 }, (_, i) => i + 1);
const NGAY = Array.from({ length: 31 }, (_, i) => i + 1);

/** Chữ báo lỗi: mất mạng nói rõ, lỗi của một ô thì lấy chữ của ô đó, còn lại lấy chữ máy chủ gửi. */
export function chuLoi(e: unknown, o?: string): string {
    if (!(e instanceof LoiApi) || e.ma === 'mat_mang') return t('ai.canMang');
    return (o ? e.loiCua(o) : undefined) ?? e.message;
}

/**
 * Cổng vào AI theo trạng thái của tài khoản: ẩn (AI tắt, chưa được mở), khai ngày sinh, chưa đủ tuổi, xin đồng ý,
 * rồi mới tới phần dùng AI (children). Đổi bước thì đưa tiêu điểm tới tiêu đề của bước mới.
 */
export function CongAi({ children }: { children: ReactNode }) {
    const { toi } = useTaiKhoan();
    const ai = toi?.ai;
    const vung = useRef<HTMLDivElement>(null);
    const buocTruoc = useRef(ai?.state);

    useEffect(() => {
        if (buocTruoc.current === ai?.state) return;
        buocTruoc.current = ai?.state;
        vung.current?.querySelector<HTMLElement>('[data-dau-buoc]')?.focus();
    }, [ai?.state]);

    if (!ai || ai.state === 'hidden') return null;
    return (
        <div ref={vung}>
            {ai.state === 'ready' ? (
                <div data-dau-buoc tabIndex={-1} className="outline-none">
                    {children}
                </div>
            ) : ai.state === 'need_birth_date' ? (
                <BuocNgaySinh tuoi={ai.min_age} />
            ) : ai.state === 'need_consent' ? (
                <BuocDongY />
            ) : (
                <p data-dau-buoc tabIndex={-1} className="outline-none">
                    {t('ai.chuaDuTuoi', { tuoi: ai.min_age })}
                </p>
            )}
        </div>
    );
}

function BuocNgaySinh({ tuoi }: { tuoi: number }) {
    const { nhanToi } = useTaiKhoan();
    const uid = useId();
    const [ngay, setNgay] = useState('');
    const [thang, setThang] = useState('');
    const [nam, setNam] = useState('');
    const [loi, setLoi] = useState<string | null>(null);
    const [dangGui, setDangGui] = useState(false);
    const namNay = new Date().getFullYear();
    const cacNam = Array.from({ length: namNay - 1900 + 1 }, (_, i) => namNay - i);

    const gui = async (e: FormEvent) => {
        e.preventDefault();
        if (!ngay || !thang || !nam) return setLoi(t('ai.ngaySinh.chonDu'));
        if (!ngayCoThat(Number(nam), Number(thang), Number(ngay))) return setLoi(t('ai.ngaySinh.khongCoThat'));
        setLoi(null);
        setDangGui(true);
        try {
            const { data } = await khaiNgaySinh(`${nam}-${thang.padStart(2, '0')}-${ngay.padStart(2, '0')}`);
            nhanToi(data);
        } catch (err) {
            setLoi(chuLoi(err, 'birth_date'));
            setDangGui(false);
        }
    };

    const o = (id: string, nhan: string, giaTri: string, doi: (v: string) => void, ds: number[]) => (
        <div>
            <label htmlFor={`${uid}-${id}`} className="text-label font-semibold text-ink-2">
                {nhan}
            </label>
            <select id={`${uid}-${id}`} value={giaTri} onChange={(e) => doi(e.target.value)} className={CHON}>
                <option value="">{t('ai.ngaySinh.chon')}</option>
                {ds.map((n) => (
                    <option key={n} value={String(n)}>
                        {n}
                    </option>
                ))}
            </select>
        </div>
    );

    return (
        <form onSubmit={gui} noValidate>
            <h2 data-dau-buoc tabIndex={-1} id={`${uid}-tieu-de`} className="text-title font-bold outline-none">
                {t('ai.ngaySinh.tieuDe')}
            </h2>
            <p className="mt-1 text-ink-2">{t('ai.ngaySinh.moTa', { tuoi })}</p>
            <fieldset aria-labelledby={`${uid}-tieu-de`} aria-describedby={loi ? `${uid}-loi` : undefined} className="mt-4 flex flex-wrap gap-3">
                {o('ngay', t('ai.ngaySinh.ngay'), ngay, setNgay, NGAY)}
                {o('thang', t('ai.ngaySinh.thang'), thang, setThang, THANG)}
                {o('nam', t('ai.ngaySinh.nam'), nam, setNam, cacNam)}
            </fieldset>
            {loi && (
                <p id={`${uid}-loi`} role="alert" className="mt-2 text-label font-semibold text-danger">
                    {loi}
                </p>
            )}
            <button type="submit" disabled={dangGui} className={`mt-4 ${NUT_CHINH}`}>
                {t('ai.ngaySinh.tiepTuc')}
            </button>
        </form>
    );
}

function BuocDongY() {
    const { nhanToi } = useTaiKhoan();
    const [loi, setLoi] = useState<string | null>(null);
    const [dangGui, setDangGui] = useState(false);
    const y = [t('ai.dongY.y1'), t('ai.dongY.y2'), t('ai.dongY.y3'), t('ai.dongY.y4'), t('ai.dongY.y5'), t('ai.dongY.y6')];

    const dongY = async () => {
        setLoi(null);
        setDangGui(true);
        try {
            const { data } = await guiDongY(true);
            nhanToi(data);
        } catch (err) {
            setLoi(chuLoi(err));
            setDangGui(false);
        }
    };

    return (
        <div>
            <h2 data-dau-buoc tabIndex={-1} className="text-title font-bold outline-none">
                {t('ai.dongY.tieuDe')}
            </h2>
            <ul className="mt-3 list-disc space-y-2 pl-5">
                {y.map((cau) => (
                    <li key={cau}>{cau}</li>
                ))}
            </ul>
            {loi && (
                <p role="alert" className="mt-3 text-label font-semibold text-danger">
                    {loi}
                </p>
            )}
            <button type="button" onClick={() => void dongY()} disabled={dangGui} className={`mt-4 ${NUT_CHINH}`}>
                {t('ai.dongY.nut')}
            </button>
        </div>
    );
}
