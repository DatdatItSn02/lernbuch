import { ConLuotAi, DsViDu, MucAiKq, NUT_AI, TrangThaiHoi, useHoiAi } from './KetQuaAi';
import { CongAi } from './CongAi';
import { t } from '../../i18n';
import { traTuAi, type KetQuaAi, type KetQuaTraTu } from '../../lib/ai';

const MAO_TU = { m: 'der', f: 'die', n: 'das', '': '' } as const;

/** Tra từ AI: hiện khi từ điển của web không có từ đang tìm. */
export function TraTuAi({ tu }: { tu: string }) {
    return (
        <CongAi>
            <TraLoi key={tu} tu={tu} />
        </CongAi>
    );
}

function TraLoi({ tu }: { tu: string }) {
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<KetQuaAi<KetQuaTraTu>>();
    const d = kq?.data;
    return (
        <div>
            <p>{t('ai.traTu.khongCo', { tu })}</p>
            <button type="button" onClick={() => void hoi(() => traTuAi(tu))} disabled={dangHoi} className={`mt-3 ${NUT_AI}`}>
                {t('ai.traTu.nut')}
            </button>
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            {d && (
                <div aria-live="polite" className="mt-5 space-y-4">
                    <p lang="de" className="text-headline font-bold">
                        {MAO_TU[d.gender] ? `${MAO_TU[d.gender]} ` : ''}
                        {d.lemma}
                    </p>
                    <p className="text-ink-2">
                        {[d.pos && t('ai.traTu.loaiTu', { chu: d.pos }), d.plural && t('ai.traTu.soNhieu', { chu: d.plural }), d.level && t('ai.traTu.trinhDo', { cap: d.level })]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <MucAiKq tieuDe={t('ai.traTu.nghia')}>
                        <ul className="list-disc space-y-1 pl-5">
                            {d.meanings_vi.map((m, i) => (
                                <li key={i}>{m}</li>
                            ))}
                        </ul>
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.traTu.cacDang')} co={d.forms.length > 0}>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                            {d.forms.map((f, i) => (
                                <div key={i} className="contents">
                                    <dt className="text-ink-2">{f.label}</dt>
                                    <dd lang="de">{f.form}</dd>
                                </div>
                            ))}
                        </dl>
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.viDu')} co={d.examples.length > 0}>
                        <DsViDu ds={d.examples} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.traTu.cumTu')} co={d.collocations.length > 0}>
                        <ul lang="de" className="list-disc space-y-1 pl-5">
                            {d.collocations.map((c, i) => (
                                <li key={i}>{c}</li>
                            ))}
                        </ul>
                    </MucAiKq>
                </div>
            )}
            <ConLuotAi meta={kq?.meta} />
        </div>
    );
}
