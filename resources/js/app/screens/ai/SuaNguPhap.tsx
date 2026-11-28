import { useState } from 'react';
import { ConLuotAi, DoanAi, DsLoi, MucAiKq, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { OHoiAi } from '../../components/ai/OHoiAi';
import { t } from '../../i18n';
import { GIOI_HAN, suaNguPhap, type KetQuaAi, type KetQuaSua } from '../../lib/ai';

/** Sửa ngữ pháp một đoạn tiếng Đức: đoạn đã sửa, từng lỗi (sai → đúng, vì sao), cách nói tự nhiên hơn. */
export function SuaNguPhap() {
    const [chu, setChu] = useState('');
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<KetQuaAi<KetQuaSua>>();
    const d = kq?.data;

    return (
        <div>
            <OHoiAi
                nhan={t('ai.sua.nhan')}
                toiDa={GIOI_HAN.sua}
                giaTri={chu}
                onDoi={setChu}
                de
                rows={6}
                nut={t('ai.sua.nut')}
                dangGui={dangHoi}
                onGui={(c) => void hoi(() => suaNguPhap(c))}
            />
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            {d && (
                <div aria-live="polite" className="mt-5 space-y-5">
                    <MucAiKq tieuDe={t('ai.sua.daSua')}>
                        <DoanAi chu={d.corrected} de />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.sua.cacLoi')}>{d.errors.length > 0 ? <DsLoi ds={d.errors} /> : <p>{t('ai.sua.khongLoi')}</p>}</MucAiKq>
                    <MucAiKq tieuDe={t('ai.sua.tuNhien')} co={d.natural !== ''}>
                        <DoanAi chu={d.natural} de />
                    </MucAiKq>
                </div>
            )}
            <ConLuotAi meta={kq?.meta} />
        </div>
    );
}
