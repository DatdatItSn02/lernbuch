import { ArrowRight, ChevronRight, Coins, Flame } from 'lucide-react';
import { useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Link } from 'react-router';
import { Choice } from '../components/Choice';
import { LevelBar } from '../components/LevelBar';
import { CardBox } from '../components/CardBox';
import { InkSprig, Margin, PenTick } from '../components/Vo';
import { Sheet } from '../components/Shell';
import { currentTopicId, dailyGoal, pastReviews, topics, words } from '../data/demo';
import { dueDay, isDue, isNew, levelCounts } from '../lib/session';
import { NEW_COUNTS, useStore, type NewCount } from '../state/store';
import { useTitle } from '../lib/useTitle';

const WEEKDAY_SHORT = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
const WEEKDAY_LONG = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
const PAST_DAYS = pastReviews.length; // 10 ngày qua
const FUTURE_DAYS = 3;

const dayLabel = (d: Date) => `${WEEKDAY_LONG[d.getDay()]}, ${d.getDate()}/${d.getMonth() + 1}`;

type Day = { offset: number; date: Date; count: number; kind: 'past' | 'today' | 'future' };

export function HomNay({ now: fixedNow }: { now?: Date }) {
    const { progress, reviewedToday, coins, season, settings, dispatch } = useStore();
    useTitle('Hôm nay');
    // Giữ một mốc giờ cho cả lần mở trang (test truyền giờ cố định)
    const [now] = useState(() => fixedNow ?? new Date());
    const [selected, setSelected] = useState(0);
    const tabsRef = useRef<HTMLDivElement>(null);
    const panelId = useId();

    const days: Day[] = useMemo(() => {
        const forecast = [0, 0, 0, 0];
        for (const w of words) {
            const d = dueDay(progress[w.id]);
            if (d !== null && d >= 1 && d <= FUTURE_DAYS) forecast[d]++;
        }
        return Array.from({ length: PAST_DAYS + 1 + FUTURE_DAYS }, (_, i) => {
            const offset = i - PAST_DAYS;
            const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset);
            if (offset < 0) return { offset, date, count: pastReviews[i], kind: 'past' as const };
            if (offset === 0) return { offset, date, count: reviewedToday, kind: 'today' as const };
            return { offset, date, count: forecast[offset], kind: 'future' as const };
        });
    }, [now, progress, reviewedToday]);

    const streak = useMemo(() => {
        let n = reviewedToday > 0 ? 1 : 0;
        for (let i = pastReviews.length - 1; i >= 0 && pastReviews[i] > 0; i--) n++;
        return n;
    }, [reviewedToday]);

    const due = words.filter((w) => isDue(progress[w.id]));
    const currentTopic = topics.find((t) => t.id === currentTopicId)!;
    const newInTopic = words.filter((w) => w.topicId === currentTopicId && isNew(progress[w.id])).length;
    const newNext = settings.newPerSession === 'all' ? newInTopic : Math.min(newInTopic, settings.newPerSession);
    const allIds = useMemo(() => words.map((w) => w.id), []);
    const counts = levelCounts(allIds, progress);
    const dueByLevel = [0, 0, 0, 0, 0, 0];
    for (const w of due) dueByLevel[progress[w.id].level]++;
    const day = days.find((d) => d.offset === selected)!;

    const onStripKey = (e: KeyboardEvent<HTMLDivElement>) => {
        const delta = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        const edge = e.key === 'Home' ? -PAST_DAYS : e.key === 'End' ? FUTURE_DAYS : null;
        if (!delta && edge === null) return;
        e.preventDefault();
        const next = edge ?? Math.min(FUTURE_DAYS, Math.max(-PAST_DAYS, selected + delta));
        setSelected(next);
        tabsRef.current?.querySelector<HTMLButtonElement>(`[data-offset="${next}"]`)?.focus();
    };

    return (
        <Sheet className="overflow-hidden">
            <div className="grid md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:divide-x md:divide-line">
                <div className="pt-5 pb-6 md:pt-8 md:pb-8">
                    <Margin aside={<InkSprig season={season} size={34} className="mt-1" />} className="pr-5 md:pr-8">
                        <p className="text-label text-ink-2">
                            {dayLabel(now)}
                            <span className="ml-3 inline-flex items-center gap-1 align-middle tabular-nums">
                                <Flame size={15} aria-hidden="true" className="text-pen" />
                                Chuỗi {streak} ngày
                            </span>
                            <span className="ml-3 inline-flex items-center gap-1 align-middle tabular-nums">
                                <Coins size={15} aria-hidden="true" className="text-warning-ink" />
                                {Math.floor(coins)} xu
                            </span>
                        </p>
                        <h1 className="mt-2 text-headline font-bold">
                            {due.length === 0 && newNext === 0 ? (
                                'Hôm nay đã ôn hết thẻ đến hạn.'
                            ) : (
                                <>
                                    Hôm nay có <Mark>{due.length} thẻ cần ôn</Mark> và <Mark>{newNext} từ mới</Mark>.
                                </>
                            )}
                        </h1>
                    </Margin>

                    <div className="mt-5 px-5 md:px-8">
                        <CardBox counts={counts} due={dueByLevel} fresh={newNext} />
                        <TodayPanel due={due.length} newNext={newNext} reviewedToday={reviewedToday} topicName={currentTopic.name} topicId={currentTopic.id} />
                        <Choice<NewCount>
                            className="mt-6"
                            legend="Số từ mới mỗi lượt"
                            name={`${panelId}-tu-moi`}
                            value={settings.newPerSession}
                            onChange={(n) => dispatch({ type: 'settings', patch: { newPerSession: n } })}
                            options={NEW_COUNTS.map((n) => ({ value: n, label: n === 'all' ? 'Tất cả' : String(n) }))}
                        />
                    </div>
                </div>

                <div className="border-t border-line py-6 md:border-t-0 md:py-8">
                    <section aria-labelledby="chuoi-ngay" className="px-5 md:px-8">
                        <h2 id="chuoi-ngay" className="text-title font-bold">14 ngày</h2>
                        <div
                            ref={tabsRef}
                            role="tablist"
                            aria-label="Chọn ngày"
                            onKeyDown={onStripKey}
                            className="mt-3 grid grid-cols-14 border-y border-line"
                        >
                            {days.map((d) => {
                                const active = d.offset === selected;
                                const what = d.kind === 'future' ? `${d.count} thẻ sẽ đến hạn` : d.count === 0 ? 'không học' : `ôn ${d.count} thẻ`;
                                return (
                                    <button
                                        key={d.offset}
                                        type="button"
                                        role="tab"
                                        data-offset={d.offset}
                                        aria-selected={active}
                                        aria-controls={panelId}
                                        tabIndex={active ? 0 : -1}
                                        onClick={() => setSelected(d.offset)}
                                        className={`relative flex min-h-16 min-w-0 flex-col items-center justify-between border-r border-line py-1.5 transition-colors duration-200 last:border-r-0 ${
                                            active ? 'bg-tim-tint' : 'hover:bg-sunken'
                                        } text-ink-2`}
                                    >
                                        {/* Tên đọc ra bắt đầu bằng chữ nhìn thấy (T2 16), rồi tới ngày đủ và số thẻ */}
                                        <span className={`text-[calc(0.6875rem*var(--type-scale))] leading-none ${d.kind === 'today' ? 'font-bold text-pen' : ''}`}>
                                            {d.kind === 'today' ? 'Nay' : WEEKDAY_SHORT[d.date.getDay()]}
                                        </span>
                                        <span className="flex h-4 items-center" aria-hidden="true">
                                            {d.kind === 'past' && d.count > 0 && <PenTick size={15} />}
                                            {d.kind === 'today' && d.count > 0 && <PenTick size={15} />}
                                            {d.kind === 'future' && d.count > 0 && <span className="text-[calc(0.6875rem*var(--type-scale))] tabular-nums">{d.count}</span>}
                                        </span>
                                        <span className={`text-label leading-none font-semibold tabular-nums ${d.kind === 'today' ? 'text-pen' : d.kind === 'future' ? 'font-normal text-ink-2' : 'text-ink'}`}>
                                            {d.date.getDate()}
                                        </span>
                                        {d.kind === 'today' && <TodayRing />}
                                        <span className="sr-only">{`, ${dayLabel(d.date)}: ${what}`}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <section id={panelId} role="tabpanel" aria-label={day.kind === 'today' ? 'Hôm nay' : dayLabel(day.date)} className="mt-3 text-label text-ink-2">
                            {day.kind === 'today' ? <TodayLine reviewed={reviewedToday} /> : <OtherDayPanel day={day} />}
                        </section>
                    </section>

                    <section aria-labelledby="den-han" className="mt-8 px-5 md:px-8">
                        <h2 id="den-han" className="text-title font-bold">Đến hạn theo chủ đề</h2>
                        <ul className="mt-3 space-y-3">
                            {topics.map((t) => {
                                const ids = words.filter((w) => w.topicId === t.id).map((w) => w.id);
                                const dueN = ids.filter((id) => isDue(progress[id])).length;
                                const newN = ids.filter((id) => isNew(progress[id])).length;
                                const lc = levelCounts(ids, progress);
                                return (
                                    <li key={t.id}>
                                        <Link to={`/chu-de/${t.id}`} className="group block rounded-md border border-line bg-sheet transition-colors duration-200 hover:border-line-strong">
                                            <span className="flex items-center justify-between gap-3 border-b-2 border-pen/70 px-4 py-2.5">
                                                <span className="min-w-0 font-semibold group-hover:underline">{t.name}</span>
                                                <ChevronRight size={18} aria-hidden="true" className="shrink-0 text-ink-3" />
                                            </span>
                                            <span className="block px-4 pt-2 pb-3">
                                                <span className="block text-label text-ink-2 tabular-nums">
                                                    <span className="font-semibold text-pen">{dueN} đến hạn</span> · {newN} từ mới · {lc[5]} đã thành quả
                                                </span>
                                                <span className="mt-2 block">
                                                    <LevelBar counts={lc} compact />
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section aria-labelledby="che-do" className="mt-8 px-5 md:px-8">
                        <h2 id="che-do" className="text-title font-bold">Chế độ học</h2>
                        <p className="mt-2">
                            <Link to="/hoc" className="inline-flex min-h-11 items-center gap-2 rounded-md border border-tim px-4 font-semibold text-tim transition-colors duration-200 hover:bg-tim-tint">
                                Flashcard
                            </Link>
                        </p>
                        <p className="mt-2 text-label text-ink-2">Quiz, Nghe, Gõ từ, Ghép cặp và Đặc biệt sẽ có ở bản G1.</p>
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

function TodayLine({ reviewed }: { reviewed: number }) {
    return <p>{reviewed === 0 ? 'Hôm nay chưa ôn thẻ nào.' : `Hôm nay đã ôn ${reviewed} thẻ.`} Dấu tích đỏ là ngày có học.</p>;
}

function TodayPanel({ due, newNext, reviewedToday, topicName, topicId }: { due: number; newNext: number; reviewedToday: number; topicName: string; topicId: string }) {
    const pct = Math.min(100, Math.round((reviewedToday / dailyGoal) * 100));
    const nothing = due === 0 && newNext === 0;
    return (
        <>
            {nothing ? (
                <p className="mt-4 text-ink-2">Chọn thêm từ mới ở chủ đề kế tiếp.</p>
            ) : (
                <Link
                    to="/hoc"
                    className="mt-4 flex min-h-14 w-full items-center justify-between gap-4 rounded-md bg-tim px-5 py-3 text-on-tim transition-colors duration-200 hover:bg-tim-strong"
                >
                    <span className="text-title font-bold">Học ngay</span>
                    <span className="flex items-center gap-2 text-label tabular-nums">
                        {due} thẻ đến hạn · {newNext} từ mới
                        <ArrowRight size={20} aria-hidden="true" />
                    </span>
                </Link>
            )}
            <p className="mt-3 text-label text-ink-2">
                Đang học:{' '}
                <Link to={`/chu-de/${topicId}`} className="font-semibold text-ink underline underline-offset-2">
                    {topicName}
                </Link>
            </p>

            <div className="mt-6">
                <div className="flex items-baseline justify-between text-label">
                    <span id="muc-tieu" className="font-semibold">Mục tiêu ngày</span>
                    <span className="tabular-nums text-ink-2">
                        {reviewedToday}/{dailyGoal} thẻ
                    </span>
                </div>
                <div
                    role="progressbar"
                    aria-labelledby="muc-tieu"
                    aria-valuemin={0}
                    aria-valuemax={dailyGoal}
                    aria-valuenow={Math.min(reviewedToday, dailyGoal)}
                    className="mt-2 h-2 overflow-hidden rounded-sm bg-sunken"
                >
                    <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${pct}%` }} />
                </div>
            </div>
        </>
    );
}

function OtherDayPanel({ day }: { day: Day }) {
    if (day.kind === 'future') {
        return (
            <p>
                <span className="font-semibold text-ink">{dayLabel(day.date)}:</span>{' '}
                {day.count === 0 ? 'Chưa có thẻ nào đến hạn ngày này.' : `${day.count} thẻ sẽ đến hạn. Ôn đều mỗi ngày để không dồn lại.`}
            </p>
        );
    }
    return (
        <p>
            <span className="font-semibold text-ink">{dayLabel(day.date)}:</span> {day.count === 0 ? 'Không học ngày này.' : `Đã ôn ${day.count} thẻ.`}
        </p>
    );
}
