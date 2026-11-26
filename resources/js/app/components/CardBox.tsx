import { LEVEL_NAMES, STAGE_NAMES } from '../lib/srs';
import { LevelIcon } from './LevelIcon';

/**
 * Hộp thẻ 6 ngăn nhìn từ trên (hộp Leitner): mỗi ngăn là một mức thuộc, chồng thẻ cao theo số từ,
 * tai thẻ ghi tên mức kèm hình cây nét mực, số thẻ đến hạn của ngăn ghi trong ô trắng chữ bút đỏ.
 * Chỉ để xem: trình đọc màn hình nghe một câu tóm tắt.
 */
export function CardBox({ counts, due, fresh }: { counts: number[]; due: number[]; fresh: number }) {
    const max = Math.max(...counts, 1);
    const said = counts
        .map((n, i) => {
            const d = i === 0 ? (fresh > 0 ? `, ${fresh} sắp học` : '') : due[i] > 0 ? `, ${due[i]} đến hạn` : '';
            return `${STAGE_NAMES[i]} (${LEVEL_NAMES[i]}) ${n} thẻ${d}`;
        })
        .join('; ');
    return (
        <figure role="img" aria-label={`Hộp thẻ theo mức thuộc: ${said}`} className="rounded-md bg-box px-2.5 pt-3 text-on-box">
            <div className="grid h-36 grid-cols-6 items-end gap-1.5" aria-hidden="true">
                {counts.map((n, i) => {
                    const mark = i === 0 ? fresh : due[i];
                    // Chồng thẻ cao 14–100% phần còn lại của ngăn; ngăn trống vẫn có đáy để thấy là có ngăn
                    const h = n === 0 ? 0 : 14 + Math.round((n / max) * 70);
                    return (
                        <div key={i} className="relative flex h-full min-w-0 flex-col justify-end">
                            {mark > 0 && (
                                <span className="mb-1 self-end rounded-sm bg-on-box px-1.5 text-[calc(0.75rem*var(--type-scale))] leading-5 font-bold text-box-pen tabular-nums">
                                    {i === 0 ? `+${mark}` : mark}
                                </span>
                            )}
                            <span
                                className="flex items-center gap-0.5 self-start rounded-t-sm px-1 pt-0.5 text-[calc(0.6875rem*var(--type-scale))] leading-4 font-semibold"
                                style={{ background: `var(--color-level-${i})`, color: `var(--color-on-level-${i})` }}
                            >
                                <LevelIcon level={i} size={12} title="" />
                                {STAGE_NAMES[i]}
                            </span>
                            <span
                                className="block w-full rounded-tr-sm border-t border-box"
                                style={{
                                    height: `${h}%`,
                                    minHeight: 3,
                                    background: 'repeating-linear-gradient(to top, var(--color-on-box) 0 3px, color-mix(in oklch, var(--color-on-box) 72%, var(--color-box)) 3px 4px)',
                                }}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="grid grid-cols-6 gap-1.5 py-1.5 text-center text-label font-semibold tabular-nums" aria-hidden="true">
                {counts.map((n, i) => (
                    <span key={i}>{n}</span>
                ))}
            </div>
        </figure>
    );
}
