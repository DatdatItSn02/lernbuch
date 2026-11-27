import { ArrowRight, ChevronRight, Coins, Flame } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { Link } from 'react-router';
import { CardBox } from '../components/CardBox';
import { Choice } from '../components/Choice';
import { LevelBar } from '../components/LevelBar';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { InkSprig, Margin, PenTick } from '../components/Vo';
import { khiNhanLo } from '../lib/hangCho';
import type { HomNayData, NgayHoc } from '../lib/hoc';
import { useApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { NEW_COUNTS, useStore, type NewCount } from '../state/store';
import { so, t } from '../i18n';
import { tGhep } from '../i18n/ghep';

// Tên thứ trong tuần theo getUTCDay() (0 = Chủ nhật)
const THU_NGAN = ['ngay.cnNgan', 'ngay.t2Ngan', 'ngay.t3Ngan', 'ngay.t4Ngan', 'ngay.t5Ngan', 'ngay.t6Ngan', 'ngay.t7Ngan'] as const;
const THU_DAI = ['ngay.cn', 'ngay.t2', 'ngay.t3', 'ngay.t4', 'ngay.t5', 'ngay.t6', 'ngay.t7'] as const;

/** Ngày "YYYY-MM-DD" (ngày theo múi giờ người học, máy chủ đã tính) thành thứ, ngày, tháng; không phụ thuộc giờ của máy. */
function phanNgay(ngay: string) {
    const [y, m, d] = ngay.split('-').map(Number);
    return { thu: new Date(Date.UTC(y, m - 1, d)).getUTCDay(), ngay: d, thang: m };
}

const dayLabel = (ngay: string) => {
    const p = phanNgay(ngay);
    return t('ngay.nhan', { thu: t(THU_DAI[p.thu]), ngay: p.ngay, thang: p.thang });
};

export function HomNay() {
    const { season, settings, dispatch } = useStore();
    useTitle(t('khung.homNay'));
    const { duLieu, loi, taiLai } = useApi<{ data: HomNayData }>('/hom-nay');

    // Máy chủ vừa nhận thêm lượt chấm (gửi từ hàng chờ): tải lại số liệu
    useEffect(() => khiNhanLo(() => taiLai()), [taiLai]);

    if (!duLieu) {
        return (
            <Sheet className="px-5 md:px-8">
                <h1 className="sr-only">{t('khung.homNay')}</h1>
                {loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />}
            </Sheet>
        );
    }

    const d = duLieu.data;
    const newNext = settings.newPerSession === 'all' ? (d.new_topic?.new ?? 0) : Math.min(settings.newPerSession, d.new_available);

    return (
        <Sheet className="overflow-hidden">
            <div className="grid md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:divide-x md:divide-line">
                <div className="pt-5 pb-6 md:pt-8 md:pb-8">
                    <Margin aside={<InkSprig season={season} size={40} />} className="pr-5 md:pr-8">
                        <p className="text-label text-ink-2">
                            {dayLabel(d.date)}
                            <span className="ml-3 inline-flex items-center gap-1 align-middle tabular-nums">
                                <Flame size={15} aria-hidden="true" className="text-pen" />
                                {t('homNay.chuoi', { n: d.streak })}
                            </span>
                            <span className="ml-3 inline-flex items-center gap-1 align-middle tabular-nums">
                                <Coins size={15} aria-hidden="true" className="text-warning-ink" />
                                {t('homNay.xu', { n: so(d.coins) })}
                            </span>
                        </p>
                        <h1 className="mt-2 text-headline font-bold">
                            {d.due === 0 && newNext === 0
                                ? t('homNay.daOnHet')
                                : tGhep('homNay.homNayCo', { on: <Mark>{t('homNay.theCanOn', { n: d.due })}</Mark>, moi: <Mark>{t('homNay.tuMoiN', { n: newNext })}</Mark> })}
                        </h1>
                    </Margin>

                    <div className="mt-5 px-5 md:px-8">
                        <CardBox counts={d.levels} due={d.due_by_level} fresh={newNext} />
                        <TodayPanel d={d} newNext={newNext} />
                        <Choice<NewCount>
                            className="mt-6"
                            legend={t('homNay.soTuMoi')}
                            name="so-tu-moi"
                            value={settings.newPerSession}
                            onChange={(n) => dispatch({ type: 'settings', patch: { newPerSession: n } })}
                            options={NEW_COUNTS.map((n) => ({ value: n, label: n === 'all' ? t('chung.tatCa') : String(n) }))}
                        />
                    </div>
                </div>

                <div className="border-t border-line py-6 md:border-t-0 md:py-8">
                    <MuoiBonNgay days={d.days} />

                    <section aria-labelledby="den-han" className="mt-8 px-5 md:px-8">
                        <h2 id="den-han" className="text-title font-bold">{t('homNay.denHanTheoChuDe')}</h2>
                        {d.due_topics.length === 0 ? (
                            <p className="mt-2 text-label text-ink-2">{t('homNay.chuaCoDenHan')}</p>
                        ) : (
                            <ul className="mt-3 space-y-3">
                                {d.due_topics.map((cd) => (
                                    <li key={cd.slug}>
                                        <Link to={`/hoc?chu-de=${cd.slug}`} className="group block rounded-md border border-line bg-sheet transition-colors duration-200 hover:border-line-strong">
                                            <span className="flex items-center justify-between gap-3 border-b-2 border-pen/70 px-4 py-2.5">
                                                <span className="min-w-0 font-semibold group-hover:underline">{cd.name}</span>
                                                <ChevronRight size={18} aria-hidden="true" className="shrink-0 text-ink-3" />
                                            </span>
                                            <span className="block px-4 pt-2 pb-3">
                                                <span className="block text-label text-ink-2 tabular-nums">
                                                    {tGhep('homNay.chuDeDem', { denHan: <span className="font-semibold text-pen">{t('homNay.denHanN', { n: cd.due })}</span>, moi: cd.new, qua: cd.levels[5] })}
                                                </span>
                                                <span className="mt-2 block">
                                                    <LevelBar counts={cd.levels} compact />
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section aria-labelledby="che-do" className="mt-8 px-5 md:px-8">
                        <h2 id="che-do" className="text-title font-bold">{t('homNay.cheDoHoc')}</h2>
                        <p className="mt-2">
                            <Link to="/hoc" className="inline-flex min-h-11 items-center gap-2 rounded-md border border-tim px-4 font-semibold text-tim transition-colors duration-200 hover:bg-tim-tint">
                                {t('cheDo.flashcard')}
                            </Link>
                        </p>
                        <p className="mt-2 text-label text-ink-2">{t('homNay.cheDoSapCo')}</p>
                    </section>
                </div>
            </div>
        </Sheet>
    );
}

/** Chữ được tô như bút dạ quang mực tím nhạt dưới chân chữ. */
function Mark({ children }: { children: string | (string | number)[] }) {
    return <span className="bg-[linear-gradient(transparent_60%,var(--color-tim-tint)_60%)] text-tim">{children}</span>;
}

/** Vòng bút đỏ khoanh ô hôm nay. */
function TodayRing() {
    return (
        <svg viewBox="0 0 24 60" preserveAspectRatio="none" aria-hidden="true" className="pointer-events-none absolute inset-0.5 h-[calc(100%-4px)] w-[calc(100%-4px)] text-pen" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
            <path d="M15 3C7 2 2.5 12 2.6 30c.1 17 4 27 10 27s9.6-11 9-28C21 13 18 4 10.4 5.4" vectorEffect="non-scaling-stroke" />
        </svg>
    );
}

function MuoiBonNgay({ days }: { days: NgayHoc[] }) {
    const homNay = Math.max(0, days.findIndex((x) => x.kind === 'today'));
    const [selected, setSelected] = useState(homNay);
    const tabsRef = useRef<HTMLDivElement>(null);
    const panelId = useId();
    const day = days[selected] ?? days[homNay];

    const onStripKey = (e: KeyboardEvent<HTMLDivElement>) => {
        const delta = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        const edge = e.key === 'Home' ? 0 : e.key === 'End' ? days.length - 1 : null;
        if (!delta && edge === null) return;
        e.preventDefault();
        const next = edge ?? Math.min(days.length - 1, Math.max(0, selected + delta));
        setSelected(next);
        tabsRef.current?.querySelector<HTMLButtonElement>(`[data-i="${next}"]`)?.focus();
    };

    return (
        <section aria-labelledby="chuoi-ngay" className="px-5 md:px-8">
            <h2 id="chuoi-ngay" className="text-title font-bold">{t('homNay.muoiBonNgay')}</h2>
            <div ref={tabsRef} role="tablist" aria-label={t('homNay.chonNgay')} onKeyDown={onStripKey} className="mt-3 grid grid-cols-14 border-y border-line">
                {days.map((x, i) => {
                    const active = i === selected;
                    const p = phanNgay(x.date);
                    const what = x.kind === 'future' ? t('homNay.seDenHan', { n: x.count }) : x.count === 0 ? t('homNay.khongHoc') : t('homNay.onN', { n: x.count });
                    return (
                        <button
                            key={x.date}
                            type="button"
                            role="tab"
                            data-i={i}
                            aria-selected={active}
                            aria-controls={panelId}
                            tabIndex={active ? 0 : -1}
                            onClick={() => setSelected(i)}
                            className={`relative flex min-h-16 min-w-0 flex-col items-center justify-between border-r border-line py-1.5 transition-colors duration-200 last:border-r-0 ${
                                active ? 'bg-tim-tint' : 'hover:bg-sunken'
                            } text-ink-2`}
                        >
                            {/* Tên đọc ra bắt đầu bằng chữ nhìn thấy (T2 16), rồi tới ngày đủ và số thẻ */}
                            <span className={`text-[calc(0.6875rem*var(--type-scale))] leading-none ${x.kind === 'today' ? 'font-bold text-pen' : ''}`}>
                                {x.kind === 'today' ? t('ngay.nayNgan') : t(THU_NGAN[p.thu])}
                            </span>
                            <span className="flex h-4 items-center" aria-hidden="true">
                                {x.kind !== 'future' && x.count > 0 && <PenTick size={15} />}
                                {x.kind === 'future' && x.count > 0 && <span className="text-[calc(0.6875rem*var(--type-scale))] tabular-nums">{x.count}</span>}
                            </span>
                            <span className={`text-label leading-none font-semibold tabular-nums ${x.kind === 'today' ? 'text-pen' : x.kind === 'future' ? 'font-normal text-ink-2' : 'text-ink'}`}>
                                {p.ngay}
                            </span>
                            {x.kind === 'today' && <TodayRing />}
                            <span className="sr-only">{t('homNay.ngayVaViec', { ngay: dayLabel(x.date), viec: what })}</span>
                        </button>
                    );
                })}
            </div>
            <section id={panelId} role="tabpanel" aria-label={day.kind === 'today' ? t('khung.homNay') : dayLabel(day.date)} className="mt-3 text-label text-ink-2">
                {day.kind === 'today' ? (
                    <p>
                        {day.count === 0 ? t('homNay.chuaOn') : t('homNay.daOnN', { n: day.count })} {t('homNay.dauTich')}
                    </p>
                ) : (
                    <p>
                        <span className="font-semibold text-ink">{dayLabel(day.date)}:</span>{' '}
                        {day.kind === 'future'
                            ? day.count === 0
                                ? t('homNay.ngayKhongDenHan')
                                : t('homNay.ngaySeDenHan', { n: day.count })
                            : day.count === 0
                              ? t('homNay.ngayKhongHoc')
                              : t('homNay.ngayDaOn', { n: day.count })}
                    </p>
                )}
            </section>
        </section>
    );
}

function TodayPanel({ d, newNext }: { d: HomNayData; newNext: number }) {
    const pct = d.goal === 0 ? 100 : Math.min(100, Math.round((d.reviewed_today / d.goal) * 100));
    const nothing = d.due === 0 && newNext === 0;
    return (
        <>
            {nothing ? (
                <p className="mt-4 text-ink-2">
                    {tGhep('homNay.chonThemO', {
                        chuDe: (
                            <Link to="/chu-de" className="font-semibold text-ink underline underline-offset-2">
                                {t('khung.chuDe')}
                            </Link>
                        ),
                    })}
                </p>
            ) : (
                <Link to="/hoc" className="mt-4 flex min-h-14 w-full items-center justify-between gap-4 rounded-md bg-tim px-5 py-3 text-on-tim transition-colors duration-200 hover:bg-tim-strong">
                    <span className="text-title font-bold">{t('homNay.hocNgay')}</span>
                    <span className="flex items-center gap-2 text-label tabular-nums">
                        {t('homNay.denHanVaMoi', { denHan: d.due, moi: newNext })}
                        <ArrowRight size={20} aria-hidden="true" />
                    </span>
                </Link>
            )}
            {d.new_topic && (
                <p className="mt-3 text-label text-ink-2">
                    {t('homNay.dangHoc')}{' '}
                    <Link to={`/chu-de/${d.new_topic.slug}`} className="font-semibold text-ink underline underline-offset-2">
                        {d.new_topic.name}
                    </Link>
                </p>
            )}

            <div className="mt-6">
                <div className="flex items-baseline justify-between text-label">
                    <span id="muc-tieu" className="font-semibold">{t('homNay.mucTieuNgay')}</span>
                    <span className="tabular-nums text-ink-2">{t('homNay.mucTieuDem', { da: d.reviewed_today, can: d.goal })}</span>
                </div>
                <div
                    role="progressbar"
                    aria-labelledby="muc-tieu"
                    aria-valuemin={0}
                    aria-valuemax={d.goal}
                    aria-valuenow={Math.min(d.reviewed_today, d.goal)}
                    className="mt-2 h-2 overflow-hidden rounded-sm bg-sunken"
                >
                    <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${pct}%` }} />
                </div>
            </div>
        </>
    );
}
