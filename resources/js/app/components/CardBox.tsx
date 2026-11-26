import { levelName, stageName } from '../lib/srs';
import { t } from '../i18n';

const EDGE = 'color-mix(in oklch, var(--color-on-box) 62%, var(--color-box))';

/**
 * Hộp thẻ 6 ngăn nhìn từ trên (hộp Leitner): mỗi ngăn là một mức thuộc, chồng thẻ cao theo số từ,
 * tai thẻ ghi tên mức (hình cây quá nhỏ ở cỡ này nên chỉ ghi chữ), số thẻ đến hạn của ngăn ghi trong ô trắng chữ bút đỏ.
 * Chỉ để xem: trình đọc màn hình nghe một câu tóm tắt.
 */
export function CardBox({ counts, due, fresh }: { counts: number[]; due: number[]; fresh: number }) {
    const max = Math.max(...counts, 1);
    const said = counts
        .map((n, i) => {
            const d = i === 0 ? (fresh > 0 ? t('hopThe.sapHoc', { n: fresh }) : '') : due[i] > 0 ? t('hopThe.denHan', { n: due[i] }) : '';
            return t('hopThe.ngan', { giaiDoan: stageName(i), muc: levelName(i), n }) + d;
        })
        .join('; ');
    return (
        <figure role="img" aria-label={t('hopThe.tomTat', { cacNgan: said })} className="rounded-md bg-box px-2.5 pt-3 text-on-box">
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
                                className="flex items-center gap-0.5 self-start rounded-t-sm px-1 pt-0.5 text-[calc(0.6875rem*var(--type-scale))] leading-[1.125rem] font-semibold"
                                style={{ background: `var(--color-level-${i})`, color: `var(--color-on-level-${i})` }}
                            >
                                {stageName(i)}
                            </span>
                            <span
                                className="block w-full rounded-tr-sm border-t border-box"
                                style={{
                                    height: `${h}%`,
                                    minHeight: 3,
                                    // Mép trên của vài tấm thẻ: hai vạch mảnh ở đầu chồng
                                    background: 'var(--color-on-box)',
                                    boxShadow: [3, 4, 7, 8].map((y, k) => `inset 0 ${y}px 0 ${k % 2 ? EDGE : 'var(--color-on-box)'}`).join(', '),
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
