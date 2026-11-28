import { useId, useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router';
import { NUT_AI, TrangThaiHoi, useHoiAi } from '../../components/ai/KetQuaAi';
import { Choice } from '../../components/Choice';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { t, type Khoa } from '../../i18n';
import { coChu, GIOI_HAN, soanBaiHoc, TOI_DA_TU, type BaiHocDaLuu } from '../../lib/ai';
import { useApi } from '../../lib/useApi';
import { useTaiKhoan } from '../../state/taiKhoan';

const GOI_Y: Khoa[] = ['ai.baiHoc.anUong', 'ai.baiHoc.muaSam', 'ai.baiHoc.diLai', 'ai.baiHoc.congViec', 'ai.baiHoc.sucKhoe', 'ai.baiHoc.nhaO', 'ai.baiHoc.giaDinh', 'ai.baiHoc.thoiGianRanh'];
const CAP = ['A1', 'A2', 'B1', 'B2'] as const;
const TU_HOP_LE = /^[\p{L}\p{M}][\p{L}\p{M} '-]*$/u;

/** Tách ô từ thành danh sách (dấu phẩy hay xuống dòng), bỏ ô trống. */
export function tachTu(chu: string): string[] {
    return chu
        .split(/[,\n]/)
        .map((s) => s.trim())
        .filter((s) => s !== '');
}

/** Tab Bài học AI: chủ đề, trình độ, tối đa 10 từ; danh sách bài đã soạn kèm điểm. */
export function BaiHocAi() {
    const nav = useNavigate();
    const uid = useId();
    const { toi } = useTaiKhoan();
    const capCuaToi = toi?.course?.level;
    const [chuDe, setChuDe] = useState('');
    const [cap, setCap] = useState<(typeof CAP)[number]>(capCuaToi && capCuaToi >= 1 && capCuaToi <= 4 ? CAP[capCuaToi - 1] : 'A1');
    const [tu, setTu] = useState('');
    const [loiO, setLoiO] = useState<{ o: 'chuDe' | 'tu'; chu: string } | null>(null);
    const ds = useApi<{ data: BaiHocDaLuu[] }>('/ai/bai-hoc');
    const { dangHoi, loi, hoi, thuLai } = useHoiAi<Awaited<ReturnType<typeof soanBaiHoc>>>();

    const cd = chuDe.trim();
    const quaDai = cd.length > GIOI_HAN.chuDe;

    const gui = async (e: FormEvent) => {
        e.preventDefault();
        const cacTu = tachTu(tu);
        if (cd === '') return setLoiO({ o: 'chuDe', chu: t('ai.o.oTrong') });
        if (quaDai) return;
        if (!coChu(cd)) return setLoiO({ o: 'chuDe', chu: t('ai.o.canChu') });
        if (cacTu.length > TOI_DA_TU) return setLoiO({ o: 'tu', chu: t('ai.baiHoc.quaNhieuTu') });
        if (cacTu.some((w) => w.length > GIOI_HAN.tu || !TU_HOP_LE.test(w))) return setLoiO({ o: 'tu', chu: t('ai.baiHoc.tuSai') });
        setLoiO(null);
        const r = await hoi(() => soanBaiHoc(cd, cap, cacTu));
        if (r) void nav(`/hoi-ai/bai-hoc/${r.lesson.id}`);
    };

    const loiChuDe = quaDai ? t('ai.o.daiQua', { toiDa: GIOI_HAN.chuDe }) : loiO?.o === 'chuDe' ? loiO.chu : null;
    const loiTu = loiO?.o === 'tu' ? loiO.chu : null;

    return (
        <div>
            <form onSubmit={(e) => void gui(e)} noValidate className="space-y-5">
                <div>
                    <label htmlFor={`${uid}-chu-de`} className="block text-label font-semibold">
                        {t('ai.baiHoc.chuDe')}
                    </label>
                    <input
                        id={`${uid}-chu-de`}
                        value={chuDe}
                        autoComplete="off"
                        onChange={(e) => {
                            setLoiO(null);
                            setChuDe(e.target.value);
                        }}
                        aria-invalid={loiChuDe ? true : undefined}
                        aria-describedby={`${uid}-chu-de-dem${loiChuDe ? ` ${uid}-chu-de-loi` : ''}`}
                        className="mt-1.5 block min-h-11 w-full rounded-md border border-line-strong bg-paper px-3 text-body"
                    />
                    <p id={`${uid}-chu-de-dem`} className="mt-1 text-label text-ink-2 tabular-nums">
                        {t('ai.o.demKyTu', { n: cd.length, toiDa: GIOI_HAN.chuDe })}
                    </p>
                    {loiChuDe && (
                        <p id={`${uid}-chu-de-loi`} role="alert" className="mt-1 text-label font-semibold text-danger">
                            {loiChuDe}
                        </p>
                    )}
                    <div role="group" aria-label={t('ai.baiHoc.goiY')} className="mt-2 flex flex-wrap gap-1.5">
                        {GOI_Y.map((k) => (
                            <button key={k} type="button" onClick={() => setChuDe(t(k))} className="min-h-11 rounded-md border border-line px-3 text-label hover:bg-sunken">
                                {t(k)}
                            </button>
                        ))}
                    </div>
                </div>
                <Choice legend={t('ai.baiHoc.trinhDo')} name="cap-bai-hoc" value={cap} onChange={setCap} options={CAP.map((c) => ({ value: c, label: c }))} />
                <div>
                    <label htmlFor={`${uid}-tu`} className="block text-label font-semibold">
                        {t('ai.baiHoc.tu')}
                    </label>
                    <input
                        id={`${uid}-tu`}
                        lang="de"
                        value={tu}
                        autoComplete="off"
                        onChange={(e) => {
                            setLoiO(null);
                            setTu(e.target.value);
                        }}
                        aria-invalid={loiTu ? true : undefined}
                        aria-describedby={loiTu ? `${uid}-tu-loi` : undefined}
                        className="mt-1.5 block min-h-11 w-full rounded-md border border-line-strong bg-paper px-3 text-body"
                    />
                    {loiTu && (
                        <p id={`${uid}-tu-loi`} role="alert" className="mt-1 text-label font-semibold text-danger">
                            {loiTu}
                        </p>
                    )}
                </div>
                <button type="submit" disabled={dangHoi || quaDai} className={NUT_AI}>
                    {t('ai.baiHoc.nut')}
                </button>
            </form>
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="topic" />

            <section className="mt-8 border-t border-line pt-5" aria-labelledby={`${uid}-da-luu`}>
                <h2 id={`${uid}-da-luu`} className="text-title font-bold">
                    {t('ai.baiHoc.daLuu')}
                </h2>
                {ds.duLieu ? (
                    ds.duLieu.data.length === 0 ? (
                        <p className="mt-2 text-ink-2">{t('ai.baiHoc.chuaCo')}</p>
                    ) : (
                        <ul className="mt-2 divide-y divide-line">
                            {ds.duLieu.data.map((b) => (
                                <li key={b.id}>
                                    <Link to={`/hoi-ai/bai-hoc/${b.id}`} className="flex min-h-12 flex-wrap items-baseline gap-x-3 py-2 hover:underline hover:underline-offset-2">
                                        <span className="font-semibold">{b.title}</span>
                                        <span className="text-label text-ink-2">
                                            {b.level ?? ''} · {b.score !== null ? t('ai.baiHoc.diem', { n: b.score }) : t('ai.baiHoc.chuaLam')}
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
