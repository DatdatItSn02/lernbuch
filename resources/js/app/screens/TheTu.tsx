import { ChevronLeft, ChevronRight, Star } from 'lucide-react';
import { useId, useRef, useState, type KeyboardEvent } from 'react';
import { Link, useParams } from 'react-router';
import { Article, GermanWord, PluralLine, posLabel } from '../components/Article';
import { AudioButton } from '../components/AudioButton';
import { LevelIcon } from '../components/LevelIcon';
import { Sheet } from '../components/Shell';
import { topics, words, type Gender, type Word } from '../data/demo';
import { dueDay } from '../lib/session';
import { LEVEL_NAMES, STAGE_NAMES } from '../lib/srs';
import { useStore, type Lang } from '../state/store';
import { KhongThay } from './KhongThay';
import { useTitle } from '../lib/useTitle';

const TABS = [
    { id: 'nghia', label: 'Nghĩa và ví dụ' },
    { id: 'ngu-phap', label: 'Ngữ pháp' },
    { id: 'ghi-chu', label: 'Ghi chú' },
] as const;
type TabId = (typeof TABS)[number]['id'];

const LANGS: { id: Lang; short: string; name: string }[] = [
    { id: 'vi', short: 'VI', name: 'tiếng Việt' },
    { id: 'en', short: 'EN', name: 'tiếng Anh' },
    { id: 'zh', short: '中', name: 'tiếng Trung' },
];

const GENDER_NAME: Record<Gender, string> = { der: 'giống đực', die: 'giống cái', das: 'giống trung' };
const PRONOUNS = ['ich', 'du', 'er/sie/es', 'wir', 'ihr', 'sie/Sie'];
const NOTE_MAX = 500;

function dueText(level: number, day: number | null) {
    if (level === 0 || day === null) return 'Chưa học';
    if (day === 0) return 'Ôn hôm nay';
    if (day === 1) return 'Ôn ngày mai';
    return `Ôn sau ${day} ngày`;
}

export function TheTu() {
    const { ma } = useParams();
    const word = words.find((w) => w.id === ma);
    if (!word) {
        return <KhongThay title="Không có từ này" message="Từ này không có trong dữ liệu mẫu." back={{ to: '/chu-de', label: 'Về danh sách chủ đề' }} />;
    }
    return <WordSheet key={word.id} word={word} />;
}

function WordSheet({ word }: { word: Word }) {
    const { progress, starred, dispatch } = useStore();
    useTitle(word.lemma);
    const [tab, setTab] = useState<TabId>('nghia');
    const tabsRef = useRef<HTMLDivElement>(null);
    const baseId = useId();
    const topic = topics.find((t) => t.id === word.topicId)!;
    const inTopic = words.filter((w) => w.topicId === word.topicId);
    const index = inTopic.findIndex((w) => w.id === word.id);
    const prev = inTopic[index - 1];
    const next = inTopic[index + 1];
    const p = progress[word.id];
    const level = p?.level ?? 0;
    const isStarred = !!starred[word.id];

    const onTabKey = (e: KeyboardEvent<HTMLDivElement>) => {
        const i = TABS.findIndex((t) => t.id === tab);
        const to =
            e.key === 'ArrowRight' ? (i + 1) % TABS.length
            : e.key === 'ArrowLeft' ? (i - 1 + TABS.length) % TABS.length
            : e.key === 'Home' ? 0
            : e.key === 'End' ? TABS.length - 1
            : null;
        if (to === null) return;
        e.preventDefault();
        setTab(TABS[to].id);
        tabsRef.current?.querySelector<HTMLButtonElement>(`[data-tab="${TABS[to].id}"]`)?.focus();
    };

    return (
        <div className="mx-auto max-w-[720px]">
            <nav aria-label="Vị trí" className="px-5 pt-4 pb-2 md:px-0 md:pt-0">
                <Link to={`/chu-de/${topic.id}`} className="inline-flex min-h-11 items-center gap-1 text-label font-semibold text-ink-2 hover:text-ink">
                    <ChevronLeft size={18} aria-hidden="true" />
                    {topic.name}
                </Link>
            </nav>

            <Sheet>
                <header className="px-5 pt-5 pb-4 md:px-8 md:pt-8">
                    <div className="flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                            <h1 className="text-display font-extrabold break-words">
                                <GermanWord word={word} />
                            </h1>
                            <p className="mt-1 text-title text-ink-2">
                                <PluralLine word={word} />
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-pressed={isStarred}
                            onClick={() => dispatch({ type: 'star', id: word.id })}
                            className={`flex size-11 shrink-0 items-center justify-center rounded-md border transition-colors duration-200 ${
                                isStarred ? 'border-line text-warning-ink hover:bg-sunken' : 'border-line text-ink-2 hover:bg-sunken'
                            }`}
                        >
                            <Star size={22} aria-hidden="true" fill={isStarred ? 'currentColor' : 'none'} />
                            <span className="sr-only">Đánh dấu sao</span>
                        </button>
                    </div>
                    <p className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-label text-ink-2">
                        <span>
                            {posLabel(word)} · {word.cefr}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <LevelIcon level={level} size={20} title="" className="text-tim" />
                            {STAGE_NAMES[level]} · {LEVEL_NAMES[level]}
                        </span>
                        <span>{dueText(level, dueDay(p))}</span>
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <AudioButton text={word.lemma} />
                        <AudioButton text={word.lemma} slow />
                    </div>
                </header>

                <div ref={tabsRef} role="tablist" aria-label="Thông tin về từ" onKeyDown={onTabKey} className="flex overflow-x-auto border-b border-line px-2 md:px-6">
                    {TABS.map((t) => {
                        const on = t.id === tab;
                        return (
                            <button
                                key={t.id}
                                type="button"
                                role="tab"
                                data-tab={t.id}
                                id={`${baseId}-tab-${t.id}`}
                                aria-selected={on}
                                aria-controls={on ? `${baseId}-panel-${t.id}` : undefined}
                                tabIndex={on ? 0 : -1}
                                onClick={() => setTab(t.id)}
                                className={`-mb-px min-h-12 shrink-0 border-b-2 px-3 text-label font-semibold whitespace-nowrap transition-colors duration-200 ${
                                    on ? 'border-tim text-ink' : 'border-transparent text-ink-2 hover:text-ink'
                                }`}
                            >
                                {t.label}
                            </button>
                        );
                    })}
                </div>

                <div role="tabpanel" id={`${baseId}-panel-${tab}`} aria-labelledby={`${baseId}-tab-${tab}`} tabIndex={0} className="px-5 py-5 md:px-8 md:py-6">
                    {tab === 'nghia' && <MeaningTab word={word} />}
                    {tab === 'ngu-phap' && <GrammarTab word={word} />}
                    {tab === 'ghi-chu' && <NoteTab word={word} />}
                </div>
            </Sheet>

            <nav aria-label="Từ khác trong chủ đề" className="flex items-center justify-between gap-3 px-5 py-4 md:px-0">
                {prev ? (
                    <Link to={`/tu/${prev.id}`} aria-label={`Từ trước: ${prev.lemma}`} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                        <ChevronLeft size={18} aria-hidden="true" />
                        <span lang="de">{prev.lemma}</span>
                    </Link>
                ) : (
                    <span />
                )}
                <span className="text-label text-ink-2 tabular-nums">
                    {index + 1}/{inTopic.length}
                </span>
                {next ? (
                    <Link to={`/tu/${next.id}`} aria-label={`Từ sau: ${next.lemma}`} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                        <span lang="de">{next.lemma}</span>
                        <ChevronRight size={18} aria-hidden="true" />
                    </Link>
                ) : (
                    <span />
                )}
            </nav>
        </div>
    );
}

function MeaningTab({ word }: { word: Word }) {
    const { settings, dispatch } = useStore();
    const langs = settings.langs;
    const none = !langs.vi && !langs.en && !langs.zh;
    const toggle = (id: Lang) => dispatch({ type: 'settings', patch: { langs: { ...langs, [id]: !langs[id] } } });
    return (
        <>
            <div role="group" aria-label="Hiện nghĩa bằng" className="flex gap-2">
                {LANGS.map((l) => (
                    <button
                        key={l.id}
                        type="button"
                        aria-label={`${l.short} (${l.name})`}
                        aria-pressed={langs[l.id]}
                        onClick={() => toggle(l.id)}
                        className={`min-h-11 min-w-11 rounded-md border px-4 text-label font-semibold transition-colors duration-200 ${
                            langs[l.id] ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong'
                        } ${l.id === 'zh' ? 'font-zh' : ''}`}
                    >
                        {l.short}
                    </button>
                ))}
            </div>

            <dl className="mt-4 space-y-2">
                {langs.vi && (
                    <div>
                        <dt className="sr-only">Tiếng Việt</dt>
                        <dd className="text-title font-bold">{word.vi}</dd>
                    </div>
                )}
                {langs.en && (
                    <div>
                        <dt className="sr-only">Tiếng Anh</dt>
                        <dd lang="en" className="text-ink-2">{word.en}</dd>
                    </div>
                )}
                {langs.zh && (
                    <div>
                        <dt className="sr-only">Tiếng Trung</dt>
                        <dd lang="zh" className="font-zh text-ink-2">{word.zh}</dd>
                    </div>
                )}
            </dl>
            {none && <p className="mt-4 text-ink-2">Đang tắt cả ba thứ tiếng. Bật một nút ở trên để xem nghĩa.</p>}

            <h2 className="mt-6 text-label font-semibold text-ink-2">Ví dụ</h2>
            {word.examples.length === 0 ? (
                <p className="mt-2 text-ink-2">Chưa có câu ví dụ cho từ này.</p>
            ) : (
                <ul className="mt-2 divide-y divide-line">
                    {word.examples.slice(0, 3).map((ex) => (
                        <li key={ex.de} className="flex items-start gap-3 py-3">
                            <AudioButton text={ex.de} label="Nghe câu ví dụ" iconOnly />
                            <p className="min-w-0 pt-2">
                                <span lang="de" className="block font-semibold">{ex.de}</span>
                                <span className="block text-ink-2">{ex.vi}</span>
                            </p>
                        </li>
                    ))}
                </ul>
            )}

            {word.tip && (
                <p className="mt-5 rounded-md border border-dashed border-line-strong px-4 py-3 font-hand text-hand">
                    <span className="sr-only">Mẹo: </span>
                    {word.tip}
                </p>
            )}
        </>
    );
}

function GrammarTab({ word }: { word: Word }) {
    if (word.pos === 'noun') {
        return (
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3">
                {word.gender && (
                    <>
                        <dt className="text-ink-2">Giống</dt>
                        <dd>
                            <span lang="de"><Article gender={word.gender} /></span> ({GENDER_NAME[word.gender]})
                        </dd>
                    </>
                )}
                <dt className="text-ink-2">Số ít</dt>
                <dd>
                    <GermanWord word={word} />
                </dd>
                <dt className="text-ink-2">Số nhiều</dt>
                <dd>
                    <PluralLine word={word} />
                </dd>
            </dl>
        );
    }
    if (word.pos === 'verb') {
        const parts = word.principalParts?.split(' · ') ?? [];
        return (
            <>
                {parts.length === 3 && (
                    <table className="w-full text-left">
                        <caption className="mb-2 text-left text-label font-semibold text-ink-2">Ba dạng chính</caption>
                        <thead>
                            <tr className="border-b border-line text-label text-ink-2">
                                <th scope="col" className="py-2 pr-4 font-semibold">Hiện tại (ngôi 3)</th>
                                <th scope="col" className="py-2 pr-4 font-semibold">Quá khứ</th>
                                <th scope="col" className="py-2 font-semibold">Perfekt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr lang="de">
                                {parts.map((x) => (
                                    <td key={x} className="py-2 pr-4 font-semibold">{x}</td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                )}
                {word.present && (
                    <table className="mt-6 w-full max-w-sm text-left">
                        <caption className="mb-2 text-left text-label font-semibold text-ink-2">Chia thì hiện tại (Präsens)</caption>
                        <tbody lang="de" className="divide-y divide-line">
                            {word.present.map((form, i) => (
                                <tr key={PRONOUNS[i]}>
                                    <th scope="row" className="py-2 pr-6 font-normal text-ink-2">{PRONOUNS[i]}</th>
                                    <td className="py-2 font-semibold">{form}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </>
        );
    }
    return word.comparison ? (
        <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3">
            <dt className="text-ink-2">Nguyên dạng</dt>
            <dd lang="de" className="font-semibold">{word.lemma}</dd>
            <dt className="text-ink-2">So sánh hơn</dt>
            <dd lang="de" className="font-semibold">{word.comparison.split(' · ')[0]}</dd>
            <dt className="text-ink-2">So sánh nhất</dt>
            <dd lang="de" className="font-semibold">{word.comparison.split(' · ')[1]}</dd>
        </dl>
    ) : (
        <p className="text-ink-2">Chưa có ghi chú ngữ pháp cho từ này.</p>
    );
}

function NoteTab({ word }: { word: Word }) {
    const { notes, dispatch } = useStore();
    const id = useId();
    const text = notes[word.id] ?? '';
    return (
        <>
            <label htmlFor={id} className="text-label font-semibold">
                Ghi chú riêng cho từ này
            </label>
            <textarea
                id={id}
                value={text}
                maxLength={NOTE_MAX}
                rows={5}
                aria-describedby={`${id}-goi-y`}
                onChange={(e) => dispatch({ type: 'note', id: word.id, text: e.target.value })}
                className="mt-2 block w-full rounded-md border border-line-strong bg-paper px-3 py-2 text-body placeholder:text-ink-2"
                placeholder="Ví dụ: hay gặp ở tiệm bánh gần nhà"
            />
            <p id={`${id}-goi-y`} className="mt-2 flex justify-between gap-4 text-label text-ink-2">
                <span>Dữ liệu mẫu: ghi chú mất khi tải lại trang.</span>
                <span className="tabular-nums">
                    {text.length}/{NOTE_MAX}
                </span>
            </p>
        </>
    );
}
