// Phần dùng chung của Ngữ pháp, Bài tập, Động từ: nút dạng liên kết, thanh tiến độ nhỏ, nút lọc cấp, đường quay lại.
import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { tenCap } from '../../lib/noiDung';
import { t } from '../../i18n';

/** Cấp có nội dung luyện tập (A1–B2). */
export const CAC_CAP = [1, 2, 3, 4] as const;

const NUT = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 py-2 text-center font-semibold transition-colors duration-200';
export const LOP_NUT_CHINH = `${NUT} bg-tim font-bold text-on-tim hover:bg-tim-strong`;
export const LOP_NUT_PHU = `${NUT} border border-line-strong hover:bg-sunken`;
export const LOP_CHIP = (on: boolean) =>
    `flex min-h-11 items-center gap-1.5 rounded-md border px-3 text-label font-semibold transition-colors duration-200 ${
        on ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong hover:text-ink'
    }`;

export function NutLink({ to, chinh = false, children, className = '' }: { to: string; chinh?: boolean; children: ReactNode; className?: string }) {
    return (
        <Link to={to} className={`${chinh ? LOP_NUT_CHINH : LOP_NUT_PHU} ${className}`}>
            {children}
        </Link>
    );
}

/** Thanh tiến độ nhỏ (đã thuộc / tổng); trình đọc màn hình đọc câu kèm theo. */
export function ThanhNho({ done, n, nhan }: { done: number; n: number; nhan: string }) {
    const pct = n > 0 ? Math.round((done / n) * 100) : 0;
    return (
        <span role="img" aria-label={nhan} className="inline-block h-1.5 w-16 shrink-0 overflow-hidden rounded-sm bg-sunken align-middle">
            <span className="block h-full bg-tim" style={{ width: `${pct}%` }} />
        </span>
    );
}

/** Nút lọc nhiều cấp (bấm lần nữa để bỏ). */
export function LocCap({ cap, onDoi, them }: { cap: number[]; onDoi: (cap: number[]) => void; them?: ReactNode }) {
    return (
        <div role="group" aria-label={t('noiDung.locCap')} className="flex flex-wrap gap-1.5">
            {CAC_CAP.map((c) => {
                const on = cap.includes(c);
                return (
                    <button key={c} type="button" aria-pressed={on} onClick={() => onDoi(on ? cap.filter((x) => x !== c) : [...cap, c].sort())} className={LOP_CHIP(on)}>
                        {tenCap(c)}
                    </button>
                );
            })}
            {them}
        </div>
    );
}

/** Đường quay lại lấy từ ?ve=: chỉ nhận đường dẫn trong app ("/…", không phải "//…"). */
export function duongVe(ve: string | null, macDinh: string): string {
    return ve && ve.startsWith('/') && !ve.startsWith('//') ? ve : macDinh;
}
