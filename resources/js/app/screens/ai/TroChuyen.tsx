import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { NUT_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { Choice } from '../../components/Choice';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { t } from '../../i18n';
import { taoCuoc, TINH_HUONG, type CuocTroChuyen, type TinhHuong } from '../../lib/ai';
import { useApi } from '../../lib/useApi';

/** Tab Trò chuyện: chọn tình huống để mở cuộc mới, hoặc mở lại một cuộc đã có. */
export function TroChuyen() {
    const nav = useNavigate();
    const [tinhHuong, setTinhHuong] = useState<TinhHuong>('restaurant');
    const ds = useApi<{ data: CuocTroChuyen[] }>('/ai/tro-chuyen');
    const { dangHoi, loi, hoi, thuLai } = useHoiAi<{ data: CuocTroChuyen }>();

    const batDau = async () => {
        const r = await hoi(() => taoCuoc(tinhHuong));
        if (r) void nav(`/hoi-ai/tro-chuyen/${r.data.id}`);
    };

    return (
        <div>
            <Choice<TinhHuong>
                legend={t('ai.troChuyen.tinhHuong')}
                name="tinh-huong"
                value={tinhHuong}
                onChange={setTinhHuong}
                options={TINH_HUONG.map((v) => ({ value: v, label: t(`ai.troChuyen.${v}`) }))}
            />
            <button type="button" onClick={() => void batDau()} disabled={dangHoi} className={`mt-4 ${NUT_AI}`}>
                {t('ai.troChuyen.batDau')}
            </button>
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} />

            <section className="mt-8 border-t border-line pt-5" aria-labelledby="cac-cuoc">
                <h2 id="cac-cuoc" className="text-title font-bold">
                    {t('ai.troChuyen.cacCuoc')}
                </h2>
                {ds.duLieu ? (
                    ds.duLieu.data.length === 0 ? (
                        <p className="mt-2 text-ink-2">{t('ai.troChuyen.chuaCo')}</p>
                    ) : (
                        <ul className="mt-2 divide-y divide-line">
                            {ds.duLieu.data.map((c) => (
                                <li key={c.id}>
                                    <Link to={`/hoi-ai/tro-chuyen/${c.id}`} className="flex min-h-12 flex-wrap items-baseline gap-x-3 py-2 hover:underline hover:underline-offset-2">
                                        <span lang="de" className="font-semibold">
                                            {c.title ?? t(`ai.troChuyen.${c.scenario}`)}
                                        </span>
                                        <span className="text-label text-ink-2">
                                            {t(`ai.troChuyen.${c.scenario}`)} · {t('ai.troChuyen.soTin', { n: c.message_count })}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )
                ) : ds.loi ? (
                    <LoiTai loi={ds.loi} taiLai={ds.taiLai} />
                ) : (
                    <DangTai />
                )}
            </section>
        </div>
    );
}
