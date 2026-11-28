import { useId, useState } from 'react';
import { ConLuotAi, DoanAi, DsViDu, MucAiKq, NUT_AI, TrangThaiHoi, useHoiAi } from './KetQuaAi';
import { CongAi } from './CongAi';
import { OHoiAi } from './OHoiAi';
import { t } from '../../i18n';
import { giaiThichNguPhap, GIOI_HAN, hoiNguPhap, type KetQuaAi, type KetQuaDoan, type KetQuaHoi } from '../../lib/ai';

/** Nút "Hỏi AI về phần này" sau mỗi tiêu đề của bài ngữ pháp; mở ra: giải thích phần này, hoặc hỏi câu của mình. */
export function HoiNguPhap({ slug, phan, ten }: { slug: string; phan: number; ten: string }) {
    const [mo, setMo] = useState(false);
    const id = useId();
    return (
        <div className="mt-2">
            <button
                type="button"
                aria-expanded={mo}
                aria-controls={id}
                aria-label={ten ? t('ai.nguPhap.nutTen', { ten }) : undefined}
                onClick={() => setMo(!mo)}
                className="min-h-11 text-label font-semibold underline underline-offset-2"
            >
                {t('ai.nguPhap.nut')}
            </button>
            {mo && (
                <div id={id} className="mt-2 border-y border-line py-3">
                    <CongAi>
                        <Khung slug={slug} phan={phan} />
                    </CongAi>
                </div>
            )}
        </div>
    );
}

function Khung({ slug, phan }: { slug: string; phan: number }) {
    const giai = useHoiAi<KetQuaAi<KetQuaDoan>>();
    const hoi = useHoiAi<KetQuaAi<KetQuaHoi>>();
    const [cau, setCau] = useState('');
    const d = giai.kq?.data;
    const tl = hoi.kq?.data;
    return (
        <div>
            <button type="button" onClick={() => void giai.hoi(() => giaiThichNguPhap(slug, phan))} disabled={giai.dangHoi} className={NUT_AI}>
                {t('ai.nguPhap.giaiThich')}
            </button>
            <TrangThaiHoi dangHoi={giai.dangHoi} loi={giai.loi} onThuLai={() => void giai.thuLai()} />
            {d && (
                <div aria-live="polite" className="mt-4 space-y-4">
                    <MucAiKq tieuDe={t('ai.nguPhap.tomTat')}>
                        <DoanAi chu={d.summary} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.nguPhap.yChinh')} co={d.points.length > 0}>
                        <ul className="list-disc space-y-1 pl-5">
                            {d.points.map((p, i) => (
                                <li key={i}>{p}</li>
                            ))}
                        </ul>
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.viDu')} co={d.examples.length > 0}>
                        <DsViDu ds={d.examples} />
                    </MucAiKq>
                </div>
            )}
            <div className="mt-5">
                <OHoiAi
                    nhan={t('ai.nguPhap.cauHoi')}
                    toiDa={GIOI_HAN.hoiNguPhap}
                    giaTri={cau}
                    onDoi={setCau}
                    rows={2}
                    nut={t('ai.nguPhap.hoi')}
                    dangGui={hoi.dangHoi}
                    onGui={(c) => void hoi.hoi(() => hoiNguPhap(slug, c))}
                />
                <TrangThaiHoi dangHoi={hoi.dangHoi} loi={hoi.loi} onThuLai={() => void hoi.thuLai()} o="question" />
                {tl && (
                    <div aria-live="polite" className="mt-4 space-y-4">
                        <MucAiKq tieuDe={t('ai.nguPhap.traLoi')}>
                            <DoanAi chu={tl.answer} />
                        </MucAiKq>
                        <MucAiKq tieuDe={t('ai.viDu')} co={tl.examples.length > 0}>
                            <DsViDu ds={tl.examples} />
                        </MucAiKq>
                    </div>
                )}
            </div>
            <ConLuotAi meta={hoi.kq?.meta ?? giai.kq?.meta} />
        </div>
    );
}
