import { History, Search, Star, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { DongDang, TuDuc } from '../components/Article';
import { TraTuAi } from '../components/ai/TraTuAi';
import { Kbd } from '../components/Kbd';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { coTabAi, GIOI_HAN } from '../lib/ai';
import { api } from '../lib/api';
import { tenLoaiTu, type KetQuaTra, type MucLichSu, type SoTay } from '../lib/noiDung';
import { quenDaTai, useApi, type KetQuaApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { useStore } from '../state/store';
import { useTaiKhoan } from '../state/taiKhoan';
import { so, t } from '../i18n';

/** Ngừng gõ chừng này thì tra. */
export const CHO_TRA_MS = 250;

const isTyping = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
const quaNgan = (q: string) => q.length < 2 && !/\p{Script=Han}/u.test(q);

/**
 * Tra từ: gõ tiếng Đức, Việt, Anh hoặc Trung, không cần dấu; từ khớp đúng đứng đầu. Chữ đang tra nằm trên đường dẫn (?q=).
 * Mở một thẻ từ kết quả (hay bấm Enter) thì ghi vào lịch sử tra; ô trống thì hiện các lần tra gần đây.
 */
export function TraTu() {
    useTitle(t('khung.traTu'));
    const uid = useId();
    const [params, setParams] = useSearchParams();
    const q = (params.get('q') ?? '').trim();
    const coAi = coTabAi(useTaiKhoan().toi?.ai);
    const [chu, setChu] = useState(params.get('q') ?? '');
    const oTim = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (chu.trim() === q) return;
        const hen = window.setTimeout(() => setParams(chu.trim() ? { q: chu.trim() } : {}, { replace: true }), CHO_TRA_MS);
        return () => window.clearTimeout(hen);
        // setParams đổi theo mỗi lần render; chỉ chạy lại khi chữ gõ đổi
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [chu]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) return;
            e.preventDefault();
            oTim.current?.focus();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const kq = useApi<{ data: KetQuaTra[]; meta: { total: number; limit: number } }>(q && !quaNgan(q) ? `/tra-tu?q=${encodeURIComponent(q)}` : null);
    const lichSu = useApi<{ data: MucLichSu[] }>(q === '' ? '/tra-tu/lich-su' : null);
    const ghiLichSu = (term: string, cardId?: number) => void api.gui('/tra-tu/lich-su', cardId ? { term, card_id: cardId } : { term }).catch(() => {});

    const batSao = async (the: KetQuaTra) => {
        const sao = !the.starred;
        const doi = (s: boolean) => kq.sua((cu) => ({ ...cu, data: cu.data.map((x) => (x.id === the.id ? { ...x, starred: s } : x)) }));
        doi(sao);
        try {
            const r = await api.sua<{ data: SoTay }>(`/the/${the.id}/so-tay`, { starred: sao });
            doi(r.data.starred);
            quenDaTai();
        } catch {
            doi(!sao);
        }
    };

    const ketQua = kq.duLieu?.data;
    const tong = kq.duLieu?.meta.total ?? 0;

    return (
        <Sheet>
            <div className="px-5 pt-6 pb-4 md:px-8">
                <h1 className="text-headline font-bold">{t('khung.traTu')}</h1>
                <form
                    role="search"
                    className="relative mt-4 max-w-2xl"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (chu.trim()) {
                            setParams({ q: chu.trim() }, { replace: true });
                            ghiLichSu(chu.trim());
                        }
                    }}
                >
                    <label htmlFor={`${uid}-q`} className="text-label font-semibold">
                        {t('traTu.oTim')}
                    </label>
                    <div className="relative mt-2">
                        <Search size={20} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                        <input
                            ref={oTim}
                            id={`${uid}-q`}
                            type="search"
                            value={chu}
                            onChange={(e) => setChu(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape' && chu) {
                                    e.preventDefault();
                                    setChu('');
                                }
                            }}
                            aria-keyshortcuts="/"
                            aria-describedby={`${uid}-kq`}
                            placeholder={t('traTu.goiY')}
                            autoComplete="off"
                            spellCheck={false}
                            className="min-h-12 w-full rounded-md border border-line-strong bg-paper pr-10 pl-10 text-body placeholder:text-ink-2"
                        />
                        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink-2">
                            <Kbd>/</Kbd>
                        </span>
                    </div>
                </form>
                <p id={`${uid}-kq`} role="status" className="mt-3 min-h-6 text-label text-ink-2 tabular-nums">
                    {q === ''
                        ? ''
                        : quaNgan(q)
                          ? t('traTu.goNgan')
                          : ketQua
                            ? tong === 0
                                ? t('traTu.khongCo')
                                : tong > ketQua.length
                                  ? t('traTu.ketQuaNhieu', { n: so(tong), hien: so(ketQua.length) })
                                  : t('traTu.ketQua', { n: so(tong) })
                            : ''}
                </p>
            </div>

            {q !== '' && !quaNgan(q) && (
                <section aria-label={t('khung.traTu')} aria-busy={kq.dangTai} className="border-t border-line">
                    {ketQua && tong === 0 && coAi && q.trim().length <= GIOI_HAN.traTu ? (
                        <div className="px-5 py-5 md:px-8">
                            <TraTuAi tu={q.trim()} />
                        </div>
                    ) : ketQua ? (
                        <ul className="divide-y divide-line">
                            {ketQua.map((the) => (
                                <KetQua key={the.id} the={the} onMo={() => ghiLichSu(q, the.id)} onSao={() => void batSao(the)} />
                            ))}
                        </ul>
                    ) : kq.loi ? (
                        <LoiTai loi={kq.loi} taiLai={kq.taiLai} className="px-5 md:px-8" />
                    ) : (
                        <DangTai className="px-5 md:px-8" />
                    )}
                </section>
            )}

            {q === '' && <LichSuTra lichSu={lichSu} onChon={(term) => setChu(term)} />}
        </Sheet>
    );
}

function KetQua({ the, onMo, onSao }: { the: KetQuaTra; onMo: () => void; onSao: () => void }) {
    const { settings } = useStore();
    const langs = (['vi', 'en', 'zh'] as const).filter((l) => settings.langs[l] && the.meanings[l]);
    return (
        <li className="flex items-start gap-3 px-5 py-3 md:px-8">
            <div className="min-w-0 flex-1">
                <Link to={`/tu/${the.id}`} onClick={onMo} className="text-title font-bold break-words underline-offset-2 hover:underline">
                    <TuDuc the={the} />
                </Link>
                <span className="block text-label text-ink-2">
                    {[tenLoaiTu(the.pos), the.level].filter(Boolean).join(' · ')}
                    {the.forms && the.forms !== '–' && (
                        <>
                            {the.pos || the.level ? ' · ' : ''}
                            <DongDang the={the} />
                        </>
                    )}
                </span>
                {langs.map((l) => (
                    <span key={l} lang={l} className={`block break-words ${l === 'vi' ? '' : 'text-label text-ink-2'} ${l === 'zh' ? 'font-zh' : ''}`}>
                        {the.meanings[l]}
                    </span>
                ))}
            </div>
            <button
                type="button"
                aria-pressed={the.starred}
                aria-label={t('traTu.themSao', { tu: the.front })}
                onClick={onSao}
                className={`flex size-11 shrink-0 items-center justify-center rounded-md border border-line transition-colors duration-200 hover:bg-sunken ${the.starred ? 'text-warning-ink' : 'text-ink-2'}`}
            >
                <Star size={20} aria-hidden="true" fill={the.starred ? 'currentColor' : 'none'} />
            </button>
        </li>
    );
}

/** Lịch sử tra của chính mình: mở lại thẻ đã xem, tra lại chữ cũ, xoá từng mục hay xoá hết. */
function LichSuTra({ lichSu, onChon }: { lichSu: KetQuaApi<{ data: MucLichSu[] }>; onChon: (term: string) => void }) {
    const ds = lichSu.duLieu?.data;
    const xoa = async (id: number | null) => {
        lichSu.sua((cu) => ({ data: id === null ? [] : cu.data.filter((m) => m.id !== id) }));
        try {
            await api.xoa(id === null ? '/tra-tu/lich-su' : `/tra-tu/lich-su/${id}`);
        } catch {
            lichSu.taiLai();
        }
    };
    return (
        <section aria-labelledby="tra-gan-day" className="border-t border-line px-5 pt-4 pb-6 md:px-8">
            <div className="flex min-h-11 items-center justify-between gap-3">
                <h2 id="tra-gan-day" className="flex items-center gap-2 text-title font-bold">
                    <History size={20} aria-hidden="true" className="text-ink-2" />
                    {t('traTu.ganDay')}
                </h2>
                {ds && ds.length > 0 && (
                    <button type="button" onClick={() => void xoa(null)} className="min-h-11 px-2 text-label font-semibold text-tim underline-offset-2 hover:underline">
                        {t('traTu.xoaHet')}
                    </button>
                )}
            </div>
            {!ds ? (
                lichSu.loi ? <LoiTai loi={lichSu.loi} taiLai={lichSu.taiLai} /> : <DangTai />
            ) : ds.length === 0 ? (
                <p className="mt-2 text-ink-2">{t('traTu.chuaCoLichSu')}</p>
            ) : (
                <ul className="mt-2 divide-y divide-line">
                    {ds.map((m) => (
                        <li key={m.id} className="flex items-center gap-2 py-1">
                            {m.card ? (
                                <Link to={`/tu/${m.card.id}`} className="min-h-11 min-w-0 flex-1 py-2.5 font-semibold underline-offset-2 hover:underline">
                                    <span lang="de">{m.card.front}</span>
                                    {m.term.toLowerCase() !== m.card.front.toLowerCase() && (
                                        <>
                                            {' '}
                                            <span className="font-normal text-ink-2">{`· ${m.term}`}</span>
                                        </>
                                    )}
                                </Link>
                            ) : (
                                <button type="button" onClick={() => onChon(m.term)} className="min-h-11 min-w-0 flex-1 py-2.5 text-left font-semibold underline-offset-2 hover:underline">
                                    {m.term}
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => void xoa(m.id)}
                                aria-label={t('traTu.xoaMuc', { tu: m.card?.front ?? m.term })}
                                className="flex size-11 shrink-0 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink"
                            >
                                <X size={18} aria-hidden="true" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
