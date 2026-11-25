import type { ReactNode } from 'react';
import type { Season } from '../state/store';

// Cảnh mùa vẽ tay đơn giản cho đầu trang Hôm nay (chỉ ở đây và lúc xong phiên).
// Đồi và một cái cây; tán cây đổi theo mùa: xuân hoa anh đào, hạ tán xanh, thu quả cam, đông cành trơ và tuyết.

const canopy: Record<Exclude<Season, 'auto'>, (x: number, y: number) => ReactNode> = {
    spring: (x, y) => (
        <g>
            <circle cx={x} cy={y} r="26" fill="var(--color-level-2)" />
            {[[-14, -8], [6, -16], [16, 4], [-4, 10], [-18, 8], [10, -2], [-6, -18]].map(([dx, dy], i) => (
                <circle key={i} cx={x + dx} cy={y + dy} r="4.2" fill="var(--season)" stroke="var(--color-sheet)" strokeWidth="1" />
            ))}
        </g>
    ),
    summer: (x, y) => (
        <g>
            <circle cx={x} cy={y} r="27" fill="var(--color-level-3)" />
            <circle cx={x - 12} cy={y + 6} r="15" fill="var(--color-level-4)" />
        </g>
    ),
    autumn: (x, y) => (
        <g>
            <circle cx={x} cy={y} r="26" fill="var(--color-level-3)" />
            {[[-13, -6], [7, -14], [15, 5], [-3, 11], [-17, 9], [3, -1]].map(([dx, dy], i) => (
                <circle key={i} cx={x + dx} cy={y + dy} r="4.6" fill="var(--season)" stroke="var(--color-ink-2)" strokeWidth="1" />
            ))}
        </g>
    ),
    winter: (x, y) => (
        <g fill="none" stroke="var(--color-ink-2)" strokeWidth="2" strokeLinecap="round">
            <path d={`M${x} ${y + 24}V${y - 6}M${x} ${y + 4}l-14-12M${x} ${y - 2}l12-14M${x} ${y + 12}l16-8`} />
            <path d={`M${x - 22} ${y - 14}h44`} stroke="var(--color-sheet)" strokeWidth="5" opacity="0.9" />
        </g>
    ),
};

export function SeasonScene({ season, className = '' }: { season: Exclude<Season, 'auto'>; className?: string }) {
    return (
        <svg viewBox="0 0 360 96" preserveAspectRatio="xMaxYMax slice" aria-hidden="true" className={className}>
            <path d="M0 96V70c60-20 120-26 190-14s120 6 170-10v50z" fill="var(--color-level-1)" />
            <path d="M120 96c40-18 110-30 240-22v22z" fill="var(--color-level-2)" />
            <path d="M292 92V54" stroke="var(--color-ink-2)" strokeWidth="5" strokeLinecap="round" />
            {canopy[season](292, 40)}
            {season === 'winter' && <path d="M0 96V84c90-10 200-12 360-4v16z" fill="var(--color-sheet)" />}
        </svg>
    );
}
