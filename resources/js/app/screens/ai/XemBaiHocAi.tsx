import { useId, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { DsViDu, MucAiKq, NUT_AI, NUT_PHU_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { Choice } from '../../components/Choice';
import { Sheet } from '../../components/Shell';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { t } from '../../i18n';
import { ghiDiemBaiHoc, xoaBaiHoc, type BaiHocDaLuu, type NoiDungBaiHoc } from '../../lib/ai';
import { strip } from '../../lib/baiTap';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { KhongThay } from '../KhongThay';

const VE = { to: '/hoi-ai?tab=bai-hoc', label: t('ai.baiHoc.veDanhSach') };
const giong = (a: string, b: string) => strip(a).toLocaleLowerCase('de') === strip(b).toLocaleLowerCase('de');

/** Một bài học AI đã soạn: bài đọc, từ mới, câu hỏi, bài tập điền từ; chấm ở máy rồi ghi điểm lên tài khoản. */
export default function XemBaiHocAi() {
    const { id = '' } = useParams();
    const kq = useApi<{ data: NoiDungBaiHoc; lesson: BaiHocDaLuu }>(/^\d+$/.test(id) ? `/ai/bai-hoc/${id}` : null);
    useTitle(kq.duLieu?.lesson.title ?? t('ai.trang.baiHoc'));

    if (!/^\d+$/.test(id) || kq.loi?.trangThai === 404) return <KhongThay message={t('ai.baiHoc.khongThay')} back={VE} />;
    if (!kq.duLieu) {
        return <Sheet className="px-5 py-6 md:px-8">{kq.loi ? <LoiTai loi={kq.loi} taiLai={kq.taiLai} /> : <DangTai />}</Sheet>;
    }
    return <Bai key={id} bai={kq.duLieu.data} luu={kq.duLieu.lesson} />;
}

function Bai({ bai, luu }: { bai: NoiDungBaiHoc; luu: BaiHocDaLuu }) {
    const nav = useNavigate();
    const uid = useId();
    const [chon, setChon] = useState<Record<number, number>>({});
    const [dien, setDien] = useState<Record<number, string>>({});
    const [diem, setDiem] = useState<{ ok: number; tong: number; diem: number } | null>(null);
    const [hoiXoa, setHoiXoa] = useState(false);
    const ghi = useHoiAi<unknown>();
    const xoa = useHoiAi<true>();

    const cham = () => {
        const tong = bai.questions.length + bai.exercise.length;
        const ok = bai.questions.filter((q, i) => chon[i] === q.answer).length + bai.exercise.filter((x, i) => giong(dien[i] ?? '', x.answer)).length;
        const d = tong === 0 ? 0 : Math.round((ok / tong) * 100);
        setDiem({ ok, tong, diem: d });
        void ghi.hoi(() => ghiDiemBaiHoc(luu.id, d));
    };

    return (
        <Sheet className="px-5 py-6 md:px-8">
            <Link to={VE.to} className="text-label font-semibold text-ink-2 hover:underline">
                ← {VE.label}
            </Link>
            <h1 className="mt-2 text-headline font-bold">{bai.title}</h1>
            {bai.level && <p className="mt-1 text-ink-2">{bai.level}</p>}

            <div className="mt-6 space-y-8">
                <MucAiKq tieuDe={t('ai.baiHoc.baiDoc')}>
                    <DsViDu ds={bai.text} />
                </MucAiKq>
                <MucAiKq tieuDe={t('ai.baiHoc.tuMoi')} co={bai.new_words.length > 0}>
                    <DsViDu ds={bai.new_words} />
                </MucAiKq>
                <MucAiKq tieuDe={t('ai.baiHoc.cauHoi')} co={bai.questions.length > 0}>
                    <div className="space-y-5">
                        {bai.questions.map((q, i) => (
                            <div key={i}>
                                <Choice<number>
                                    legend={q.q}
                                    name={`${uid}-cau-${i}`}
                                    value={chon[i] ?? -1}
                                    onChange={(v) => setChon({ ...chon, [i]: v })}
                                    options={q.choices.map((c, j) => ({ value: j, label: <span lang="de">{c}</span> }))}
                                />
                                {diem && (
                                    <p className={`mt-1 text-label font-semibold ${chon[i] === q.answer ? 'text-ink' : 'text-danger'}`}>
                                        {chon[i] === q.answer ? t('lamBai.dung') : t('ai.baiHoc.dapAn', { da: q.choices[q.answer] ?? '' })}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </MucAiKq>
                <MucAiKq tieuDe={t('ai.baiHoc.baiTap')} co={bai.exercise.length > 0}>
                    <ol className="space-y-4">
                        {bai.exercise.map((x, i) => (
                            <li key={i}>
                                <label htmlFor={`${uid}-dien-${i}`} className="block" lang="de">
                                    <span className="sr-only">{t('ai.baiHoc.oTrong', { n: i + 1 })}: </span>
                                    {x.de}
                                </label>
                                <input
                                    id={`${uid}-dien-${i}`}
                                    lang="de"
                                    value={dien[i] ?? ''}
                                    autoComplete="off"
                                    onChange={(e) => setDien({ ...dien, [i]: e.target.value })}
                                    className="mt-1.5 block min-h-11 w-full max-w-sm rounded-md border border-line-strong bg-paper px-3 text-body"
                                />
                                {diem && (
                                    <p className={`mt-1 text-label font-semibold ${giong(dien[i] ?? '', x.answer) ? 'text-ink' : 'text-danger'}`}>
                                        {giong(dien[i] ?? '', x.answer) ? t('lamBai.dung') : t('ai.baiHoc.dapAn', { da: x.answer })}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </MucAiKq>
            </div>

            <div className="mt-8 border-t border-line pt-5">
                <button type="button" onClick={cham} className={NUT_AI}>
                    {t('ai.baiHoc.chamBai')}
                </button>
                {diem && (
                    <p role="status" className="mt-3 font-semibold">
                        {t('ai.baiHoc.ketQua', diem)}
                    </p>
                )}
                <TrangThaiHoi dangHoi={false} loi={ghi.loi} onThuLai={() => void ghi.thuLai()} />
            </div>

            <div className="mt-8 border-t border-line pt-5">
                {hoiXoa ? (
                    <div role="group" aria-label={t('ai.baiHoc.xoa')}>
                        <p className="font-semibold">{t('ai.baiHoc.xoaHoi')}</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                disabled={xoa.dangHoi}
                                onClick={async () => {
                                    const xong = await xoa.hoi(async () => {
                                        await xoaBaiHoc(luu.id);
                                        return true as const;
                                    });
                                    if (xong) void nav(VE.to);
                                }}
                                className="inline-flex min-h-11 items-center rounded-md border border-danger bg-sheet px-4 font-semibold text-danger hover:bg-danger-tint"
                            >
                                {t('ai.baiHoc.xoa')}
                            </button>
                            <button type="button" onClick={() => setHoiXoa(false)} className={NUT_PHU_AI}>
                                {t('chung.huy')}
                            </button>
                        </div>
                        <TrangThaiHoi dangHoi={false} loi={xoa.loi} onThuLai={() => void xoa.thuLai()} />
                    </div>
                ) : (
                    <button type="button" onClick={() => setHoiXoa(true)} className={NUT_PHU_AI}>
                        {t('ai.baiHoc.xoa')}
                    </button>
                )}
            </div>
        </Sheet>
    );
}
