import { ArrowRight, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { GermanWord, PluralLine } from '../components/Article';
import { AudioButton } from '../components/AudioButton';
import { Kbd } from '../components/Kbd';
import { LevelIcon } from '../components/LevelIcon';
import { PenTick } from '../components/Vo';
import { currentTopicId, topics, words, type Word } from '../data/demo';
import { buildSession, isDue, isNew } from '../lib/session';
import { speak } from '../lib/speech';
import { GRADES, LEVEL_NAMES, STAGE_NAMES, formatInterval, grade as gradeCard, type Grade } from '../lib/srs';
import { useStore } from '../state/store';
import { useTitle } from '../lib/useTitle';

type Done = { id: string; grade: Grade; before: number; after: number };

const byId = new Map(words.map((w) => [w.id, w]));
const MAX_REQUEUE = 2;

const GRADE_STYLE: Record<Grade, string> = {
    quen: 'border-danger text-danger bg-sheet hover:bg-danger-tint',
    kho: 'border-warning-ink text-warning-ink bg-sheet hover:bg-sunken',
    tot: 'border-tim bg-tim text-on-tim hover:bg-tim-strong hover:border-tim-strong',
    de: 'border-tim text-tim bg-sheet hover:bg-tim-tint',
};

const isTyping = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
/** Space/Enter trên nút hay liên kết đang có tiêu điểm thì để trình duyệt bấm chính nút đó. */
const onControl = (el: EventTarget | null) => el instanceof HTMLElement && !!el.closest('button, a');

export function Hoc() {
    const [params] = useSearchParams();
    const navigate = useNavigate();
    const { progress, settings, dispatch } = useStore();
    useTitle('Học');
    const topicId = params.get('chu-de') ?? undefined;
    const reviewOnly = params.get('on') === '1';
    const topic = topicId ? topics.find((t) => t.id === topicId) : undefined;

    // Lập phiên một lần khi mở trang; chấm thẻ không làm phiên đổi theo (trừ thẻ Quên được chèn lại)
    const [queue, setQueue] = useState<string[]>(() =>
        buildSession(words, progress, {
            dueTopicId: topicId,
            newTopicId: topicId ?? currentTopicId,
            newCount: settings.newPerSession,
            reviewOnly,
        }),
    );
    const [pos, setPos] = useState(0);
    const [revealed, setRevealed] = useState(false);
    const [done, setDone] = useState<Done[]>([]);
    const [requeued, setRequeued] = useState<Record<string, number>>({});
    const [lookBack, setLookBack] = useState(0); // 0: thẻ đang học; n: xem lại thẻ đã chấm thứ n tính từ cuối

    const finished = pos >= queue.length;
    const reviewing = lookBack > 0 ? done[done.length - lookBack] : null;
    const word = reviewing ? byId.get(reviewing.id) : finished ? undefined : byId.get(queue[pos]);
    const level = word ? (reviewing ? reviewing.after : (progress[word.id]?.level ?? 0)) : 0;
    const last = done[done.length - 1];

    const exit = useCallback(() => navigate(topicId ? `/chu-de/${topicId}` : '/hom-nay'), [navigate, topicId]);

    const doGrade = useCallback(
        (g: Grade) => {
            if (!word || reviewing || !revealed) return;
            const before = progress[word.id]?.level ?? 0;
            const after = gradeCard(before, g).level;
            dispatch({ type: 'grade', id: word.id, grade: g });
            setDone((d) => [...d, { id: word.id, grade: g, before, after }]);
            if (g === 'quen' && (requeued[word.id] ?? 0) < MAX_REQUEUE) {
                // Ôn lại sau ít phút: chèn thẻ vào sau vài thẻ nữa
                setQueue((q) => {
                    const at = Math.min(q.length, pos + 4);
                    return [...q.slice(0, at), word.id, ...q.slice(at)];
                });
                setRequeued((r) => ({ ...r, [word.id]: (r[word.id] ?? 0) + 1 }));
            }
            setPos((p) => p + 1);
            setRevealed(false);
        },
        [word, reviewing, revealed, progress, dispatch, requeued, pos],
    );

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (isTyping(e.target) || e.altKey || e.ctrlKey || e.metaKey) return;
            const k = e.key.toLowerCase();
            if (k === 'escape') return exit();
            if (k === 'arrowleft') return setLookBack((n) => Math.min(done.length, n + 1));
            if (k === 'arrowright') return setLookBack((n) => Math.max(0, n - 1));
            if (!word) return;
            if ((k === ' ' || k === 'enter') && onControl(e.target)) return;
            if (k === ' ' || k === 'spacebar') {
                e.preventDefault();
                if (reviewing) return setLookBack(0);
                return revealed ? doGrade('tot') : setRevealed(true);
            }
            const g = GRADES.find((x) => x.key === k);
            if (g && revealed) return doGrade(g.id);
            if (k === 'p') void speak(word.lemma);
            if (k === 's') void speak(word.lemma, true);
            if (k === 'e' && word.examples[0]) void speak(word.examples[0].de);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [word, revealed, reviewing, doGrade, exit, done.length]);

    const lastWord = last ? byId.get(last.id) : undefined;
    const lastRequeued = !!last && last.grade === 'quen' && queue.indexOf(last.id, pos) >= pos;
    let status: ReactNode = null;
    if (reviewing) {
        status = <>Đang xem lại thẻ đã chấm: {GRADES.find((g) => g.id === reviewing.grade)?.label}</>;
    } else if (!finished && lastWord && last && last.after > last.before) {
        status = (
            <span className="inline-flex items-center gap-1">
                <span lang="de">{lastWord.lemma}</span> lên mức {LEVEL_NAMES[last.after]}
                <span className="lb-grow inline-flex">
                    <LevelIcon level={last.after} size={20} className="text-tim" />
                </span>
            </span>
        );
    } else if (!finished && lastWord && lastRequeued) {
        status = (
            <>
                <span lang="de">{lastWord.lemma}</span> sẽ quay lại sau vài thẻ
            </>
        );
    }

    const progressPct = queue.length === 0 ? 0 : Math.round((Math.min(pos, queue.length) / queue.length) * 100);

    return (
        <div className="flex min-h-dvh flex-col bg-paper text-ink">
            <header className="mx-auto flex w-full max-w-[640px] items-center gap-4 px-4 pt-3 pb-2">
                <button
                    type="button"
                    onClick={exit}
                    aria-label="Thoát phiên học (Esc)"
                    className="flex size-11 items-center justify-center rounded-md text-ink-2 hover:bg-sunken hover:text-ink"
                >
                    <X size={24} aria-hidden="true" />
                </button>
                <div
                    role="progressbar"
                    aria-label="Tiến độ phiên học"
                    aria-valuemin={0}
                    aria-valuemax={queue.length}
                    aria-valuenow={Math.min(pos, queue.length)}
                    className="h-2 flex-1 overflow-hidden rounded-sm bg-sunken"
                >
                    <div className="h-full bg-tim transition-[width] duration-300" style={{ width: `${progressPct}%` }} />
                </div>
                <span className="min-w-12 text-right text-label font-semibold tabular-nums text-ink-2">
                    {Math.min(pos + (finished ? 0 : 1), queue.length)}/{queue.length}
                </span>
            </header>
            <p role="status" className="mx-auto min-h-6 w-full max-w-[640px] px-5 text-center text-label text-ink-2">
                {status}
            </p>

            {queue.length === 0 ? (
                <Empty onExit={exit} />
            ) : finished && !reviewing ? (
                <Summary done={done} topicId={topicId} topicName={topic?.name} newTopicId={topicId ?? currentTopicId} />
            ) : word ? (
                <Card
                    key={`${word.id}-${pos}-${lookBack}`}
                    word={word}
                    level={level}
                    revealed={revealed || !!reviewing}
                    reviewing={reviewing}
                    focusReveal={done.length > 0}
                    langs={settings.langs}
                    onReveal={() => setRevealed(true)}
                    onGrade={doGrade}
                    onBack={() => setLookBack(0)}
                />
            ) : null}
        </div>
    );
}

function Card(props: {
    word: Word;
    level: number;
    revealed: boolean;
    reviewing: Done | null;
    /** Sau khi chấm, nút vừa bấm biến mất: đặt tiêu điểm vào nút Lật thẻ của thẻ mới (thẻ đầu phiên thì không). */
    focusReveal: boolean;
    langs: { vi: boolean; en: boolean; zh: boolean };
    onReveal: () => void;
    onGrade: (g: Grade) => void;
    onBack: () => void;
}) {
    const { word, level, revealed, reviewing, focusReveal, langs } = props;
    const example = word.examples[0];
    const revealRef = useRef<HTMLButtonElement>(null);
    const answerRef = useRef<HTMLDivElement>(null);
    const wasRevealed = useRef(revealed);

    // Giữ tiêu điểm trong phiên: thẻ mới đặt vào nút Lật thẻ, lật xong đặt vào phần đáp án để trình đọc màn hình đọc tiếp.
    useEffect(() => {
        const lost = !document.activeElement || document.activeElement === document.body;
        if (!revealed && focusReveal && lost) revealRef.current?.focus();
    }, [revealed, focusReveal]);
    useEffect(() => {
        if (revealed && !wasRevealed.current && !reviewing) answerRef.current?.focus();
        wasRevealed.current = revealed;
    }, [revealed, reviewing]);
    return (
        <>
            <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col px-4 pt-6 pb-48">
                {/* Thẻ rút từ hộp: hai thẻ chờ phía sau, tai thẻ ghi mức thuộc */}
                <div className="relative">
                    <span aria-hidden="true" className="absolute inset-x-4 -top-2 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                    <span aria-hidden="true" className="absolute inset-x-2 -top-1 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                    <article className="relative flex min-h-[min(56vh,30rem)] flex-col rounded-md border border-line bg-sheet shadow-sheet">
                        <p
                            className="absolute -top-6 right-4 flex h-6 items-center gap-1 rounded-t-md px-2.5 text-label font-semibold"
                            style={{ background: `var(--color-level-${level})`, color: `var(--color-on-level-${level})` }}
                        >
                            <LevelIcon level={level} size={16} title="" className="text-tim" />
                            {level === 0 ? 'Từ mới' : `${STAGE_NAMES[level]} · ${LEVEL_NAMES[level]}`}
                        </p>
                        <div className="relative flex-1 py-6 pr-5 pl-14 md:pl-16">
                            <span aria-hidden="true" className="absolute inset-y-0 left-10 w-[1.5px] bg-pen opacity-45 md:left-12" />
                            {reviewing && (reviewing.grade === 'tot' || reviewing.grade === 'de') && (
                                <span className="absolute top-7 left-2.5 md:left-3.5">
                                    <PenTick size={22} />
                                </span>
                            )}
                            <h1 className="max-w-full text-study-word font-extrabold break-words">
                                <GermanWord word={word} />
                            </h1>
                            <p className="mt-1 text-ink-2">
                                <PluralLine word={word} />
                            </p>
                            <div className="mt-4 flex gap-2">
                                <AudioButton text={word.lemma} shortcut="P" iconOnly />
                                <AudioButton text={word.lemma} slow shortcut="S" iconOnly />
                            </div>

                            {revealed && (
                                <div ref={answerRef} tabIndex={-1} aria-label="Đáp án" className="lb-reveal mt-6 border-t border-dashed border-line-strong pt-5">
                                    {langs.vi && <p className="text-title font-bold">{word.vi}</p>}
                                    {langs.en && <p lang="en" className="mt-1 text-ink-2">{word.en}</p>}
                                    {langs.zh && <p lang="zh" className="mt-1 font-zh text-ink-2">{word.zh}</p>}
                                    {!langs.vi && !langs.en && !langs.zh && <p className="text-ink-2">Đang tắt cả ba thứ tiếng của nghĩa (bật lại ở Cài đặt).</p>}
                                    {example && (
                                        <div className="mt-5 flex items-start gap-3">
                                            <AudioButton text={example.de} label="Nghe câu ví dụ" shortcut="E" iconOnly />
                                            <p>
                                                <span lang="de" className="block font-semibold">{example.de}</span>
                                                <span className="block text-ink-2">{example.vi}</span>
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </article>
                </div>
            </main>

            <footer className="fixed inset-x-0 bottom-0 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)]">
                <div className="mx-auto max-w-[640px] px-4 py-3">
                    {reviewing ? (
                        <button type="button" onClick={props.onBack} className="flex min-h-14 w-full items-center justify-center gap-3 rounded-md border border-line-strong bg-sheet font-semibold hover:bg-sunken">
                            Về thẻ đang học <Kbd>→</Kbd>
                        </button>
                    ) : !revealed ? (
                        <button
                            ref={revealRef}
                            type="button"
                            onClick={props.onReveal}
                            className="flex min-h-14 w-full items-center justify-center gap-3 rounded-md bg-tim text-title font-bold text-on-tim transition-colors duration-200 hover:bg-tim-strong"
                        >
                            Lật thẻ <Kbd>Space</Kbd>
                        </button>
                    ) : (
                        <div role="group" aria-label="Tự chấm" className="grid grid-cols-4 gap-2">
                            {GRADES.map((g) => (
                                <button
                                    key={g.id}
                                    type="button"
                                    onClick={() => props.onGrade(g.id)}
                                    aria-keyshortcuts={g.key}
                                    className={`flex min-h-14 flex-col items-center justify-center rounded-md border-[1.5px] px-1 transition-colors duration-200 ${GRADE_STYLE[g.id]}`}
                                >
                                    <span className="font-bold">{g.label}</span>
                                    <span className="text-label tabular-nums">
                                        {formatInterval(gradeCard(level, g.id).intervalDays)} <Kbd>{g.key}</Kbd>
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </footer>
        </>
    );
}

/** Một câu tổng kết thay cho ô số: bỏ phần bằng 0 cho dễ đọc. */
export function sessionLine(total: number, fresh: number) {
    if (fresh === 0) return `Đã học ${total} thẻ ôn.`;
    if (fresh === total) return `Đã học ${total} từ mới.`;
    return `Đã học ${total} thẻ: ${fresh} từ mới, ${total - fresh} thẻ ôn.`;
}

function Summary({ done, topicId, topicName, newTopicId }: { done: Done[]; topicId?: string; topicName?: string; newTopicId: string }) {
    const { progress, settings } = useStore();
    const newLimit = settings.newPerSession === 'all' ? Infinity : settings.newPerSession;
    const stats = useMemo(() => {
        const first = new Map<string, Done>();
        const lastOf = new Map<string, Done>();
        for (const d of done) {
            if (!first.has(d.id)) first.set(d.id, d);
            lastOf.set(d.id, d);
        }
        const cards = [...first.values()];
        const grown = cards
            .map((f) => ({ id: f.id, from: f.before, to: lastOf.get(f.id)!.after }))
            .filter((c) => c.to > c.from);
        const perGrade = Object.fromEntries(GRADES.map((g) => [g.id, done.filter((d) => d.grade === g.id).length])) as Record<Grade, number>;
        return { total: cards.length, fresh: cards.filter((c) => c.before === 0).length, grown, perGrade };
    }, [done]);
    // Còn việc trong cùng phạm vi: thẻ đến hạn (kể cả thẻ Quên cần ôn lại) hoặc từ mới của chủ đề
    const moreDue = words.filter((w) => (!topicId || w.topicId === topicId) && isDue(progress[w.id])).length;
    const moreNew = words.filter((w) => w.topicId === newTopicId && isNew(progress[w.id])).length;
    const more = moreDue + moreNew > 0;
    const back = topicId ? { to: `/chu-de/${topicId}`, label: 'Về chủ đề' } : { to: '/hom-nay', label: 'Về Hôm nay' };

    return (
        <main className="mx-auto w-full max-w-[640px] flex-1 px-5 py-6">
            <h1 className="text-headline font-bold">Xong phiên</h1>
            {topicName && <p className="text-ink-2">{topicName}</p>}
            <p className="mt-4 text-title">
                {sessionLine(stats.total, stats.fresh)}{' '}
                {stats.grown.length > 0 ? `${stats.grown.length} thẻ lên mức.` : 'Chưa có thẻ nào lên mức.'}
            </p>
            <p className="mt-1 text-label text-ink-2 tabular-nums">{GRADES.map((g) => `${g.label} ${stats.perGrade[g.id]}`).join(' · ')}</p>

            {stats.grown.length > 0 && (
                <section aria-labelledby="cay-lon" className="mt-8">
                    <h2 id="cay-lon" className="text-title font-bold">
                        Cây lớn lên
                    </h2>
                    <ul className="mt-2 grid gap-x-8 sm:grid-cols-2">
                        {stats.grown.map((c, i) => {
                            const w = byId.get(c.id)!;
                            return (
                                <li key={c.id} className="flex min-h-12 items-center justify-between gap-4 border-b border-line py-2">
                                    <span className="min-w-0 font-semibold break-words">
                                        <GermanWord word={w} />
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1 text-label text-ink-2">
                                        <LevelIcon level={c.from} size={20} title="" className="text-tim" />
                                        <ArrowRight size={14} aria-hidden="true" />
                                        <span className="lb-grow inline-flex" style={{ animationDelay: `${i * 80}ms` }}>
                                            <LevelIcon level={c.to} size={28} title="" className="text-tim" />
                                        </span>
                                        <span className="sr-only">{`từ ${LEVEL_NAMES[c.from]} lên`}</span>
                                        {LEVEL_NAMES[c.to]}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            {!more && <p className="mt-8 font-semibold">Đã học hết thẻ đến hạn và từ mới ở đây.</p>}
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                {more && (
                    <Link
                        to={topicId ? `/hoc?chu-de=${topicId}` : '/hoc'}
                        className="flex min-h-12 items-center justify-center rounded-md bg-tim px-6 font-bold text-on-tim hover:bg-tim-strong"
                    >
                        {moreNew > 0 ? 'Học tiếp' : 'Ôn tiếp'}
                        <span className="ml-2 text-label font-semibold tabular-nums">
                            {moreDue} ôn · {Math.min(moreNew, newLimit)} mới
                        </span>
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

function Empty({ onExit }: { onExit: () => void }) {
    return (
        <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col items-center justify-center px-5 text-center">
            <h1 className="text-headline font-bold">Chưa có thẻ nào để học</h1>
            <p className="mt-2 text-ink-2">Thẻ đến hạn đã ôn hết và chủ đề này hết từ mới.</p>
            <button type="button" onClick={onExit} className="mt-6 min-h-12 rounded-md border border-line-strong px-6 font-semibold hover:bg-sunken">
                Quay lại
            </button>
        </main>
    );
}
