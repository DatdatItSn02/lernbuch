// Phần dùng chung của mọi chế độ học: thanh trên cùng, gửi lượt khi xong phiên, bảng tổng kết, phiên rỗng.
import { ArrowRight, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router';
import { TuDuc } from '../../components/Article';
import { ChoGui } from '../../components/ChoGui';
import { LevelIcon } from '../../components/LevelIcon';
import { api } from '../../lib/api';
import { guiDi, themLuot, useHangCho } from '../../lib/hangCho';
import type { CheDo, ConLai, TheHoc } from '../../lib/hoc';
import { truyVan } from '../../lib/noiDung';
import { GRADES, SO_CHAM, grade as gradeCard, levelName, type Grade } from '../../lib/srs';
import { ulidTangDan } from '../../lib/ulid';
import { useStore } from '../../state/store';
import { t } from '../../i18n';

/** Một lượt chấm trong phiên. ghi = lượt đầu của thẻ trong phiên (gửi lên máy chủ); lượt ôn lại sau khi Quên chỉ để luyện. */
export type Done = { id: number; grade: Grade; before: number; after: number; ghi: boolean };

/** Đường dẫn phiên học (giữ chủ đề, chỉ ôn, chế độ). */
export const duongHoc = (chuDe: string | null, chiOn: boolean, cheDo: CheDo) =>
    `/hoc${truyVan({ 'chu-de': chuDe, on: chiOn ? '1' : null, 'che-do': cheDo === 'flashcard' ? null : cheDo })}`;

/**
 * Ghi lượt chấm của phiên như Lernbuch: chỉ lượt đầu của mỗi thẻ được tính (vào hàng chờ trên máy, rồi gửi theo lô);
 * thẻ Quên quay lại trong phiên chỉ để luyện, không đổi mức. Xong phiên thì chờ ghi hết vào hàng chờ, gửi ngay.
 */
export function useGhiLuot(phienId: number, cheDo: CheDo, finished: boolean) {
    const daGhi = useRef(new Set<number>());
    const dangGhi = useRef<Promise<void>[]>([]);
    // Lúc ghi lượt trước: thời gian một lượt tính từ lúc thẻ hiện, hoặc từ lượt trước nếu muộn hơn (Ghép cặp chấm nhiều thẻ một vòng)
    const lanCuoi = useRef(0);
    const [daGuiHet, setDaGuiHet] = useState(false);

    useEffect(() => {
        if (!finished) return;
        let bo = false;
        void Promise.all(dangGhi.current)
            .then(() => guiDi())
            .then(() => !bo && setDaGuiHet(true));
        return () => {
            bo = true;
        };
    }, [finished]);

    /** Ghi lượt; trả về true nếu là lượt được tính (lượt đầu của thẻ). */
    const ghi = (the: TheHoc, g: Grade, hienLuc: number): boolean => {
        if (daGhi.current.has(the.id)) return false;
        daGhi.current.add(the.id);
        const now = Date.now();
        const bat = Math.max(hienLuc, lanCuoi.current);
        lanCuoi.current = now;
        const viec = themLuot({
            uid: ulidTangDan(now),
            card_id: the.id,
            session_id: phienId,
            mode: cheDo,
            rating: SO_CHAM[g],
            reviewed_at_ms: now,
            duration_ms: Math.max(0, now - bat),
        });
        dangGhi.current.push(viec.catch(() => {}));
        return true;
    };

    return { ghi, daGhi, daGuiHet };
}

/** Kết quả một lượt: mức sau khi chấm (lượt ôn lại giữ nguyên mức). */
export function ketQua(the: TheHoc, g: Grade, before: number, ghi: boolean): Done {
    return { id: the.id, grade: g, before, after: ghi ? gradeCard(before, g).level : before, ghi };
}

export function Header({ onExit, pos, total, finished = false }: { onExit: () => void; pos: number; total: number; finished?: boolean }) {
    const pct = total === 0 ? 0 : Math.round((Math.min(pos, total) / total) * 100);
    return (
        <header className="mx-auto flex w-full max-w-[640px] items-center gap-4 px-4 pt-3 pb-2">
            <button
                type="button"
                onClick={onExit}
                aria-label={t('hoc.thoat')}
                className="flex size-11 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink"
            >
                <X size={24} aria-hidden="true" />
            </button>
            <div
                role="progressbar"
                aria-label={t('hoc.tienDo')}
                aria-valuemin={0}
                aria-valuemax={total}
                aria-valuenow={Math.min(pos, total)}
                className="h-2 flex-1 overflow-hidden rounded-sm bg-sunken"
            >
                <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${pct}%` }} />
            </div>
            <span className="min-w-12 text-right text-label font-semibold tabular-nums text-ink-2">
                {Math.min(pos + (finished || total === 0 ? 0 : 1), total)}/{total}
            </span>
            <ChoGui />
        </header>
    );
}

/** Một câu tổng kết thay cho ô số: bỏ phần bằng 0 cho dễ đọc. */
export function sessionLine(total: number, fresh: number) {
    if (fresh === 0) return t('hoc.tongKetOn', { n: total });
    if (fresh === total) return t('hoc.tongKetMoi', { n: total });
    return t('hoc.tongKet', { n: total, moi: fresh, on: total - fresh });
}

export function Summary(props: {
    done: Done[];
    byId: Map<number, TheHoc>;
    chuDe: string | null;
    chiOn: boolean;
    cheDo: CheDo;
    tenChuDe?: string;
    daGuiHet: boolean;
}) {
    const { done, byId, chuDe, chiOn, cheDo, tenChuDe } = props;
    const { settings } = useStore();
    const { cho } = useHangCho();
    const [conLai, setConLai] = useState<ConLai | null>(null);
    // Máy chủ đã nhận hết lượt của phiên này (mất mạng thì chờ tới khi hàng chờ gửi được)
    const daGui = props.daGuiHet && cho === 0;

    // Biết phiên tới còn gì sau khi máy chủ đã nhận hết lượt của phiên này
    useEffect(() => {
        if (!daGui) return;
        let bo = false;
        api.lay<{ data: ConLai }>(`/hoc/con-lai${truyVan({ chu_de: chuDe, on: chiOn ? '1' : null, che_do: cheDo === 'flashcard' ? null : cheDo })}`).then(
            ({ data }) => !bo && setConLai(data),
            () => {},
        );
        return () => {
            bo = true;
        };
    }, [daGui, chuDe, chiOn, cheDo]);

    // Chỉ lượt được tính (lượt đầu của mỗi thẻ): lượt ôn lại sau khi Quên không đổi mức, không đếm
    const stats = useMemo(() => {
        const tinh = done.filter((d) => d.ghi);
        const grown = tinh.map((d) => ({ id: d.id, from: d.before, to: d.after })).filter((c) => c.to > c.from);
        const perGrade = Object.fromEntries(GRADES.map((g) => [g.id, tinh.filter((d) => d.grade === g.id).length])) as Record<Grade, number>;
        return { total: tinh.length, fresh: tinh.filter((d) => d.before === 0).length, grown, perGrade };
    }, [done]);
    const newLimit = settings.newPerSession === 'all' ? Infinity : settings.newPerSession;
    const more = conLai !== null && conLai.review + conLai.new > 0;
    const xong = conLai !== null && !more;
    const back = chuDe ? { to: `/chu-de/${chuDe}`, label: t('chuDe.veChuDe') } : { to: '/hom-nay', label: t('chung.veHomNay') };

    return (
        <main className="mx-auto w-full max-w-[640px] flex-1 px-5 py-6">
            <h1 className="text-headline font-bold">{t('hoc.xongPhien')}</h1>
            {tenChuDe && <p className="text-ink-2">{tenChuDe}</p>}
            <p className="mt-4 text-title">
                {sessionLine(stats.total, stats.fresh)} {stats.grown.length > 0 ? t('hoc.theLenMuc', { n: stats.grown.length }) : t('hoc.chuaLenMuc')}
            </p>
            <p className="mt-1 text-label text-ink-2 tabular-nums">{GRADES.map((g) => `${g.label} ${stats.perGrade[g.id]}`).join(' · ')}</p>
            {props.daGuiHet && cho > 0 && <p className="mt-3 text-label text-ink-2">{t('hoc.conChoGui', { n: cho })}</p>}

            {stats.grown.length > 0 && <CayLonLen grown={stats.grown} byId={byId} />}

            {xong && <p className="mt-8 font-semibold">{t('hoc.hetChoNay')}</p>}
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                {more && (
                    <Link
                        to={duongHoc(chuDe, chiOn, cheDo)}
                        className="flex min-h-12 items-center justify-center rounded-md bg-tim px-6 font-bold text-on-tim hover:bg-tim-strong"
                    >
                        {conLai.new > 0 ? t('chuDe.hocTiep') : t('hoc.onTiep')}
                        <span className="ml-2 text-label font-semibold tabular-nums">{t('chuDe.onVaMoi', { on: conLai.review, moi: Math.min(conLai.new, newLimit) })}</span>
                    </Link>
                )}
                <Link
                    to={back.to}
                    className={`flex min-h-12 items-center justify-center rounded-md px-6 font-semibold ${
                        more ? 'border border-line-strong hover:bg-sunken' : 'bg-tim font-bold text-on-tim hover:bg-tim-strong'
                    }`}
                >
                    {back.label}
                </Link>
            </div>
        </main>
    );
}

function CayLonLen({ grown, byId }: { grown: { id: number; from: number; to: number }[]; byId: Map<number, TheHoc> }) {
    return (
        <section aria-labelledby="cay-lon" className="mt-8">
            <h2 id="cay-lon" className="text-title font-bold">
                {t('hoc.cayLonLen')}
            </h2>
            <ul className="mt-2 grid gap-x-8 sm:grid-cols-2">
                {grown.map((c, i) => (
                    <CayMot key={c.id} c={c} i={i} the={byId.get(c.id)} />
                ))}
            </ul>
        </section>
    );
}

function CayMot({ c, i, the }: { c: { id: number; from: number; to: number }; i: number; the: TheHoc | undefined }) {
    return (
        <li className="flex min-h-12 items-center justify-between gap-4 border-b border-line py-2">
            <span className="min-w-0 font-semibold break-words">{the && <TuDuc the={the} />}</span>
            <span className="flex shrink-0 items-center gap-1 text-label text-ink-2">
                <LevelIcon level={c.from} size={20} title="" className="text-tim" />
                <ArrowRight size={14} aria-hidden="true" />
                <span className="lb-grow inline-flex" style={{ animationDelay: `${i * 80}ms` }}>
                    <LevelIcon level={c.to} size={28} title="" className="text-tim" />
                </span>
                <span className="sr-only">{t('hoc.tuMucLen', { muc: levelName(c.from) })}</span>
                {levelName(c.to)}
            </span>
        </li>
    );
}

export function Empty({ onExit, lyDo }: { onExit: () => void; lyDo?: string }) {
    return (
        <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col items-center justify-center px-5 text-center">
            <h1 className="text-headline font-bold">{t('hoc.khongCoThe')}</h1>
            <p className="mt-2 text-ink-2">{lyDo ?? t('hoc.khongCoTheLyDo')}</p>
            <button type="button" onClick={onExit} className="mt-6 min-h-12 rounded-md border border-line-strong px-6 font-semibold hover:bg-sunken">
                {t('chung.quayLai')}
            </button>
        </main>
    );
}
