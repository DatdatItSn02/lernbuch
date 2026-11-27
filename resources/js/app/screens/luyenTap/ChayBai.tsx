// Làm bài tập theo Lernbuch (exercises.js): chấm ngay từng câu (drive: câu sai quay lại một lần sau 4 câu, tổng kết,
// làm lại câu sai) và làm cả phiếu rồi chấm (runSheet). Mỗi câu tính lần trả lời đầu tiên; câu ôn lại chỉ để luyện.
import { Check, Coins, X } from 'lucide-react';
import { Fragment, useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode, type RefObject } from 'react';
import { Link, useNavigate } from 'react-router';
import { AudioButton } from '../../components/AudioButton';
import { Kbd } from '../../components/Kbd';
import { chamCau, deTron, laTracNghiem, tachDe, type Diem, type LoaiCau } from '../../lib/baiTap';
import { tron } from '../../lib/luyen';
import { so, t } from '../../i18n';
import { BaoKetQua, NutDay, laGo, laNut, phimNghe, useTieuDiemTiep } from '../hoc/chung';
import { LOP_NUT_CHINH, LOP_NUT_PHU } from './chung';
import { flushSync } from 'react-dom';

/** Một câu để làm: câu bài tập, câu chia động từ hay câu Điền câu, cùng một dạng. */
export type CauChay = {
    khoa: string;
    kind: LoaiCau;
    prompt: string;
    answers: string[];
    options: string[] | null;
    hint: string | null;
    explanation: string | null;
    label: string | null;
    case_sensitive: boolean;
    bank: string[] | null;
    tokens: string[] | null;
    lead: string | null;
    frag: string[];
    /** Tên bộ hay chủ đề, hiện ở đầu thẻ. */
    nhom: string;
    /** Chữ để nghe (động từ, từ của câu điền): P nghe, S nghe chậm sau khi trả lời. */
    nghe?: { chu: string; url: string | null } | null;
    /** Thêm dưới câu hỏi (nghĩa của từ cần điền) và sau khi trả lời (câu đầy đủ kèm bản dịch). */
    truoc?: ReactNode;
    sau?: ReactNode;
};

export type KetQuaCau = { khoa: string; diem: Diem; traLoi: string };

/** Kết quả gửi lên máy chủ: xu (null là chưa biết), làm quá nhanh, chưa gửi được (mất mạng), máy chủ từ chối. */
export type DaGui = { xu: number | null; quaNhanh: boolean; chuaGui: boolean; loi: boolean };

const CHU_DAC_BIET = ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'];

const nhanCua = (c: CauChay) =>
    c.label ?? (laTracNghiem(c) ? t('lamBai.chonDapAn') : c.kind === 'order' ? t('lamBai.sapXep') : c.kind === 'fix' ? t('lamBai.suaLoi') : t('lamBai.dienChoTrong'));

/** Đề có chỗ trống và chữ đậm; chỗ trống có thể thay bằng ô nhập (phiếu). */
export function De({ chu, oTrong }: { chu: string; oTrong?: ReactNode }) {
    return (
        <>
            {tachDe(chu).map((m, i) =>
                m.kieu === 'trong' ? (
                    <Fragment key={i}>
                        {oTrong ?? (
                            <span className="mx-0.5 inline-block w-16 border-b-2 border-ink-2 align-baseline">
                                <span className="sr-only">{t('lamBai.choTrong')}</span>
                            </span>
                        )}
                    </Fragment>
                ) : m.kieu === 'dam' ? (
                    <strong key={i}>{m.chu}</strong>
                ) : (
                    <Fragment key={i}>{m.chu}</Fragment>
                ),
            )}
        </>
    );
}

function chuBao(diem: Diem, traLoi: string, phieu = false) {
    return diem === 2 ? t('lamBai.dung') : diem === 1 ? t('lamBai.dungChu') : phieu && !traLoi ? t('lamBai.chuaLam') : t('lamBai.chuaDung');
}

/** Sau khi trả lời: báo đúng sai, đáp án (khi sai hoặc có lời giải), lời giải, phần thêm. */
function SauTraLoi({ c, diem, traLoi }: { c: CauChay; diem: Diem; traLoi: string }) {
    return (
        <>
            <BaoKetQua diem={diem} chu={chuBao(diem, traLoi)} phu={traLoi && diem < 2 ? <s>{traLoi}</s> : undefined} />
            {(diem < 2 || c.explanation) && (
                <p className="mt-2">
                    <span className="font-semibold">{`${t('lamBai.dapAn')} `}</span>
                    <span lang="de">{c.answers[0]}</span>
                </p>
            )}
            {c.explanation && <p className="mt-1 text-ink-2">{c.explanation}</p>}
            {c.nghe && (
                <div className="mt-3 flex gap-2">
                    <AudioButton text={c.nghe.chu} url={c.nghe.url} label={t('lamBai.phatAm')} shortcut="P" />
                    <AudioButton text={c.nghe.chu} url={c.nghe.url} slow label={t('nghe.cham')} shortcut="S" />
                </div>
            )}
            {c.sau}
        </>
    );
}

/** Sắp xếp: bấm từ để đặt vào câu, bấm lại để bỏ ra; phím 1–9 đặt từ, Backspace bỏ từ cuối. */
function SapXep({ c, khoa, coPhim, onDoi }: { c: CauChay; khoa: boolean; coPhim: boolean; onDoi: (giaTri: string, du: boolean) => void }) {
    const manh = useMemo(() => tron((c.tokens ?? []).map((chu, i) => ({ chu, i }))), [c.tokens]);
    const [da, setDa] = useState<number[]>([]);
    const con = manh.filter((m) => !da.includes(m.i));

    const doi = (moi: number[]) => {
        setDa(moi);
        const chu = [c.lead ?? '', ...moi.map((i) => (c.tokens ?? [])[i])].filter(Boolean).join(' ');
        onDoi(chu, moi.length === manh.length);
    };

    useLayoutEffect(() => {
        if (!coPhim || khoa) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.ctrlKey || e.metaKey || e.altKey || laGo(e.target)) return;
            if (/^[1-9]$/.test(e.key)) {
                const m = con[Number(e.key) - 1];
                if (m) {
                    e.preventDefault();
                    doi([...da, m.i]);
                }
            } else if (e.key === 'Backspace' && da.length > 0) {
                e.preventDefault();
                doi(da.slice(0, -1));
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    return (
        <div>
            <div aria-label={t('lamBai.cauCuaBan')} role="group" className="flex min-h-14 flex-wrap items-center gap-1.5 rounded-md border-[1.5px] border-dashed border-line-strong p-2">
                {c.lead && (
                    <span lang="de" className="rounded-md bg-sunken px-3 py-2 font-semibold">
                        {c.lead}
                    </span>
                )}
                {da.map((i, k) => (
                    <button
                        key={`${i}-${k}`}
                        type="button"
                        lang="de"
                        disabled={khoa}
                        onClick={() => doi(da.filter((x) => x !== i))}
                        aria-label={t('lamBai.boTu', { tu: (c.tokens ?? [])[i] })}
                        className="min-h-11 rounded-md border border-tim bg-tim-tint px-3 font-semibold disabled:cursor-default"
                    >
                        {(c.tokens ?? [])[i]}
                    </button>
                ))}
                {da.length === 0 && !c.lead && <span className="px-1 text-label text-ink-2">{t('lamBai.bamTu')}</span>}
            </div>
            <div role="group" aria-label={t('lamBai.cacTu')} className="mt-3 flex flex-wrap gap-1.5">
                {con.map((m, k) => (
                    <button
                        key={m.i}
                        type="button"
                        lang="de"
                        disabled={khoa}
                        onClick={() => doi([...da, m.i])}
                        aria-label={t('lamBai.themTu', { tu: m.chu })}
                        className="flex min-h-11 items-center gap-2 rounded-md border border-line bg-sheet px-3 font-semibold hover:border-line-strong hover:bg-sunken disabled:cursor-default"
                    >
                        {m.chu}
                        {coPhim && k < 9 && <Kbd>{String(k + 1)}</Kbd>}
                    </button>
                ))}
            </div>
        </div>
    );
}

/** Ô gõ: chữ đặc biệt của tiếng Đức và từ cho sẵn bấm để chèn. */
function OGo({ c, giaTri, onDoi, khoa, oRef, nhan }: { c: CauChay; giaTri: string; onDoi: (v: string) => void; khoa: boolean; oRef: RefObject<HTMLInputElement | null>; nhan: string }) {
    const chen = (chu: string, ca = false) => {
        const o = oRef.current;
        if (!o || khoa) return;
        if (ca) {
            onDoi(giaTri ? `${giaTri.replace(/\s*$/, ' ')}${chu}` : chu);
            requestAnimationFrame(() => o.focus());
            return;
        }
        const a = o.selectionStart ?? giaTri.length;
        const b = o.selectionEnd ?? a;
        // Vẽ lại ngay rồi đặt con trỏ, để phím gõ tiếp theo không chen trước lúc đặt con trỏ
        flushSync(() => onDoi(giaTri.slice(0, a) + chu + giaTri.slice(b)));
        o.focus();
        o.setSelectionRange(a + chu.length, a + chu.length);
    };
    return (
        <>
            {c.bank && c.bank.length > 0 && (
                <div role="group" aria-label={t('lamBai.tuChoSan')} className="mt-3 flex flex-wrap gap-1.5">
                    {c.bank.map((w) => (
                        <button
                            key={w}
                            type="button"
                            lang="de"
                            tabIndex={-1}
                            disabled={khoa}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => chen(w, true)}
                            className="min-h-11 rounded-md border border-line bg-sheet px-3 font-semibold hover:bg-sunken disabled:opacity-60"
                        >
                            {w}
                        </button>
                    ))}
                </div>
            )}
            <input
                ref={oRef}
                type="text"
                lang="de"
                value={giaTri}
                onChange={(e) => onDoi(e.target.value)}
                readOnly={khoa}
                autoComplete="off"
                autoCapitalize="off"
                spellCheck={false}
                aria-label={nhan}
                className="mt-3 min-h-12 w-full rounded-md border-[1.5px] border-line-strong bg-paper px-3 text-title outline-offset-2 read-only:bg-sunken"
            />
            {!khoa && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {CHU_DAC_BIET.map((ch) => (
                        <button
                            key={ch}
                            type="button"
                            tabIndex={-1}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => chen(ch)}
                            aria-label={t('luyen.goChu', { chu: ch })}
                            className="min-h-11 min-w-11 rounded-md border border-line bg-sheet font-semibold hover:bg-sunken"
                        >
                            {ch}
                        </button>
                    ))}
                </div>
            )}
        </>
    );
}

/** Một câu ở cách chấm ngay: chọn, xếp hay gõ; Enter kiểm tra, rồi Enter (hoặc Space) sang câu sau. */
function MotCau({ c, onLai, onTraLoi, onTiep }: { c: CauChay; onLai: boolean; onTraLoi: (diem: Diem, traLoi: string) => void; onTiep: (diem: Diem) => void }) {
    const tn = laTracNghiem(c);
    const [go, setGo] = useState(c.kind === 'fix' ? deTron(c.prompt) : '');
    const [xep, setXep] = useState({ chu: '', du: false });
    const [kq, setKq] = useState<{ diem: Diem; traLoi: string; chon?: number } | null>(null);
    const oGo = useRef<HTMLInputElement>(null);
    const nutTiep = useTieuDiemTiep(kq !== null);

    const tra = useCallback(
        (traLoi: string, chon?: number) => {
            if (kq) return;
            const diem = traLoi ? chamCau(c, traLoi) : 0;
            setKq({ diem, traLoi, chon });
            onTraLoi(diem, traLoi);
        },
        [kq, c, onTraLoi],
    );

    const kiem = useCallback(() => {
        if (kq) return;
        if (c.kind === 'order') {
            if (xep.du) tra(xep.chu);
            return;
        }
        const v = go.trim();
        if (!v) {
            oGo.current?.focus();
            return;
        }
        tra(v);
    }, [kq, c.kind, xep, go, tra]);

    useEffect(() => {
        if (c.kind === 'fix') oGo.current?.select();
        else oGo.current?.focus();
    }, [c.kind]);

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (kq && c.nghe && phimNghe(e, c.nghe.chu, c.nghe.url)) return;
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (!kq && tn && /^[1-6]$/.test(e.key) && !laGo(e.target)) {
                const i = Number(e.key) - 1;
                if (i < (c.options ?? []).length) {
                    e.preventDefault();
                    tra((c.options ?? [])[i], i);
                }
            } else if (!kq && e.key === 'Enter' && !tn && !laNut(e.target)) {
                e.preventDefault();
                kiem();
            } else if (kq && (e.key === 'Enter' || e.key === ' ') && !laNut(e.target)) {
                e.preventDefault();
                onTiep(kq.diem);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [kq, tn, c, kiem, tra, onTiep]);

    return (
        <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col px-4 pt-4 pb-48">
            <article className="relative rounded-md border border-line bg-sheet shadow-sheet">
                <div className="relative py-6 pr-5 pl-14 md:pl-16">
                    <span aria-hidden="true" className="absolute inset-y-0 left-10 w-[1.5px] bg-pen opacity-45 md:left-12" />
                    <p className="mb-3 flex flex-wrap gap-x-3 text-label text-ink-2">
                        <span>{c.nhom}</span>
                        {onLai && <span className="font-semibold text-danger">{t('hoc.onLai')}</span>}
                    </p>
                    <h1 className="mb-2 text-label font-semibold tracking-wide text-ink-2 uppercase">{nhanCua(c)}</h1>
                    {c.prompt && (
                        <p lang="de" className="text-title font-semibold break-words">
                            <De chu={c.prompt} />
                        </p>
                    )}
                    {c.truoc}
                    {c.hint && <p className="mt-2 text-ink-2">{c.hint}</p>}

                    <div className="mt-4">
                        {tn ? (
                            <div role="group" aria-label={t('lamBai.cacDapAn')} className="grid gap-2">
                                {(c.options ?? []).map((chu, i) => {
                                    const laDung = kq !== null && c.answers.includes(chu);
                                    const laSai = kq?.chon === i && !laDung;
                                    return (
                                        <button
                                            key={chu}
                                            type="button"
                                            onClick={() => tra(chu, i)}
                                            disabled={kq !== null}
                                            aria-keyshortcuts={String(i + 1)}
                                            className={`flex min-h-12 items-center gap-3 rounded-md border-[1.5px] px-3 py-2 text-left transition-colors duration-200 ${
                                                laDung ? 'border-tim bg-tim-tint font-bold' : laSai ? 'border-danger bg-danger-tint' : 'border-line bg-sheet hover:border-line-strong hover:bg-sunken'
                                            } disabled:cursor-default`}
                                        >
                                            <Kbd>{String(i + 1)}</Kbd>
                                            <span className="min-w-0 flex-1 break-words">{chu}</span>
                                            {laDung && <Check size={20} aria-hidden="true" className="text-tim" />}
                                            {laSai && <X size={20} aria-hidden="true" className="text-danger" />}
                                        </button>
                                    );
                                })}
                            </div>
                        ) : c.kind === 'order' ? (
                            <SapXep c={c} khoa={kq !== null} coPhim onDoi={(chu, du) => setXep({ chu, du })} />
                        ) : (
                            <OGo c={c} giaTri={go} onDoi={setGo} khoa={kq !== null} oRef={oGo} nhan={t('luyen.traLoi')} />
                        )}
                        {!kq && !tn && (
                            <button
                                type="button"
                                onClick={() => tra('')}
                                className="mt-3 min-h-11 rounded-md border border-line px-3 text-label font-semibold hover:bg-sunken"
                            >
                                {t('luyen.xemDapAn')}
                            </button>
                        )}
                    </div>

                    {kq && <SauTraLoi c={c} diem={kq.diem} traLoi={kq.traLoi} />}
                </div>
            </article>
            {kq ? (
                <NutDay nutRef={nutTiep} onClick={() => onTiep(kq.diem)}>
                    {t('luyen.tiep')} <Kbd>{t('phim.enter')}</Kbd>
                </NutDay>
            ) : (
                !tn && (
                    <NutDay onClick={kiem} disabled={c.kind === 'order' && !xep.du}>
                        {t('luyen.kiemTra')} <Kbd>{t('phim.enter')}</Kbd>
                    </NutDay>
                )
            )}
        </main>
    );
}

/** Dòng xu và tình trạng gửi kết quả ở cuối lượt. */
function DongXu({ daGui }: { daGui: DaGui | null }) {
    if (!daGui) return <p className="mt-3 text-label text-ink-2">{t('chung.dangTai')}</p>;
    return (
        <>
            {daGui.xu !== null && (
                <p className="mt-3 flex items-center gap-2 font-semibold">
                    <Coins size={20} aria-hidden="true" />
                    {t('lamBai.xu', { n: so(daGui.xu) })}
                </p>
            )}
            {daGui.quaNhanh && <p className="mt-1 text-label text-ink-2">{t('lamBai.quaNhanh')}</p>}
            {daGui.chuaGui && <p className="mt-1 text-label text-ink-2">{t('lamBai.chuaGui')}</p>}
            {daGui.loi && (
                <p role="alert" className="mt-1 text-label font-semibold text-danger">
                    {t('lamBai.loiGui')}
                </p>
            )}
        </>
    );
}

function DanhSachSai({ ds }: { ds: CauChay[] }) {
    if (ds.length === 0) return null;
    return (
        <section aria-labelledby="can-on" className="mt-8">
            <h2 id="can-on" className="text-title font-bold">
                {t('lamBai.canOnLai')}
            </h2>
            <ul className="mt-2 divide-y divide-line">
                {ds.map((c) => (
                    <li key={c.khoa} className="py-3">
                        {c.prompt && (
                            <p lang="de" className="break-words">
                                <De chu={c.prompt} />
                            </p>
                        )}
                        <p lang="de" className="font-bold">
                            {c.answers[0]}
                        </p>
                        {c.explanation && <p className="text-label text-ink-2">{c.explanation}</p>}
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * Chấm ngay từng câu (Lernbuch drive): câu sai lần đầu quay lại một lần sau 4 câu (chỉ để luyện); xong thì gửi kết quả
 * lần đầu của từng câu, tổng kết số câu đúng ngay lần đầu, xu, câu cần ôn lại.
 */
export function ChayBai({
    cau,
    ve,
    onCham,
    onXong,
    onLamLai,
    onLuyenTiep,
    ghiChu,
}: {
    cau: CauChay[];
    ve: string;
    onCham?: (c: CauChay, diem: Diem, traLoi: string) => void;
    onXong: (kq: KetQuaCau[]) => Promise<DaGui>;
    onLamLai: (sai: CauChay[]) => void;
    onLuyenTiep?: () => void;
    ghiChu?: string;
}) {
    const navigate = useNavigate();
    const [hang, setHang] = useState(() => cau.map((c) => ({ c, onLai: false })));
    const [vt, setVt] = useState(0);
    const [xong, setXong] = useState(0);
    const [kq, setKq] = useState<Record<string, KetQuaCau>>({});
    const [daGui, setDaGui] = useState<DaGui | null>(null);
    const daGoiXong = useRef(false);
    const het = vt >= hang.length;
    const muc = het ? null : hang[vt];

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !e.ctrlKey && !e.metaKey && !e.altKey) navigate(ve);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [navigate, ve]);

    // Xong: gửi lần trả lời đầu của từng câu, theo thứ tự ban đầu
    useEffect(() => {
        if (!het || daGoiXong.current) return;
        daGoiXong.current = true;
        void onXong(cau.map((c) => kq[c.khoa] ?? { khoa: c.khoa, diem: 0, traLoi: '' })).then(setDaGui);
    }, [het, cau, kq, onXong]);

    const traLoi = useCallback(
        (c: CauChay, diem: Diem, chu: string) => {
            if (kq[c.khoa]) return;
            setKq((x) => ({ ...x, [c.khoa]: { khoa: c.khoa, diem, traLoi: chu } }));
            onCham?.(c, diem, chu);
        },
        [kq, onCham],
    );

    const tiep = (diem: Diem) => {
        if (!muc) return;
        if (diem === 0 && !muc.onLai) setHang((h) => [...h.slice(0, vt + 4), { c: muc.c, onLai: true }, ...h.slice(vt + 4)]);
        else if (!muc.onLai || diem > 0) setXong((x) => Math.min(cau.length, x + 1));
        setVt((v) => v + 1);
    };

    const pct = cau.length === 0 ? 0 : Math.round(((het ? cau.length : xong) / cau.length) * 100);
    const dung = cau.filter((c) => kq[c.khoa]?.diem === 2);
    const sai = cau.filter((c) => kq[c.khoa]?.diem !== 2);

    return (
        <div className="flex min-h-dvh flex-col bg-paper text-ink">
            <header className="mx-auto flex w-full max-w-[640px] items-center gap-4 px-4 pt-3 pb-2">
                <Link to={ve} aria-label={t('lamBai.thoat')} className="flex size-11 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink">
                    <X size={24} aria-hidden="true" />
                </Link>
                <div
                    role="progressbar"
                    aria-label={t('lamBai.tienDo')}
                    aria-valuemin={0}
                    aria-valuemax={cau.length}
                    aria-valuenow={het ? cau.length : xong}
                    className="h-2 flex-1 overflow-hidden rounded-sm bg-sunken"
                >
                    <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${pct}%` }} />
                </div>
                <span className="min-w-12 text-right text-label font-semibold tabular-nums text-ink-2">{`${het ? cau.length : xong}/${cau.length}`}</span>
            </header>
            {muc ? (
                <MotCau key={vt} c={muc.c} onLai={muc.onLai} onTraLoi={(d, chu) => traLoi(muc.c, d, chu)} onTiep={tiep} />
            ) : (
                <main className="mx-auto w-full max-w-[640px] flex-1 px-5 py-6">
                    <h1 className="text-headline font-bold">
                        {sai.length === 0 ? t('lamBai.dungHet', { tong: so(cau.length) }) : t('lamBai.ketQua', { ok: so(dung.length), tong: so(cau.length) })}
                    </h1>
                    {ghiChu && <p className="mt-1 text-ink-2">{ghiChu}</p>}
                    <DongXu daGui={daGui} />
                    <DanhSachSai ds={sai} />
                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        {sai.length > 0 && (
                            <button type="button" onClick={() => onLamLai(sai)} className={LOP_NUT_CHINH}>
                                {t('lamBai.lamLaiSai', { n: so(sai.length) })}
                            </button>
                        )}
                        {onLuyenTiep && (
                            <button type="button" onClick={onLuyenTiep} className={sai.length > 0 ? LOP_NUT_PHU : LOP_NUT_CHINH}>
                                {t('lamBai.luyenTiep')}
                            </button>
                        )}
                        <Link to={ve} className={LOP_NUT_PHU}>
                            {t('chung.quayLai')}
                        </Link>
                    </div>
                </main>
            )}
        </div>
    );
}

/** Một dòng của phiếu: chọn (bấm lại để bỏ), xếp, hay gõ; một chỗ trống thì ô gõ nằm ngay trong câu. */
function DongPhieu({
    c,
    so: thuTu,
    giaTri,
    onDoi,
    diem,
    oRef,
    onEnter,
    hienGoiY,
}: {
    c: CauChay;
    so: number;
    giaTri: string;
    onDoi: (v: string, du: boolean) => void;
    diem: Diem | null;
    oRef: (o: HTMLInputElement | null) => void;
    onEnter: () => void;
    hienGoiY: boolean;
}) {
    const tn = laTracNghiem(c);
    const khoa = diem !== null;
    const motCho = c.kind !== 'fix' && !tn && c.kind !== 'order' && (c.prompt.match(/_{2,}/g) ?? []).length === 1;
    const oNho = useRef<HTMLInputElement | null>(null);
    const nhan = t('lamBai.cau', { n: thuTu });
    const oNhap = (rong: boolean) => (
        <input
            ref={(o) => {
                oNho.current = o;
                oRef(o);
            }}
            type="text"
            lang="de"
            value={giaTri}
            onChange={(e) => onDoi(e.target.value, e.target.value.trim() !== '' && (c.kind !== 'fix' || e.target.value.trim() !== deTron(c.prompt)))}
            onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    onEnter();
                }
            }}
            readOnly={khoa}
            autoComplete="off"
            autoCapitalize="off"
            spellCheck={false}
            aria-label={nhan}
            className={`min-h-11 rounded-md border-[1.5px] border-line-strong bg-paper px-2 read-only:bg-sunken ${rong ? 'w-full' : 'mx-1 w-36 align-baseline'}`}
        />
    );

    return (
        <li className="flex gap-3 border-b border-line py-3">
            <span className="w-7 shrink-0 pt-2 text-right font-semibold text-ink-2 tabular-nums">{thuTu}</span>
            <div className="min-w-0 flex-1">
                {hienGoiY && c.hint && <p className="text-label text-ink-2">{c.hint}</p>}
                {c.prompt && (
                    <p lang="de" className="break-words leading-loose">
                        <De chu={c.prompt} oTrong={motCho ? oNhap(false) : undefined} />
                    </p>
                )}
                {tn ? (
                    <div role="group" aria-label={nhan} className="mt-1 flex flex-wrap gap-1.5">
                        {(c.options ?? []).map((chu) => {
                            const on = giaTri === chu;
                            const dungO = khoa && c.answers.includes(chu);
                            const saiO = khoa && on && !dungO;
                            return (
                                <button
                                    key={chu}
                                    type="button"
                                    aria-pressed={on}
                                    disabled={khoa}
                                    onClick={() => onDoi(on ? '' : chu, !on)}
                                    className={`min-h-11 rounded-md border-[1.5px] px-3 font-semibold transition-colors duration-200 disabled:cursor-default ${
                                        dungO ? 'border-tim bg-tim-tint' : saiO ? 'border-danger bg-danger-tint' : on ? 'border-tim bg-tim-tint' : 'border-line hover:border-line-strong'
                                    }`}
                                >
                                    {chu}
                                </button>
                            );
                        })}
                    </div>
                ) : c.kind === 'order' ? (
                    <div className="mt-1">
                        <SapXep c={c} khoa={khoa} coPhim={false} onDoi={onDoi} />
                    </div>
                ) : (
                    <>
                        {!motCho && <div className="mt-1">{oNhap(true)}</div>}
                        {c.bank && c.bank.length > 0 && (
                            <div role="group" aria-label={t('lamBai.tuChoSan')} className="mt-2 flex flex-wrap gap-1.5">
                                {c.bank.map((w) => (
                                    <button
                                        key={w}
                                        type="button"
                                        lang="de"
                                        tabIndex={-1}
                                        disabled={khoa}
                                        onClick={() => {
                                            const moi = giaTri ? `${giaTri.replace(/\s*$/, ' ')}${w}` : w;
                                            onDoi(moi, true);
                                            oNho.current?.focus();
                                        }}
                                        className="min-h-11 rounded-md border border-line px-3 font-semibold hover:bg-sunken disabled:opacity-60"
                                    >
                                        {w}
                                    </button>
                                ))}
                            </div>
                        )}
                    </>
                )}
                {diem !== null && (
                    <div>
                        <p className={`mt-2 flex items-center gap-2 font-bold ${diem === 2 ? 'text-tim' : diem === 1 ? 'text-warning-ink' : 'text-danger'}`}>
                            {diem === 0 ? <X size={18} aria-hidden="true" /> : <Check size={18} aria-hidden="true" />}
                            {chuBao(diem, giaTri, true)}
                        </p>
                        {(diem < 2 || c.explanation) && (
                            <p className="mt-1">
                                <span className="font-semibold">{`${t('lamBai.dapAn')} `}</span>
                                <span lang="de">{c.answers[0]}</span>
                            </p>
                        )}
                        {c.explanation && <p className="text-ink-2">{c.explanation}</p>}
                    </div>
                )}
            </div>
        </li>
    );
}

/**
 * Làm cả phiếu rồi chấm (Lernbuch runSheet): Enter sang câu sau, Ctrl+Enter chấm bài; còn câu chưa làm thì hỏi lại.
 * Chấm xong: đúng sai từng câu, lời giải, điều cần ghi nhớ của bộ; làm lại câu sai hoặc cả bộ.
 */
export function PhieuBai({
    cau,
    tieuDe,
    phu,
    loiDan,
    ghiNho,
    ve,
    onXong,
    onLamLai,
}: {
    cau: CauChay[];
    tieuDe: string;
    phu: string | null;
    loiDan: string | null;
    ghiNho: string | null;
    ve: string;
    onXong: (kq: KetQuaCau[]) => Promise<DaGui>;
    onLamLai: (ds: CauChay[]) => void;
}) {
    const [giaTri, setGiaTri] = useState<Record<string, { chu: string; du: boolean }>>({});
    const [diem, setDiem] = useState<Record<string, Diem> | null>(null);
    const [hoi, setHoi] = useState(false);
    const [daGui, setDaGui] = useState<DaGui | null>(null);
    const oNhap = useRef<(HTMLInputElement | null)[]>([]);
    const nutCham = useRef<HTMLButtonElement>(null);
    const dau = useRef<HTMLHeadingElement>(null);
    const daLam = cau.filter((c) => giaTri[c.khoa]?.du).length;

    const cham = useCallback(
        (vanCham = false) => {
            if (diem) return;
            if (!vanCham && daLam < cau.length) {
                setHoi(true);
                return;
            }
            setHoi(false);
            const d: Record<string, Diem> = {};
            for (const c of cau) {
                const v = giaTri[c.khoa];
                d[c.khoa] = v?.du ? chamCau(c, v.chu) : 0;
            }
            setDiem(d);
            requestAnimationFrame(() => dau.current?.focus());
            void onXong(cau.map((c) => ({ khoa: c.khoa, diem: d[c.khoa], traLoi: giaTri[c.khoa]?.du ? giaTri[c.khoa].chu : '' }))).then(setDaGui);
        },
        [diem, daLam, cau, giaTri, onXong],
    );

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && !diem) {
                e.preventDefault();
                cham();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [cham, diem]);

    useEffect(() => {
        oNhap.current.find(Boolean)?.focus();
    }, []);

    const dung = diem ? cau.filter((c) => diem[c.khoa] === 2) : [];
    const sai = diem ? cau.filter((c) => diem[c.khoa] !== 2) : [];

    return (
        <div className="min-h-dvh bg-paper text-ink">
            <main className="mx-auto w-full max-w-[760px] px-5 py-6 pb-28">
                <Link to={ve} className="-ml-2 inline-flex min-h-11 items-center gap-1.5 rounded-md px-2 text-label font-semibold text-ink-2 hover:text-ink">
                    <X size={18} aria-hidden="true" />
                    {t('chung.quayLai')}
                </Link>
                <h1 ref={dau} tabIndex={-1} className="mt-2 text-headline font-bold">
                    {tieuDe}
                </h1>
                {phu && <p className="text-ink-2">{phu}</p>}
                {loiDan && <p className="mt-3 font-semibold">{loiDan}</p>}

                {diem && (
                    <section aria-label={t('lamBai.ketQuaPhieu', { ok: so(dung.length), tong: so(cau.length) })} className="mt-4 border-y border-line py-3">
                        <p className="text-title font-bold">
                            {sai.length === 0 ? t('lamBai.dungHet', { tong: so(cau.length) }) : t('lamBai.ketQuaPhieu', { ok: so(dung.length), tong: so(cau.length) })}
                        </p>
                        {sai.length > 0 && <p className="text-ink-2">{t('lamBai.canXemLai', { n: so(sai.length) })}</p>}
                        <DongXu daGui={daGui} />
                        {ghiNho && (
                            <aside className="mt-3 rounded-md bg-sunken px-3 py-2">
                                <p className="text-label font-bold">{t('lamBai.ghiNho')}</p>
                                <p>{ghiNho}</p>
                            </aside>
                        )}
                    </section>
                )}

                <ol className="mt-4">
                    {cau.map((c, i) => (
                        <DongPhieu
                            key={c.khoa}
                            c={c}
                            so={i + 1}
                            giaTri={giaTri[c.khoa]?.chu ?? (c.kind === 'fix' ? deTron(c.prompt) : '')}
                            onDoi={(chu, du) => setGiaTri((x) => ({ ...x, [c.khoa]: { chu, du } }))}
                            diem={diem ? diem[c.khoa] : null}
                            oRef={(o) => {
                                oNhap.current[i] = o;
                            }}
                            onEnter={() => {
                                const sau = oNhap.current.slice(i + 1).find(Boolean);
                                (sau ?? nutCham.current)?.focus();
                            }}
                            hienGoiY={i === 0 || c.hint !== cau[i - 1].hint}
                        />
                    ))}
                </ol>
            </main>

            <footer className="fixed inset-x-0 bottom-0 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)]">
                <div className="mx-auto flex max-w-[760px] flex-wrap items-center gap-3 px-5 py-3">
                    {!diem ? (
                        hoi ? (
                            <>
                                <p role="alert" className="mr-auto font-semibold">
                                    {t('lamBai.conChuaLam', { n: so(cau.length - daLam) })}
                                </p>
                                <button type="button" onClick={() => setHoi(false)} className={LOP_NUT_PHU}>
                                    {t('lamBai.lamTiep')}
                                </button>
                                <button type="button" onClick={() => cham(true)} className={LOP_NUT_CHINH}>
                                    {t('lamBai.vanCham')}
                                </button>
                            </>
                        ) : (
                            <>
                                <p className="mr-auto text-label text-ink-2 tabular-nums">{t('lamBai.daLam', { n: so(daLam), tong: so(cau.length) })}</p>
                                <button ref={nutCham} type="button" onClick={() => cham()} className={LOP_NUT_CHINH}>
                                    {t('lamBai.chamBai')} <Kbd>{t('phim.ctrlEnter')}</Kbd>
                                </button>
                            </>
                        )
                    ) : (
                        <>
                            {sai.length > 0 && (
                                <button type="button" onClick={() => onLamLai(sai)} className={LOP_NUT_PHU}>
                                    {t('lamBai.lamLaiSai', { n: so(sai.length) })}
                                </button>
                            )}
                            <button type="button" onClick={() => onLamLai(cau)} className={LOP_NUT_PHU}>
                                {t('lamBai.lamLaiCaBo')}
                            </button>
                            <Link to={ve} className={`${LOP_NUT_CHINH} ml-auto`}>
                                {t('lamBai.xong')}
                            </Link>
                        </>
                    )}
                </div>
            </footer>
        </div>
    );
}
