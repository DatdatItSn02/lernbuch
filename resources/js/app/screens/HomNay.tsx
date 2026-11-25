import { ChevronRight, Coins, Flame } from 'lucide-react';
import { useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Link } from 'react-router';
import { Choice } from '../components/Choice';
import { LevelBar } from '../components/LevelBar';
import { SeasonScene } from '../components/SeasonScene';
import { Sheet } from '../components/Shell';
import { currentTopicId, dailyGoal, pastReviews, topics, words } from '../data/demo';
import { dueDay, isDue, isNew, levelCounts } from '../lib/session';
import { NEW_COUNTS, useStore, type NewCount } from '../state/store';
import { useTitle } from '../lib/useTitle';

const WEEKDAY_SHORT = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
const WEEKDAY_LONG = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
const PAST_DAYS = pastReviews.length; // 10 ngày qua
const FUTURE_DAYS = 3;

function greeting(h: number) {
    if (h >= 5 && h < 11) return 'Chào buổi sáng';
    if (h >= 11 && h < 13) return 'Chào buổi trưa';
    if (h >= 13 && h < 18) return 'Chào buổi chiều';
    return 'Chào buổi tối';
}

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
    const max = Math.max(...days.map((d) => d.count), 1);
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
            <header className="relative">
                <SeasonScene season={season} className="block h-24 w-full md:absolute md:inset-y-0 md:right-0 md:h-full md:w-1/2" />
                <div className="relative px-5 pt-2 pb-4 md:px-8 md:pt-8">
                    <h1 className="font-display text-headline font-semibold">{greeting(now.getHours())}</h1>
                    <p className="text-ink-2">
                        {dayLabel(now)}
                    </p>
                </div>
            </header>

            <section aria-labelledby="chuoi-ngay" className="border-y border-line px-5 py-4 md:px-8">
                <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                    <h2 id="chuoi-ngay" className="text-title font-bold">14 ngày</h2>
                    <p className="flex items-center gap-4 text-label font-semibold tabular-nums">
                        <span className="flex items-center gap-1.5">
                            <Flame size={18} aria-hidden="true" className="text-warning-ink" />
                            Chuỗi {streak} ngày
                        </span>
                        <span className="flex items-center gap-1.5">
                            <Coins size={18} aria-hidden="true" className="text-warning-ink" />
                            {Math.floor(coins)} xu
                        </span>
                    </p>
                </div>
                <div
                    ref={tabsRef}
                    role="tablist"
                    aria-label="Chọn ngày"
                    onKeyDown={onStripKey}
                    className="mt-3 grid grid-cols-14 gap-1"
                >
                    {days.map((d) => {
                        const active = d.offset === selected;
                        const h = d.count === 0 ? 0 : Math.max(10, Math.round((d.count / max) * 100));
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
                                className={`flex min-h-24 min-w-0 flex-col items-center justify-end gap-1 rounded-md border pt-2 pb-1.5 transition-colors duration-200 ${
                                    active ? 'border-leaf bg-leaf-tint' : 'border-transparent hover:bg-sunken'
                                }`}
                            >
                                <span className="flex h-12 w-full items-end justify-center" aria-hidden="true">
                                    {d.count === 0 ? <span className="mb-0.5 size-1.5 rounded-pill bg-line-strong" /> : <Sprout ratio={h / 100} kind={d.kind} />}
                                </span>
                                {/* Tên đọc ra bắt đầu bằng chữ nhìn thấy (T2 16), rồi tới ngày đủ và số thẻ */}
                                <span className={`text-[calc(0.6875rem*var(--type-scale))] leading-none ${d.kind === 'today' ? 'font-bold text-leaf' : 'text-ink-2'}`}>
                                    {d.kind === 'today' ? 'Nay' : WEEKDAY_SHORT[d.date.getDay()]}
                                </span>
                                <span className="text-label leading-none font-semibold tabular-nums">{d.date.getDate()}</span>
                                <span className="sr-only">{`, ${dayLabel(d.date)}: ${what}`}</span>
                            </button>
                        );
                    })}
                </div>
            </section>

            <div className="grid md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:divide-x md:divide-line">
                <section id={panelId} role="tabpanel" aria-label={day.kind === 'today' ? 'Hôm nay' : dayLabel(day.date)} className="px-5 py-5 md:px-8">
                    {day.kind === 'today' ? (
                        <TodayPanel due={due.length} newNext={newNext} reviewedToday={reviewedToday} topicName={currentTopic.name} topicId={currentTopic.id} />
                    ) : (
                        <OtherDayPanel day={day} />
                    )}

                    {day.kind === 'today' && (
                        <Choice<NewCount>
                            className="mt-6"
                            legend="Số từ mới mỗi lượt"
                            name={`${panelId}-tu-moi`}
                            value={settings.newPerSession}
                            onChange={(n) => dispatch({ type: 'settings', patch: { newPerSession: n } })}
                            options={NEW_COUNTS.map((n) => ({ value: n, label: n === 'all' ? 'Tất cả' : String(n) }))}
                        />
                    )}
                </section>

                <div className="border-t border-line px-5 py-5 md:border-t-0 md:px-8">
                    <section aria-labelledby="den-han">
                        <h2 id="den-han" className="text-title font-bold">Đến hạn theo chủ đề</h2>
                        <ul className="mt-3 divide-y divide-line">
                            {topics.map((t) => {
                                const ids = words.filter((w) => w.topicId === t.id).map((w) => w.id);
                                const dueN = ids.filter((id) => isDue(progress[id])).length;
                                const newN = ids.filter((id) => isNew(progress[id])).length;
                                return (
                                    <li key={t.id}>
                                        <Link to={`/chu-de/${t.id}`} className="group flex items-center gap-4 py-3">
                                            <div className="min-w-0 flex-1">
                                                <span className="block font-semibold group-hover:underline">{t.name}</span>
                                                <span className="block text-label text-ink-2 tabular-nums">
                                                    {dueN} đến hạn · {newN} từ mới
                                                </span>
                                                <div className="mt-2 max-w-80">
                                                    <LevelBar counts={levelCounts(ids, progress)} compact />
                                                </div>
                                            </div>
                                            <ChevronRight size={20} aria-hidden="true" className="text-ink-3" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section aria-labelledby="che-do" className="mt-6">
                        <h2 id="che-do" className="text-title font-bold">Chế độ học</h2>
                        <ul className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-2 xl:grid-cols-3">
                            <li>
                                <Link to="/hoc" className="flex min-h-12 items-center justify-between rounded-md border border-leaf px-4 font-semibold text-ink hover:bg-leaf-tint">
                                    Flashcard
                                </Link>
                            </li>
                            {['Quiz', 'Nghe', 'Gõ từ', 'Ghép cặp', 'Đặc biệt'].map((m) => (
                                <li key={m}>
                                    <span className="flex min-h-12 items-center justify-between gap-2 rounded-md border border-dashed border-line px-4 text-ink-2">
                                        <span className="whitespace-nowrap">{m}</span>
                                        <span className="text-label whitespace-nowrap">làm ở G1</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </Sheet>
    );
}

/**
 * Mầm cây của một ngày: thân cao theo số thẻ ôn, hai lá ở ngọn.
 * Hôm nay màu lá; ngày đã qua thân nâu xám, lá xanh; ngày tới vẽ nét đứt, lá rỗng (mới là dự báo).
 */
function Sprout({ ratio, kind }: { ratio: number; kind: Day['kind'] }) {
    const y = 44 - Math.round(ratio * 30); // gốc lá; thân từ đáy (y=47) lên tới đây
    const stem = kind === 'today' ? 'var(--color-leaf)' : 'var(--color-ink-3)';
    const leaf = kind === 'today' ? 'var(--color-leaf)' : kind === 'future' ? 'var(--color-sheet)' : 'var(--color-level-4)';
    const dash = kind === 'future' ? '2.5 2' : undefined;
    return (
        <svg viewBox="0 0 20 48" width="20" height="48" className="overflow-visible">
            <path d={`M10 47V${y - 2}`} stroke={stem} strokeWidth="2" strokeLinecap="round" strokeDasharray={dash} fill="none" />
            <path d={`M10 ${y}Q5 ${y + 0.6} 3.4 ${y - 4.4}Q8.6 ${y - 5} 10 ${y}Z`} fill={leaf} stroke={stem} strokeWidth="1.2" strokeLinejoin="round" strokeDasharray={dash} />
            <path d={`M10 ${y - 2}Q15 ${y - 1.4} 16.6 ${y - 6.4}Q11.4 ${y - 7} 10 ${y - 2}Z`} fill={leaf} stroke={stem} strokeWidth="1.2" strokeLinejoin="round" strokeDasharray={dash} />
        </svg>
    );
}

function TodayPanel({ due, newNext, reviewedToday, topicName, topicId }: { due: number; newNext: number; reviewedToday: number; topicName: string; topicId: string }) {
    const pct = Math.min(100, Math.round((reviewedToday / dailyGoal) * 100));
    const nothing = due === 0 && newNext === 0;
    return (
        <>
            {nothing ? (
                <p className="font-semibold">Hôm nay đã ôn hết thẻ đến hạn. Chọn thêm từ mới ở chủ đề kế tiếp.</p>
            ) : (
                <Link
                    to="/hoc"
                    className="flex min-h-14 w-full items-center justify-between gap-4 rounded-md bg-leaf px-5 py-3 text-on-leaf transition-colors duration-200 hover:bg-leaf-strong"
                >
                    <span>
                        <span className="block text-title font-bold">Học ngay</span>
                        <span className="block text-label tabular-nums">
                            {due} thẻ đến hạn · {newNext} từ mới
                        </span>
                    </span>
                    <ChevronRight size={24} aria-hidden="true" />
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
                    className="mt-2 h-2 overflow-hidden rounded-pill bg-sunken"
                >
                    <div className="h-full rounded-pill bg-leaf transition-[width] duration-300" style={{ width: `${pct}%` }} />
                </div>
            </div>
        </>
    );
}

function OtherDayPanel({ day }: { day: Day }) {
    if (day.kind === 'future') {
        return (
            <>
                <h2 className="text-title font-bold">{dayLabel(day.date)}</h2>
                <p className="mt-1 text-ink-2">
                    {day.count === 0 ? 'Chưa có thẻ nào đến hạn ngày này.' : `${day.count} thẻ sẽ đến hạn. Ôn đều mỗi ngày để không dồn lại.`}
                </p>
            </>
        );
    }
    return (
        <>
            <h2 className="text-title font-bold">{dayLabel(day.date)}</h2>
            <p className="mt-1 text-ink-2">{day.count === 0 ? 'Không học ngày này.' : `Đã ôn ${day.count} thẻ.`}</p>
        </>
    );
}
