import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router';
import { GermanWord, PluralLine } from '../components/Article';
import { Choice } from '../components/Choice';
import { Kbd } from '../components/Kbd';
import { LevelBar } from '../components/LevelBar';
import { LevelIcon } from '../components/LevelIcon';
import { Sheet } from '../components/Shell';
import { topics, words, type Topic, type Word } from '../data/demo';
import { isDue, isNew, levelCounts } from '../lib/session';
import { useStore } from '../state/store';
import { KhongThay } from './KhongThay';
import { useTitle } from '../lib/useTitle';

export const PAGE_SIZE = 20;

type Cover = 'none' | 'de' | 'vi';
type PosFilter = 'all' | Word['pos'];
type LevelFilter = 'all' | 0 | 1 | 2 | 3 | 4 | 5;

const POS_FILTERS: { id: PosFilter; label: string }[] = [
    { id: 'all', label: 'Mọi loại từ' },
    { id: 'noun', label: 'Danh từ' },
    { id: 'verb', label: 'Động từ' },
    { id: 'adj', label: 'Tính từ' },
];

/** Bỏ dấu và viết thường: gõ "brotchen" ra "Brötchen", gõ "banh" ra "bánh". */
export function fold(s: string) {
    return s.toLowerCase().replace(/ß/g, 'ss').replace(/đ/g, 'd').normalize('NFD').replace(/\p{M}/gu, '');
}

const isTyping = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

export function ChuDe() {
    const { ma } = useParams();
    const topic = topics.find((t) => t.id === ma);
    if (!topic) {
        return <KhongThay title="Không có chủ đề này" message="Chủ đề này không có trong dữ liệu mẫu." back={{ to: '/chu-de', label: 'Về danh sách chủ đề' }} />;
    }
    return <TopicPage key={topic.id} topic={topic} />;
}

function TopicPage({ topic }: { topic: Topic }) {
    const { progress, settings } = useStore();
    useTitle(topic.name);
    const [params, setParams] = useSearchParams();
    const [cover, setCover] = useState<Cover>('none');
    const [level, setLevel] = useState<LevelFilter>('all');
    const [pos, setPos] = useState<PosFilter>('all');
    const [query, setQuery] = useState('');
    const [shown, setShown] = useState<Record<string, boolean>>({});
    const searchRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLHeadingElement>(null);
    const lastPage = useRef<number | null>(null);
    const uid = useId();

    const all = useMemo(() => words.filter((w) => w.topicId === topic.id), [topic.id]);
    const counts = levelCounts(all.map((w) => w.id), progress);
    const due = all.filter((w) => isDue(progress[w.id])).length;
    const fresh = all.filter((w) => isNew(progress[w.id])).length;
    const newNext = settings.newPerSession === 'all' ? fresh : Math.min(fresh, settings.newPerSession);

    const q = fold(query.trim());
    const list = all.filter(
        (w) =>
            (level === 'all' || (progress[w.id]?.level ?? 0) === level) &&
            (pos === 'all' || w.pos === pos) &&
            (!q || fold(w.lemma).includes(q) || fold(w.vi).includes(q) || fold(w.en).includes(q)),
    );
    const pages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
    const asked = Number(params.get('trang') ?? '1');
    const page = Number.isInteger(asked) && asked >= 1 ? Math.min(asked, pages) : 1;
    const rows = list.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
    const filtered = level !== 'all' || pos !== 'all' || q !== '';
    const hiddenCount = cover === 'none' ? 0 : rows.filter((w) => !shown[w.id]).length;

    // Đổi bộ lọc thì về trang 1
    const refilter = (apply: () => void) => {
        apply();
        if (params.has('trang')) setParams({}, { replace: true });
    };
    const clearFilters = () =>
        refilter(() => {
            setLevel('all');
            setPos('all');
            setQuery('');
        });

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || isTyping(e.target)) return;
            e.preventDefault();
            searchRef.current?.focus();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Sang trang khác thì đưa đầu danh sách vào tầm nhìn và đặt tiêu điểm ở đó
    useEffect(() => {
        if (lastPage.current !== null && lastPage.current !== page) {
            listRef.current?.scrollIntoView?.({ block: 'start' });
            listRef.current?.focus();
        }
        lastPage.current = page;
    }, [page]);

    const pageLink = (n: number) => ({ search: n === 1 ? '' : `?trang=${n}` });

    return (
        <Sheet>
            <header className="px-5 pt-3 pb-4 md:px-8 md:pt-6 md:pb-5">
                <nav aria-label="Vị trí">
                    <ol className="flex flex-wrap items-center gap-x-1 text-label text-ink-2">
                        <li>
                            <Link to="/chu-de" className="inline-flex min-h-11 items-center underline-offset-2 hover:text-ink hover:underline">
                                Chủ đề
                            </Link>
                        </li>
                        <li className="flex items-center gap-1">
                            <ChevronRight size={14} aria-hidden="true" />
                            {topic.group}
                        </li>
                        <li className="flex items-center gap-1">
                            <ChevronRight size={14} aria-hidden="true" />
                            {topic.parent}
                        </li>
                    </ol>
                </nav>

                <div className="mt-1 grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(14rem,auto)] md:items-end md:gap-10">
                    <div className="min-w-0">
                        <h1 className="font-display text-headline font-semibold">{topic.name}</h1>
                        <p lang="en" className="text-ink-2">
                            {topic.nameEn}
                        </p>
                        <p className="mt-2 text-label font-semibold tabular-nums">
                            {all.length} từ · {due} đến hạn · {fresh} từ mới
                        </p>
                        <div className="mt-3 max-w-lg xl:max-w-2xl">
                            <LevelBar counts={counts} picked={level === 'all' ? null : level} onPick={(v) => refilter(() => setLevel(v === null ? 'all' : (v as LevelFilter)))} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 md:grid-cols-1">
                        {due + newNext > 0 ? (
                            <Link
                                to={`/hoc?chu-de=${topic.id}`}
                                className="flex min-h-14 flex-col justify-center rounded-md bg-leaf px-4 py-2 text-on-leaf transition-colors duration-200 hover:bg-leaf-strong"
                            >
                                <span className="font-bold">Học tiếp</span>
                                <span className="text-label tabular-nums">
                                    {due} ôn · {newNext} mới
                                </span>
                            </Link>
                        ) : (
                            <p className="flex min-h-14 items-center rounded-md border border-line px-4 text-label text-ink-2">Đã học hết chủ đề này</p>
                        )}
                        {due > 0 ? (
                            <Link
                                to={`/hoc?chu-de=${topic.id}&on=1`}
                                className="flex min-h-14 items-center justify-center rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken"
                            >
                                Ôn {due} thẻ
                            </Link>
                        ) : (
                            <p className="flex min-h-14 items-center justify-center rounded-md border border-line px-4 text-center text-label text-ink-2">
                                Không có thẻ đến hạn
                            </p>
                        )}
                    </div>
                </div>
            </header>

            <div className="flex flex-col gap-3 border-y border-line px-5 py-3 md:flex-row md:flex-wrap md:items-end md:gap-x-8 md:px-8 md:py-4">
                <div className="flex flex-wrap items-end gap-2">
                    <Choice
                        legend="Che cột để tự kiểm"
                        name={`${uid}-che`}
                        value={cover}
                        onChange={(v) => {
                            setCover(v);
                            setShown({});
                        }}
                        options={[
                            { value: 'none', label: 'Không che' },
                            { value: 'de', label: 'Tiếng Đức' },
                            { value: 'vi', label: 'Tiếng Việt' },
                        ]}
                    />
                    {cover !== 'none' && (
                        <button
                            type="button"
                            onClick={() => setShown(hiddenCount > 0 ? Object.fromEntries(rows.map((w) => [w.id, true])) : {})}
                            className="min-h-11 rounded-md px-3 text-label font-semibold text-leaf underline-offset-2 hover:underline"
                        >
                            {hiddenCount > 0 ? 'Hiện hết' : 'Che lại'}
                        </button>
                    )}
                </div>

                <div className="flex min-w-0 flex-1 gap-2 lg:min-w-80">
                    <label className="sr-only" htmlFor={`${uid}-loai`}>
                        Loại từ
                    </label>
                    <select
                        id={`${uid}-loai`}
                        value={pos}
                        onChange={(e) => refilter(() => setPos(e.target.value as PosFilter))}
                        className="min-h-11 shrink-0 rounded-md border border-line-strong bg-paper px-3 text-label font-semibold"
                    >
                        {POS_FILTERS.map((f) => (
                            <option key={f.id} value={f.id}>
                                {f.label}
                            </option>
                        ))}
                    </select>
                    <div className="relative min-w-0 flex-1">
                        <Search size={18} aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-2" />
                        <input
                            ref={searchRef}
                            type="search"
                            value={query}
                            onChange={(e) => refilter(() => setQuery(e.target.value))}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape' && query) {
                                    e.preventDefault();
                                    refilter(() => setQuery(''));
                                }
                            }}
                            aria-label="Tìm trong chủ đề"
                            aria-keyshortcuts="/"
                            placeholder="Tìm từ hoặc nghĩa"
                            className="min-h-11 w-full rounded-md border border-line-strong bg-paper pr-10 pl-9 placeholder:text-ink-2"
                        />
                        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-ink-2">
                            <Kbd>/</Kbd>
                        </span>
                    </div>
                </div>
            </div>

            <section aria-labelledby={`${uid}-ds`}>
                <div className="flex min-h-11 items-center gap-3 px-5 pt-2 md:px-8">
                    <h2 id={`${uid}-ds`} ref={listRef} tabIndex={-1} className="text-label font-semibold text-ink-2 tabular-nums">
                        {filtered ? `${list.length}/${all.length} từ khớp bộ lọc` : `${all.length} từ`}
                        {pages > 1 && ` · trang ${page}/${pages}`}
                    </h2>
                    {filtered && rows.length > 0 && (
                        <button type="button" onClick={clearFilters} className="min-h-11 px-2 text-label font-semibold text-leaf underline-offset-2 hover:underline">
                            Bỏ lọc
                        </button>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="px-5 py-8 md:px-8">
                        <p className="font-semibold">Không có từ nào khớp bộ lọc.</p>
                        <button type="button" onClick={clearFilters} className="mt-3 min-h-11 rounded-md border border-line-strong px-4 font-semibold hover:bg-sunken">
                            Bỏ lọc
                        </button>
                    </div>
                ) : (
                    <table className="mt-2 w-full table-fixed border-collapse text-left">
                        <caption className="sr-only">Danh sách từ: cột tiếng Đức và cột tiếng Việt</caption>
                        <colgroup>
                            <col className="w-12 md:w-16" />
                            <col />
                            <col />
                        </colgroup>
                        <thead>
                            <tr className="border-b border-line text-label text-ink-2">
                                <th scope="col" className="py-2 pl-5 font-semibold md:pl-8">
                                    <span className="sr-only">Mức</span>
                                </th>
                                <th scope="col" className="py-2 pr-3 font-semibold">
                                    Tiếng Đức
                                </th>
                                <th scope="col" className="py-2 pr-5 font-semibold md:pr-8">
                                    Tiếng Việt
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rows.map((w) => {
                                const lv = progress[w.id]?.level ?? 0;
                                const open = !!shown[w.id];
                                const reveal = () => setShown((s) => ({ ...s, [w.id]: true }));
                                return (
                                    <tr key={w.id} className="align-top">
                                        <td className="py-3 pl-5 md:pl-8">
                                            <LevelIcon level={lv} size={22} />
                                        </td>
                                        <td className="py-3 pr-3">
                                            {cover === 'de' && !open ? (
                                                <Covered label={`Xem tiếng Đức của “${w.vi}”`} onClick={reveal} />
                                            ) : (
                                                <>
                                                    <Link to={`/tu/${w.id}`} className="font-semibold break-words underline-offset-2 hover:underline">
                                                        <GermanWord word={w} />
                                                    </Link>
                                                    <span className="block text-label text-ink-2">
                                                        <PluralLine word={w} />
                                                    </span>
                                                </>
                                            )}
                                        </td>
                                        <td className="py-3 pr-5 md:pr-8">
                                            {cover === 'vi' && !open ? (
                                                <Covered label={`Xem tiếng Việt của “${w.lemma}”`} onClick={reveal} />
                                            ) : (
                                                <span className="break-words">{w.vi}</span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </section>

            {pages > 1 && (
                <nav aria-label="Phân trang" className="flex items-center justify-between gap-3 border-t border-line px-5 py-3 md:px-8">
                    {page > 1 ? (
                        <Link to={pageLink(page - 1)} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                            <ChevronLeft size={18} aria-hidden="true" />
                            Trang trước
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-label text-ink-2 tabular-nums">
                        Trang {page}/{pages}
                    </span>
                    {page < pages ? (
                        <Link to={pageLink(page + 1)} className="inline-flex min-h-11 items-center gap-1 font-semibold hover:underline">
                            Trang sau
                            <ChevronRight size={18} aria-hidden="true" />
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
        </Sheet>
    );
}

/** Ô bị che: chạm để xem; nhãn đọc ra từ ở cột bên kia để biết đang xem dòng nào. */
function Covered({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="flex min-h-11 w-full max-w-48 items-center rounded-sm border border-dashed border-line-strong bg-sunken px-3 text-label text-ink-2 transition-colors duration-200 hover:border-ink-3"
        >
            Xem
        </button>
    );
}
