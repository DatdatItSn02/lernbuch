import { LEVEL_NAMES, STAGE_NAMES } from '../lib/srs';
import { LevelIcon } from './LevelIcon';

type Props = {
    counts: number[];
    compact?: boolean;
    /** Có thì mỗi mức là một nút lọc (bấm lần nữa để bỏ lọc). */
    picked?: number | null;
    onPick?: (level: number | null) => void;
};

/** Tên nút bắt đầu bằng đúng chữ nhìn thấy (số thẻ, rồi tên giai đoạn trên màn rộng) để điều khiển bằng giọng nói gọi được. */
const label = (i: number, n: number) => `${n} ${STAGE_NAMES[i]} (${LEVEL_NAMES[i]})`;

/**
 * Thanh 6 mức thuộc, dấu ấn của app: dải màu theo tỉ lệ và hình hạt, mầm, lá, nụ, hoa, quả kèm số thẻ từng mức.
 * Chỉ để xem thì trình đọc màn hình nghe một câu tóm tắt; có onPick thì từng mức thành nút lọc.
 */
export function LevelBar({ counts, compact = false, picked = null, onPick }: Props) {
    const total = counts.reduce((a, b) => a + b, 0);
    const bar = (
        <div className="flex h-2 overflow-hidden rounded-pill bg-sunken" aria-hidden={onPick ? true : undefined}>
            {total > 0 &&
                counts.map((n, i) => (n > 0 ? <span key={i} style={{ width: `${(n / total) * 100}%`, background: `var(--color-level-${i})` }} /> : null))}
        </div>
    );
    // Số và tên giai đoạn chung một dòng chữ, cách nhau bằng dấu cách thật (tên chỉ hiện trên màn rộng)
    const count = (n: number, i: number) => (
        <span className={`truncate text-label tabular-nums ${n === 0 ? 'text-ink-2' : 'font-semibold text-ink'}`}>
            {n}
            {!compact && <span className="hidden font-normal text-ink-2 xl:inline">{` ${STAGE_NAMES[i]}`}</span>}
        </span>
    );

    if (onPick) {
        return (
            <div className="min-w-0">
                {bar}
                <div role="group" aria-label="Lọc theo mức thuộc" className="mt-1.5 grid grid-cols-6 gap-1">
                    {counts.map((n, i) => {
                        const on = picked === i;
                        return (
                            <button
                                key={i}
                                type="button"
                                aria-pressed={on}
                                aria-label={label(i, n)}
                                onClick={() => onPick(on ? null : i)}
                                className={`flex min-h-11 min-w-0 items-center justify-center gap-1 rounded-md border px-1 transition-colors duration-200 xl:justify-start xl:px-2 ${
                                    on ? 'border-leaf bg-leaf-tint' : 'border-transparent hover:bg-sunken'
                                }`}
                            >
                                <LevelIcon level={i} size={22} title="" />
                                {count(n, i)}
                            </button>
                        );
                    })}
                </div>
            </div>
        );
    }

    return (
        <div className="min-w-0" role="img" aria-label={`Mức thuộc: ${counts.map((n, i) => `${STAGE_NAMES[i]} (${LEVEL_NAMES[i]}) ${n}`).join(', ')}`}>
            {bar}
            <ul className={`mt-2 grid grid-cols-6 ${compact ? 'gap-1' : 'gap-2'}`}>
                {counts.map((n, i) => (
                    <li key={i} className="flex min-w-0 items-center gap-1">
                        <LevelIcon level={i} size={compact ? 18 : 22} title="" />
                        {count(n, i)}
                    </li>
                ))}
            </ul>
        </div>
    );
}
