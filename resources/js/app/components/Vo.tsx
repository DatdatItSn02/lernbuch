import type { ReactNode } from 'react';
import type { Season } from '../state/store';

// Đồ dùng của trang vở: lề đỏ, dấu bút đỏ của thầy cô (tích, khoanh tròn) và cành vẽ nét mực theo mùa.
// Bút đỏ chỉ để chấm: ngày đã học, hôm nay, số thẻ đến hạn, câu trả lời đúng. Không dùng cho nút hay chữ thường.

/** Trang có lề đỏ bên trái như vở ô li; phần lề (cột 40 px) để dấu chấm hoặc hình vẽ. */
export function Margin({ children, aside, className = '' }: { children: ReactNode; aside?: ReactNode; className?: string }) {
    return (
        <div className={`relative pl-14 md:pl-16 ${className}`}>
            <span aria-hidden="true" className="absolute inset-y-0 left-10 w-[1.5px] bg-pen opacity-45 md:left-12" />
            {aside && <div className="absolute top-0 left-0 flex w-10 justify-center md:w-12">{aside}</div>}
            {children}
        </div>
    );
}

/** Dấu tích bút đỏ, nét hơi nghiêng như tay chấm. */
export function PenTick({ size = 16, className = '' }: { size?: number; className?: string }) {
    return (
        <svg width={size} height={size} viewBox="0 0 16 16" aria-hidden="true" className={`text-pen ${className}`} fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2.6 8.9c1.2.8 2.2 1.8 3 3.1C7.7 8 10.2 5.1 13.6 2.9" />
        </svg>
    );
}

/** Vòng khoanh bút đỏ quanh một con số hay một ô; vẽ hở một chút ở đầu nét như khoanh bằng tay. */
export function PenRing({ children, className = '', label }: { children: ReactNode; className?: string; label?: string }) {
    return (
        <span className={`relative inline-flex items-center justify-center font-bold text-pen tabular-nums ${className}`} aria-label={label}>
            <svg viewBox="0 0 40 32" preserveAspectRatio="none" aria-hidden="true" className="pointer-events-none absolute -inset-1 h-[calc(100%+0.5rem)] w-[calc(100%+0.5rem)]" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                <path d="M27 3.6C20.5 1.8 8.8 2.6 4.6 9c-4 6.2.6 17.6 14.4 19.4 10.4 1.3 18.6-4.2 18.6-11.4C37.6 9.6 30.4 4.6 20.8 4.2" vectorEffect="non-scaling-stroke" />
            </svg>
            <span className="relative">{children}</span>
        </span>
    );
}

/** Cành nhỏ vẽ nét mực cho mùa đang chọn ở Cài đặt: xuân hoa đào, hạ lá, thu lá rơi, đông cành trơ và tuyết. */
export function InkSprig({ season, size = 40, className = '' }: { season: Exclude<Season, 'auto'>; size?: number; className?: string }) {
    const blossom = (x: number, y: number) =>
        [0, 72, 144, 216, 288].map((a) => {
            const r = (a * Math.PI) / 180;
            return <circle key={a} cx={x + 2.2 * Math.sin(r)} cy={y - 2.2 * Math.cos(r)} r="1.5" fill="var(--season)" />;
        });
    return (
        <svg width={size} height={size} viewBox="0 0 40 40" aria-hidden="true" className={`text-tim ${className}`} fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6 36c5-6 10-12 15-16s9-8 13-14" />
            <path d="M15 26c-1-3.5 0-6.5 2.8-8.4M24 17c3.2.4 5.6-.6 7.3-3" />
            {season === 'spring' && <g strokeWidth="0.9">{blossom(17.8, 15.4)}{blossom(31.4, 11.6)}{blossom(25, 24)}</g>}
            {season === 'summer' && (
                <>
                    <path d="M17.8 17.6c-3.4-.4-5.4-2.8-5.2-6 3.2.3 5.4 2.6 5.2 6z" fill="var(--season)" />
                    <path d="M31.3 14c.9-3.2 3.6-4.9 6.6-4.3-.7 3.1-3.4 4.8-6.6 4.3z" fill="var(--season)" />
                    <path d="M21 20.4c2.8 1.9 3.6 4.9 2.2 7.8-2.8-1.7-3.7-4.8-2.2-7.8z" fill="var(--season)" />
                </>
            )}
            {season === 'autumn' && (
                <>
                    <path d="M17.8 17.6c-3.4-.4-5.4-2.8-5.2-6 3.2.3 5.4 2.6 5.2 6z" fill="var(--season)" />
                    <path d="M30 31c-1.5-2.6-.8-5.3 1.8-6.6 1.3 2.6.5 5.3-1.8 6.6zM24 37c.2-2.4 2-3.8 4.4-3.6-.3 2.3-2 3.7-4.4 3.6z" fill="var(--season)" />
                </>
            )}
            {season === 'winter' && (
                <g fill="var(--season)" stroke="none">
                    <circle cx="10" cy="10" r="1.3" />
                    <circle cx="22" cy="6" r="1.1" />
                    <circle cx="33" cy="22" r="1.3" />
                    <circle cx="28" cy="32" r="1" />
                </g>
            )}
        </svg>
    );
}
