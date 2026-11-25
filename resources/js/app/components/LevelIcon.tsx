import { LEVEL_NAMES, STAGE_NAMES } from '../lib/srs';

// Dấu ấn của Lernbuch: 6 mức thuộc vẽ thành 6 giai đoạn của cây (hạt, mầm, lá, nụ, hoa, quả).
// Nét mực nâu, phần chính tô màu của mức; đọc được ở cỡ 20–24 px.

const fill = (level: number) => `var(--color-level-${level})`;

function Leaves({ low, high, level }: { low?: boolean; high?: boolean; level: number }) {
    return (
        <>
            {low && <path d="M12 17c-3.3 0-5.4-1.9-5.4-4.7 3.1 0 5.4 1.8 5.4 4.7z" fill={fill(level)} />}
            {high && <path d="M12 13.6c0-2.9 2.2-4.9 5.4-4.9 0 2.8-2.2 4.9-5.4 4.9z" fill={fill(level)} />}
        </>
    );
}

export function LevelIcon({ level, size = 24, title }: { level: number; size?: number; title?: string }) {
    const lv = Math.min(5, Math.max(0, Math.round(level)));
    // title="" là hình trang trí (chữ bên cạnh đã nói mức): ẩn khỏi trình đọc màn hình.
    const label = title ?? `${STAGE_NAMES[lv]}: mức ${LEVEL_NAMES[lv]}`;
    const a11y = label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': true as const, focusable: 'false' as const };
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            {...a11y}
            className="shrink-0"
            fill="none"
            stroke="var(--color-ink-2)"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M5 20.5h14" stroke="var(--color-line-strong)" />
            {lv === 0 && <ellipse cx="12" cy="17.6" rx="3.1" ry="2.2" transform="rotate(-24 12 17.6)" fill={fill(0)} />}
            {lv === 1 && (
                <>
                    <path d="M12 20.5v-5" />
                    <path d="M12 16.4c-2.5 0-3.9-1.4-3.9-3.5 2.3 0 3.9 1.3 3.9 3.5z" fill={fill(1)} />
                    <path d="M12 15.5c0-2.2 1.5-3.7 3.9-3.7 0 2.1-1.5 3.7-3.9 3.7z" fill={fill(1)} />
                </>
            )}
            {lv === 2 && (
                <>
                    <path d="M12 20.5V9.8" />
                    <Leaves low high level={2} />
                </>
            )}
            {lv === 3 && (
                <>
                    <path d="M12 20.5V9" />
                    <Leaves low high level={3} />
                    <path d="M12 9c-1.6-1.1-2.3-2.5-2.3-3.8 0-1.4 1-2.4 2.3-2.9 1.3.5 2.3 1.5 2.3 2.9 0 1.3-.7 2.7-2.3 3.8z" fill={fill(3)} />
                </>
            )}
            {lv === 4 && (
                <>
                    <path d="M12 20.5V9.6" />
                    <Leaves low level={4} />
                    {[0, 72, 144, 216, 288].map((a) => (
                        <circle
                            key={a}
                            cx={12 + 2.4 * Math.sin((a * Math.PI) / 180)}
                            cy={6.4 - 2.4 * Math.cos((a * Math.PI) / 180)}
                            r="1.9"
                            fill={fill(4)}
                        />
                    ))}
                    <circle cx="12" cy="6.4" r="1.1" fill="var(--color-sheet)" />
                </>
            )}
            {lv === 5 && (
                <>
                    <path d="M12 20.5V12" />
                    <Leaves low level={5} />
                    <circle cx="12" cy="8.2" r="3.7" fill={fill(5)} />
                    <path d="M12 4.5c.7-1.3 1.9-2 3.3-2-.1 1.4-1.2 2.3-3.3 2z" fill={fill(2)} />
                </>
            )}
        </svg>
    );
}
