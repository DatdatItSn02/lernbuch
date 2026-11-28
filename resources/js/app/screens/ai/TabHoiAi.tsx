import { useEffect, useRef } from 'react';
import { CongAi } from '../../components/ai/CongAi';
import { ConLuotAi, DsViDu, MucAiKq, NUT_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { t } from '../../i18n';
import { giaiThichTu, type GiaiThichTu, type MetaAi } from '../../lib/ai';

/** Tab Hỏi AI của thẻ từ: qua cổng (ngày sinh, đồng ý) rồi bấm "Giải thích từ này". Kết quả luôn hiện như chữ. */
export default function TabHoiAi({ cardId }: { cardId: number }) {
    return (
        <CongAi>
            <GiaiThich cardId={cardId} />
        </CongAi>
    );
}

/** Nút "Giải thích từ này" và phần giải thích; dùng ở tab Hỏi AI và ngay trong phiên học. tuDong: hỏi ngay khi hiện. */
export function GiaiThich({ cardId, tuDong = false }: { cardId: number; tuDong?: boolean }) {
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<{ data: GiaiThichTu; meta: MetaAi }>();
    const daGoi = useRef(false);
    useEffect(() => {
        if (!tuDong || daGoi.current) return;
        daGoi.current = true;
        void hoi(() => giaiThichTu(cardId));
    }, [tuDong, hoi, cardId]);
    return (
        <div>
            <button type="button" onClick={() => void hoi(() => giaiThichTu(cardId))} disabled={dangHoi} className={NUT_AI}>
                {t('ai.giaiThich')}
            </button>
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} />
            {kq && <NoiDungGiaiThich d={kq.data} />}
            <ConLuotAi meta={kq?.meta} />
        </div>
    );
}

export function NoiDungGiaiThich({ d }: { d: GiaiThichTu }) {
    return (
        <div aria-live="polite" className="mt-5 space-y-5">
            <MucAiKq tieuDe={t('ai.nghia')}>
                <p className="whitespace-pre-line">{d.meaning}</p>
            </MucAiKq>
            <MucAiKq tieuDe={t('ai.cachDung')} co={d.usage !== ''}>
                <p className="whitespace-pre-line">{d.usage}</p>
            </MucAiKq>
            <MucAiKq tieuDe={t('ai.viDu')} co={d.examples.length > 0}>
                <DsViDu ds={d.examples} />
            </MucAiKq>
            <MucAiKq tieuDe={t('ai.loiHayGap')} co={d.common_mistakes.length > 0}>
                <ul className="list-disc space-y-1 pl-5">
                    {d.common_mistakes.map((l, i) => (
                        <li key={i}>{l}</li>
                    ))}
                </ul>
            </MucAiKq>
        </div>
    );
}
