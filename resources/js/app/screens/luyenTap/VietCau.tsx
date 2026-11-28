import { useState } from 'react';
import { CongAi } from '../../components/ai/CongAi';
import { ConLuotAi, DoanAi, DsLoi, MucAiKq, NUT_AI, NUT_PHU_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { OHoiAi } from '../../components/ai/OHoiAi';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { t } from '../../i18n';
import { coTabAi, GIOI_HAN, vietCau, type KetQuaCau } from '../../lib/ai';
import type { TheTomTat } from '../../lib/noiDung';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { useTaiKhoan } from '../../state/taiKhoan';
import { KhongThay } from '../KhongThay';

type Diem = KetQuaCau['verdict'];

/** Bài tập Viết câu: mỗi lượt 5 từ; đặt câu với từng từ, AI chấm Đúng, Gần đúng, Chưa đúng và giải thích. */
export function VietCau() {
    const { toi, trangThai } = useTaiKhoan();
    useTitle(t('ai.vietCau.tieuDe'));
    if (trangThai === 'dang-tai') {
        return (
            <Sheet className="px-5 py-6 md:px-8">
                <DangTai />
            </Sheet>
        );
    }
    if (!coTabAi(toi?.ai)) return <KhongThay back={{ to: '/bai-tap', label: t('khung.baiTap') }} />;
    return (
        <Sheet className="mx-auto max-w-[720px] px-5 py-6 md:px-8">
            <h1 className="text-headline font-bold">{t('ai.vietCau.tieuDe')}</h1>
            <p className="mt-1 text-ink-2">{t('ai.vietCau.moTa')}</p>
            <div className="mt-5">
                <CongAi>
                    <Luot />
                </CongAi>
            </div>
        </Sheet>
    );
}

function Luot() {
    const ds = useApi<{ data: TheTomTat[] }>('/bai-tap/viet-cau?n=5');
    const [i, setI] = useState(0);
    const [ketQua, setKetQua] = useState<Diem[]>([]);

    if (!ds.duLieu) return ds.loi ? <LoiTai loi={ds.loi} taiLai={ds.taiLai} /> : <DangTai />;
    const the = ds.duLieu.data;
    if (the.length === 0) return <p>{t('ai.vietCau.khongCoTu')}</p>;

    if (i >= the.length) {
        const dem = (d: Diem) => ketQua.filter((k) => k === d).length;
        return (
            <div>
                <p role="status" className="text-title font-semibold">
                    {t('ai.vietCau.tongKet', { dung: dem('dung'), gan: dem('gan_dung'), sai: dem('sai') })}
                </p>
                <button
                    type="button"
                    onClick={() => {
                        setI(0);
                        setKetQua([]);
                        ds.taiLai();
                    }}
                    className={`mt-4 ${NUT_AI}`}
                >
                    {t('ai.vietCau.luotMoi')}
                </button>
            </div>
        );
    }

    return (
        <MotTu
            key={the[i].id}
            the={the[i]}
            tienDo={t('ai.vietCau.tienDo', { n: i + 1, tong: the.length })}
            cuoi={i === the.length - 1}
            onTiep={(d) => {
                setKetQua((cu) => (d ? [...cu, d] : cu));
                setI(i + 1);
            }}
        />
    );
}

function MotTu({ the, tienDo, cuoi, onTiep }: { the: TheTomTat; tienDo: string; cuoi: boolean; onTiep: (d: Diem | null) => void }) {
    const [chu, setChu] = useState('');
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<Awaited<ReturnType<typeof vietCau>>>();
    const d = kq?.data;

    return (
        <div>
            <p className="text-label text-ink-2">{tienDo}</p>
            <p lang="de" className="mt-1 text-headline font-bold">
                {the.front}
            </p>
            {the.meanings.vi && <p className="text-ink-2">{the.meanings.vi}</p>}
            <div className="mt-4">
                <OHoiAi
                    nhan={t('ai.vietCau.nhan', { tu: the.front })}
                    toiDa={GIOI_HAN.vietCau}
                    giaTri={chu}
                    onDoi={setChu}
                    de
                    rows={2}
                    nut={t('ai.vietCau.nut')}
                    dangGui={dangHoi}
                    onGui={(c) => void hoi(() => vietCau(the.id, c))}
                />
            </div>
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            {d && (
                <div aria-live="polite" className="mt-5 space-y-4">
                    <p className={`text-title font-bold ${d.verdict === 'sai' ? 'text-danger' : ''}`}>{t(`ai.vietCau.${d.verdict}`)}</p>
                    {!d.word_ok && <p className="font-semibold">{t('ai.vietCau.chuaDungTu')}</p>}
                    <MucAiKq tieuDe={t('ai.vietCau.daSua')} co={d.corrected !== ''}>
                        <DoanAi chu={d.corrected} de />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.vietCau.cacLoi')} co={d.errors.length > 0}>
                        <DsLoi ds={d.errors} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.vietCau.giaiThich')} co={d.explain !== ''}>
                        <DoanAi chu={d.explain} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.vietCau.cachDung')} co={d.use !== ''}>
                        <DoanAi chu={d.use} />
                    </MucAiKq>
                    <MucAiKq tieuDe={t('ai.vietCau.meo')} co={d.tip !== ''}>
                        <DoanAi chu={d.tip} />
                    </MucAiKq>
                </div>
            )}
            <div className="mt-5 flex flex-wrap gap-2">
                <button type="button" onClick={() => onTiep(d?.verdict ?? null)} className={NUT_PHU_AI}>
                    {cuoi ? t('ai.vietCau.xemKetQua') : t('ai.vietCau.tiep')}
                </button>
            </div>
            <ConLuotAi meta={kq?.meta} />
        </div>
    );
}
