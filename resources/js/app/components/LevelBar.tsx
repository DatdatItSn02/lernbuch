import { LEVEL_NAMES, STAGE_NAMES } from '../lib/srs';
import { LevelIcon } from './LevelIcon';

type Props = {
    counts: number[];
    /** Chỉ vẽ dải màu (chỗ hẹp như thẻ chủ đề, đã có câu tóm tắt bên cạnh). */
    compact?: boolean;
    /** Có thì mỗi mức là một nút lọc (bấm lần nữa để bỏ lọc). */
    picked?: number | null;
    onPick?: (level: number | null) => void;
};

/** Tên nút bắt đầu bằng đúng chữ nhìn thấy (số thẻ, rồi tên giai đoạn) để điều khiển bằng giọng nói gọi được. */
const label = (i: number, n: number) => `${n} ${STAGE_NAMES[i]} (${LEVEL_NAMES[i]})`;
const summary = (counts: number[]) => `Mức thuộc: ${counts.map((n, i) => `${STAGE_NAMES[i]} (${LEVEL_NAMES[i]}) ${n}`).join(', ')}`;

/**
 * Thanh 6 mức thuộc: dải mực tím từ nhạt (hạt) đến đậm (quả) theo tỉ lệ, kèm hình cây nét mực, tên và số thẻ từng mức.
 * Chỉ để xem thì trình đọc màn hình nghe một câu tóm tắt; có onPick thì từng mức thành nút lọc.
 */
export function LevelBar({ counts, compact = false, picked = null, onPick }: Props) {
    const total = counts.reduce((a, b) => a + b, 0);
    const bar = (
        <div className="flex h-2 gap-px overflow-hidden rounded-sm bg-sunken" aria-hidden={onPick ? true : undefined}>
            {total > 0 &&
                counts.map((n, i) => (n > 0 ? <span key={i} style={{ width: `${(n / total) * 100}%`, background: `var(--color-level-${i})` }} /> : null))}
        </div>
    );

    if (compact) {
        return (
            <div className="min-w-0" role="img" aria-label={summary(counts)}>
                {bar}
            </div>
        );
    }

    if (onPick) {
        return (
            <div className="min-w-0">
                {bar}
                <div role="group" aria-label="Lọc theo mức thuộc" className="mt-2 grid grid-cols-6 gap-1">
                    {counts.map((n, i) => {
                        const on = picked === i;
                        return (
                            <button
                                key={i}
                                type="button"
                                aria-pressed={on}
                                aria-label={label(i, n)}
                                onClick={() => onPick(on ? null : i)}
                                className={`flex min-h-12 min-w-0 flex-col items-center justify-center rounded-md border px-1 py-1 transition-colors duration-200 sm:flex-row sm:gap-1.5 ${
                                    on ? 'border-tim bg-tim-tint' : 'border-transparent hover:bg-sunken'
                                }`}
                            >
                                <span className="flex items-center gap-1">
                                    <LevelIcon level={i} size={20} title="" className="text-tim" />
                                    <span className={`text-label tabular-nums ${n === 0 ? 'text-ink-2' : 'font-semibold text-ink'}`}>{n}</span>
                                </span>
                                <span className="text-[calc(0.75rem*var(--type-scale))] leading-tight text-ink-2">{` ${STAGE_NAMES[i]}`}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
        );
    }

    return (
        <div className="min-w-0" role="img" aria-label={summary(counts)}>
            {bar}
            <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                {counts.map((n, i) => (
                    <li key={i} className="flex items-center gap-1 text-label">
                        <LevelIcon level={i} size={18} title="" className="text-tim" />
                        <span className="text-ink-2">{STAGE_NAMES[i]}</span>
                        <span className={`tabular-nums ${n === 0 ? 'text-ink-2' : 'font-semibold text-ink'}`}>{n}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
