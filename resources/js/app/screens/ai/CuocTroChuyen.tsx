import { flushSync } from 'react-dom';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { DsLoi, NUT_PHU_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { OHoiAi } from '../../components/ai/OHoiAi';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { t } from '../../i18n';
import { GIOI_HAN, guiTin, xoaCuoc, type CuocTroChuyen as Cuoc, type TinNhan } from '../../lib/ai';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { KhongThay } from '../KhongThay';

const VE = { to: '/hoi-ai?tab=tro-chuyen', label: t('ai.troChuyen.veDanhSach') };

/** Một cuộc trò chuyện: tin của bạn (kèm phần sửa câu), tin của AI bằng tiếng Đức (bấm để hiện nghĩa), gợi ý câu tiếp. */
export default function CuocTroChuyen() {
    const { ma = '' } = useParams();
    const kq = useApi<{ data: { conversation: Cuoc; messages: TinNhan[] } }>(`/ai/tro-chuyen/${ma}`);
    useTitle(t('ai.trang.troChuyen'));

    if (kq.loi?.trangThai === 404) return <KhongThay message={t('ai.troChuyen.khongThay')} back={VE} />;
    if (!kq.duLieu) {
        return <Sheet className="px-5 py-6 md:px-8">{kq.loi ? <LoiTai loi={kq.loi} taiLai={kq.taiLai} /> : <DangTai />}</Sheet>;
    }
    return <TrongCuoc key={ma} cuoc={kq.duLieu.data.conversation} tinDau={kq.duLieu.data.messages} />;
}

function TrongCuoc({ cuoc, tinDau }: { cuoc: Cuoc; tinDau: TinNhan[] }) {
    const nav = useNavigate();
    const [tin, setTin] = useState(tinDau);
    const [chu, setChu] = useState('');
    const [hoiXoa, setHoiXoa] = useState(false);
    const cuoiRef = useRef<HTMLLIElement>(null);
    const vungNhap = useRef<HTMLDivElement>(null);
    const { dangHoi, loi, hoi, thuLai } = useHoiAi<Awaited<ReturnType<typeof guiTin>>>();
    const xoa = useHoiAi<true>();

    useEffect(() => {
        cuoiRef.current?.scrollIntoView?.({ block: 'nearest' });
    }, [tin.length]);

    const gui = async (c: string) => {
        const r = await hoi(() => guiTin(cuoc.id, c));
        if (!r) return;
        flushSync(() => {
            setTin((cu) => [...cu, ...r.messages]);
            setChu('');
        });
        vungNhap.current?.querySelector<HTMLElement>('textarea')?.focus();
    };

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <Link to={VE.to} className="text-label font-semibold text-ink-2 hover:underline">
                ← {VE.label}
            </Link>
            <h1 className="mt-2 text-headline font-bold">{t(`ai.troChuyen.${cuoc.scenario}`)}</h1>
            <p className="mt-1 text-label text-ink-2">{t('ai.troChuyen.chiDoc10')}</p>

            <ol aria-live="polite" className="mt-5 space-y-4">
                {tin.length === 0 && <li className="text-ink-2">{t('ai.troChuyen.moDau')}</li>}
                {tin.map((m, i) => (
                    <li key={m.id} ref={i === tin.length - 1 ? cuoiRef : undefined}>
                        {m.role === 'user' ? <TinCuaBan m={m} sau={tin[i + 1]} /> : <TinCuaAi m={m} />}
                    </li>
                ))}
            </ol>

            <div ref={vungNhap} className="mt-6 border-t border-line pt-5">
                <OHoiAi
                    nhan={t('ai.troChuyen.nhan')}
                    toiDa={GIOI_HAN.troChuyen}
                    giaTri={chu}
                    onDoi={setChu}
                    de
                    rows={3}
                    nut={t('ai.troChuyen.gui')}
                    dangGui={dangHoi}
                    onGui={(c) => void gui(c)}
                />
                <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            </div>

            <div className="mt-8 border-t border-line pt-5">
                {hoiXoa ? (
                    <div role="group" aria-label={t('ai.troChuyen.xoa')}>
                        <p className="font-semibold">{t('ai.troChuyen.xoaHoi')}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                disabled={xoa.dangHoi}
                                onClick={async () => {
                                    const xong = await xoa.hoi(async () => {
                                        await xoaCuoc(cuoc.id);
                                        return true as const;
                                    });
                                    if (xong) void nav(VE.to);
                                }}
                                className="inline-flex min-h-11 items-center rounded-md border border-danger bg-sheet px-4 font-semibold text-danger hover:bg-danger-tint"
                            >
                                {t('ai.troChuyen.xoaThat')}
                            </button>
                            <button type="button" onClick={() => setHoiXoa(false)} className={NUT_PHU_AI}>
                                {t('chung.huy')}
                            </button>
                        </div>
                        <TrangThaiHoi dangHoi={false} loi={xoa.loi} onThuLai={() => void xoa.thuLai()} />
                    </div>
                ) : (
                    <button type="button" onClick={() => setHoiXoa(true)} className={NUT_PHU_AI}>
                        {t('ai.troChuyen.xoa')}
                    </button>
                )}
            </div>
        </Sheet>
    );
}

function TinCuaBan({ m, sau }: { m: TinNhan; sau: TinNhan | undefined }) {
    const loi = sau?.role === 'assistant' ? (sau.extra?.errors ?? []) : [];
    return (
        <div className="ml-auto max-w-[85%] rounded-md border border-line bg-sunken px-4 py-3">
            <p className="text-label font-semibold text-ink-2">{t('ai.troChuyen.ban')}</p>
            <p lang="de" className="whitespace-pre-line">
                {m.content}
            </p>
            {loi.length > 0 && (
                <div className="mt-2 border-t border-line pt-2">
                    <p className="text-label font-semibold text-ink-2">{t('ai.troChuyen.suaCau')}</p>
                    <div className="mt-1">
                        <DsLoi ds={loi} />
                    </div>
                </div>
            )}
        </div>
    );
}

function TinCuaAi({ m }: { m: TinNhan }) {
    const [nghia, setNghia] = useState(false);
    return (
        <div className="max-w-[85%] rounded-md border border-line bg-sheet px-4 py-3">
            <p className="text-label font-semibold text-ink-2">{t('ai.troChuyen.ai')}</p>
            <p lang="de" className="whitespace-pre-line">
                {m.content}
            </p>
            {m.extra?.vi && (
                <>
                    <button type="button" aria-expanded={nghia} onClick={() => setNghia(!nghia)} className="mt-1 min-h-11 text-label font-semibold underline underline-offset-2">
                        {nghia ? t('ai.troChuyen.anNghia') : t('ai.troChuyen.hienNghia')}
                    </button>
                    {nghia && <p className="text-ink-2">{m.extra.vi}</p>}
                </>
            )}
            {m.extra?.hint && (
                <p className="mt-1 text-label text-ink-2">
                    {t('ai.troChuyen.goiY', { cau: '' })}
                    <span lang="de">{m.extra.hint}</span>
                </p>
            )}
        </div>
    );
}
