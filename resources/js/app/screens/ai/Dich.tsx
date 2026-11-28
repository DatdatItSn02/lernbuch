import { useState } from 'react';
import { ConLuotAi, DoanAi, MucAiKq, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { OHoiAi } from '../../components/ai/OHoiAi';
import { Choice } from '../../components/Choice';
import { t } from '../../i18n';
import { dich, GIOI_HAN, type KetQuaAi, type KetQuaDich } from '../../lib/ai';

/** Dịch Việt ↔ Đức, kèm dịch sát chữ, nghĩa từng từ, ghi chú ngữ pháp, cách nói tự nhiên hơn. */
export function Dich() {
    const [chu, setChu] = useState('');
    const [chieu, setChieu] = useState<'de' | 'vi'>('de');
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<KetQuaAi<KetQuaDich>>();
    const d = kq?.data;
    const ra = chieu;

    return (
        <div>
            <OHoiAi
                nhan={t('ai.dich.nhan')}
                toiDa={GIOI_HAN.dich}
                giaTri={chu}
                onDoi={setChu}
                de={chieu === 'vi'}
                nut={t('ai.dich.nut')}
                dangGui={dangHoi}
                onGui={(c) => void hoi(() => dich(c, chieu))}
                them={
                    <Choice<'de' | 'vi'>
                        legend={t('ai.dich.chieu')}
                        name="chieu-dich"
                        value={chieu}
                        onChange={setChieu}
                        options={[
                            { value: 'de', label: t('ai.dich.viDe') },
                            { value: 'vi', label: t('ai.dich.deVi') },
                        ]}
                        className="mt-4"
                    />
                }
            />
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            {d && (
                <div aria-live="polite" className="mt-5 space-y-5">
                    <MucAiKq tieuDe={t('ai.dich.banDich')}>
                        <p lang={ra === 'de' ? 'de' : undefined} className="text-title font-semibold whitespace-pre-line">
                            {d.translation}
                        </p>
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.dich.satChu')} co={d.literal !== ''}>
                        <DoanAi chu={d.literal} de={ra === 'de'} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.dich.tungTu')} co={d.words.length > 0}>
                        <ul className="divide-y divide-line">
                            {d.words.map((w, i) => (
                                <li key={i} className="py-1.5">
                                    <span lang="de" className="font-semibold">
                                        {w.de}
                                    </span>{' '}
                                    · {w.vi}
                                    {w.note && <span className="block text-ink-2">{w.note}</span>}
                                </li>
                            ))}
                        </ul>
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.dich.nguPhap')} co={d.grammar !== ''}>
                        <DoanAi chu={d.grammar} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.dich.tuNhien')} co={d.natural !== ''}>
                        <DoanAi chu={d.natural} de={ra === 'de'} />
                    </MucAiKq>
                </div>
            )}
            <ConLuotAi meta={kq?.meta} />
        </div>
    );
}
