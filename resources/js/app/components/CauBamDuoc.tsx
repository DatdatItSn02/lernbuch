import { X } from 'lucide-react';
import { useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { Link } from 'react-router';
import { DongDang, TuDuc } from './Article';
import type { TheTomTat } from '../lib/noiDung';
import { useApi } from '../lib/useApi';
import { useStore } from '../state/store';
import { t } from '../i18n';

/** Tách câu thành chữ (bấm được) và phần xen giữa (khoảng trắng, dấu câu). Chữ gồm cả dấu nối và lược: "geht's", "E-Mail". */
export function tachChu(cau: string): { chu: string; laTu: boolean }[] {
    const ra: { chu: string; laTu: boolean }[] = [];
    let cuoi = 0;
    for (const m of cau.matchAll(/[\p{L}\p{M}\p{N}]+(?:['’-][\p{L}\p{M}\p{N}]+)*/gu)) {
        if (m.index > cuoi) ra.push({ chu: cau.slice(cuoi, m.index), laTu: false });
        ra.push({ chu: m[0], laTu: true });
        cuoi = m.index + m[0].length;
    }
    if (cuoi < cau.length) ra.push({ chu: cau.slice(cuoi), laTu: false });
    return ra;
}

/**
 * Câu tiếng Đức bấm từng chữ để tra (GET /tra-chu). Cả câu là một điểm dừng Tab; mũi tên trái/phải đi giữa các chữ,
 * Enter hay Space để tra. sauCau (vd bản dịch) nằm ngay dưới câu; bảng tra hiện sau đó, không chen giữa câu và bản dịch.
 */
export function CauBamDuoc({ cau, className = '', sauCau }: { cau: string; className?: string; sauCau?: ReactNode }) {
    const phan = tachChu(cau);
    const tu = phan.flatMap((p, i) => (p.laTu ? [i] : []));
    const [chon, setChon] = useState<number | null>(null);
    const [dungO, setDungO] = useState(tu[0] ?? 0);
    const nhom = useRef<HTMLSpanElement>(null);
    const panelId = useId();

    const diChuyen = (e: KeyboardEvent<HTMLSpanElement>) => {
        const i = tu.indexOf(dungO);
        const toi = e.key === 'ArrowRight' ? tu[Math.min(i + 1, tu.length - 1)] : e.key === 'ArrowLeft' ? tu[Math.max(i - 1, 0)] : e.key === 'Home' ? tu[0] : e.key === 'End' ? tu[tu.length - 1] : undefined;
        if (toi === undefined) return;
        e.preventDefault();
        setDungO(toi);
        nhom.current?.querySelector<HTMLButtonElement>(`[data-o="${toi}"]`)?.focus();
    };

    return (
        <>
            <p className={className}>
                <span ref={nhom} role="group" aria-label={t('theTu.bamChu')} onKeyDown={diChuyen} lang="de">
                    {phan.map((p, i) =>
                        p.laTu ? (
                            <button
                                key={i}
                                type="button"
                                data-o={i}
                                tabIndex={i === dungO ? 0 : -1}
                                aria-expanded={chon === i}
                                aria-controls={chon === i ? panelId : undefined}
                                onFocus={() => setDungO(i)}
                                onClick={() => setChon(chon === i ? null : i)}
                                className={`rounded-sm underline decoration-ink-3 decoration-dotted underline-offset-4 transition-colors duration-200 hover:bg-tim-tint ${
                                    chon === i ? 'bg-tim-tint decoration-tim' : ''
                                }`}
                            >
                                {p.chu}
                            </button>
                        ) : (
                            <span key={i}>{p.chu}</span>
                        ),
                    )}
                </span>
            </p>
            {sauCau}
            {chon !== null && <BangTraChu key={chon} id={panelId} chu={phan[chon].chu} onDong={() => setChon(null)} />}
        </>
    );
}

/** Bảng nghĩa của một chữ vừa bấm: tối đa 3 thẻ khớp, kèm link mở thẻ. */
function BangTraChu({ id, chu, onDong }: { id: string; chu: string; onDong: () => void }) {
    const { settings } = useStore();
    const { duLieu, loi } = useApi<{ data: TheTomTat[] }>(`/tra-chu?chu=${encodeURIComponent(chu)}`);
    const ds = duLieu?.data;
    const langs = (['vi', 'en', 'zh'] as const).filter((l) => settings.langs[l]);

    return (
        <div id={id} role="region" aria-label={t('theTu.traChuCua', { chu })} className="mt-2 rounded-md border border-line bg-paper px-4 py-3">
            <div className="flex items-start justify-between gap-3">
                <p aria-live="polite" className="min-w-0 pt-2.5 text-label text-ink-2">
                    {loi ? t('theTu.traChuLoi', { chu }) : !ds ? t('theTu.traChuDangTra', { chu }) : ds.length === 0 ? t('theTu.traChuKhongCo', { chu }) : t('theTu.traChuCua', { chu })}
                </p>
                <button type="button" onClick={onDong} aria-label={t('theTu.traChuDong')} className="-mr-2 flex size-11 shrink-0 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink">
                    <X size={18} aria-hidden="true" />
                </button>
            </div>
            {ds && ds.length > 0 && (
                <ul className="mt-1 divide-y divide-line">
                    {ds.map((the) => (
                        <li key={the.id} className="py-2">
                            <Link to={`/tu/${the.id}`} className="font-semibold underline-offset-2 hover:underline">
                                <TuDuc the={the} />
                            </Link>
                            <span className="ml-2 text-label text-ink-2">
                                <DongDang the={the} />
                            </span>
                            {langs.map((l) =>
                                the.meanings[l] ? (
                                    <span key={l} lang={l} className={`block ${l === 'vi' ? '' : 'text-label text-ink-2'} ${l === 'zh' ? 'font-zh' : ''}`}>
                                        {the.meanings[l]}
                                    </span>
                                ) : null,
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
