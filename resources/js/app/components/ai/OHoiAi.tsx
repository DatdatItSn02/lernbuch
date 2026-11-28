import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { t } from '../../i18n';
import { coChu } from '../../lib/ai';
import { NUT_AI } from './KetQuaAi';

type Props = {
    nhan: string;
    toiDa: number;
    giaTri: string;
    onDoi: (v: string) => void;
    onGui: (chu: string) => void;
    nut: string;
    dangGui?: boolean;
    /** Một dòng (Tra từ, chủ đề) hay nhiều dòng. */
    motDong?: boolean;
    /** Chữ tiếng Đức: đặt lang để trình đọc màn hình đọc đúng. */
    de?: boolean;
    rows?: number;
    /** Phần thêm giữa ô nhập và nút (chọn chiều dịch…). */
    them?: ReactNode;
    /** Lỗi của máy chủ cho ô này (422). */
    loiMayChu?: string | null;
};

/**
 * Ô nhập để hỏi AI: bộ đếm ký tự, không chặn dán (dán quá dài thì báo và tắt nút gửi), toàn số hay ký hiệu thì báo
 * ngay không gửi. Nhiều dòng: Ctrl+Enter để gửi.
 */
export function OHoiAi({ nhan, toiDa, giaTri, onDoi, onGui, nut, dangGui = false, motDong = false, de = false, rows = 4, them, loiMayChu }: Props) {
    const id = useId();
    const [loi, setLoi] = useState<string | null>(null);
    const chu = giaTri.trim();
    const dai = chu.length;
    const quaDai = dai > toiDa;
    const baoLoi = quaDai ? t('ai.o.daiQua', { toiDa }) : (loi ?? loiMayChu ?? null);

    const gui = (e?: FormEvent) => {
        e?.preventDefault();
        if (dangGui || quaDai) return;
        if (chu === '') return setLoi(t('ai.o.oTrong'));
        if (!coChu(chu)) return setLoi(t('ai.o.canChu'));
        setLoi(null);
        onGui(chu);
    };
    const chung = {
        id,
        value: giaTri,
        lang: de ? 'de' : undefined,
        'aria-invalid': baoLoi ? true : undefined,
        'aria-describedby': `${id}-dem${baoLoi ? ` ${id}-loi` : ''}`,
        className: 'mt-1.5 block w-full rounded-md border border-line-strong bg-paper px-3 py-2 text-body',
    } as const;

    return (
        <form onSubmit={gui} noValidate>
            <label htmlFor={id} className="block text-label font-semibold">
                {nhan}
            </label>
            {motDong ? (
                <input
                    {...chung}
                    type="text"
                    autoComplete="off"
                    onChange={(e) => {
                        setLoi(null);
                        onDoi(e.target.value);
                    }}
                    className={`${chung.className} min-h-11`}
                />
            ) : (
                <textarea
                    {...chung}
                    rows={rows}
                    onChange={(e) => {
                        setLoi(null);
                        onDoi(e.target.value);
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) gui();
                    }}
                />
            )}
            <p id={`${id}-dem`} className={`mt-1 text-label tabular-nums ${quaDai ? 'font-semibold text-danger' : 'text-ink-2'}`}>
                {t('ai.o.demKyTu', { n: dai, toiDa })}
            </p>
            {baoLoi && (
                <p id={`${id}-loi`} role="alert" className="mt-1 text-label font-semibold text-danger">
                    {baoLoi}
                </p>
            )}
            {them}
            <button type="submit" disabled={dangGui || quaDai} className={`mt-3 ${NUT_AI}`}>
                {nut}
            </button>
        </form>
    );
}
