import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router';
import { LevelBar } from '../components/LevelBar';
import { Sheet } from '../components/Shell';
import { currentTopicId, topics, words } from '../data/demo';
import { isDue, isNew, levelCounts } from '../lib/session';
import { useTitle } from '../lib/useTitle';
import { useStore } from '../state/store';

/** Danh sách chủ đề theo ba tầng: nhóm (h2) › chủ đề vừa (h3) › chủ đề nhỏ (dòng). Bản mẫu có hai chủ đề nhỏ. */
export function ChuDeIndex() {
    const { progress } = useStore();
    useTitle('Chủ đề');
    const groups = [...new Set(topics.map((t) => t.group))];
    return (
        <Sheet className="px-5 py-6 md:px-8">
            <h1 className="font-display text-headline font-semibold">Chủ đề</h1>
            <p className="mt-1 text-ink-2">Bản mẫu có hai chủ đề nhỏ; bản đầy đủ có 2.466 chủ đề nhỏ, xếp theo nhóm và chủ đề vừa.</p>
            {groups.map((g) => {
                const inGroup = topics.filter((t) => t.group === g);
                const parents = [...new Set(inGroup.map((t) => t.parent))];
                return (
                    <section key={g} aria-label={g} className="mt-8">
                        <h2 className="text-title font-bold">{g}</h2>
                        {parents.map((parent) => (
                            <div key={parent} className="mt-4">
                                <h3 className="text-label font-semibold text-ink-2">{parent}</h3>
                                <ul className="mt-1 divide-y divide-line border-y border-line">
                                    {inGroup
                                        .filter((t) => t.parent === parent)
                                        .map((t) => {
                                            const ids = words.filter((w) => w.topicId === t.id).map((w) => w.id);
                                            const due = ids.filter((id) => isDue(progress[id])).length;
                                            const fresh = ids.filter((id) => isNew(progress[id])).length;
                                            return (
                                                <li key={t.id}>
                                                    <Link to={`/chu-de/${t.id}`} className="group flex items-center gap-4 py-3">
                                                        <div className="min-w-0 flex-1">
                                                            <span className="block font-semibold group-hover:underline">
                                                                {t.name}
                                                                {t.id === currentTopicId && (
                                                                    <span className="ml-2 rounded-pill bg-leaf-tint px-2 text-label font-semibold whitespace-nowrap">Đang học</span>
                                                                )}
                                                            </span>
                                                            <span className="block text-label text-ink-2 tabular-nums">
                                                                {ids.length} từ · {due} đến hạn · {fresh} từ mới
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
                            </div>
                        ))}
                    </section>
                );
            })}
        </Sheet>
    );
}
