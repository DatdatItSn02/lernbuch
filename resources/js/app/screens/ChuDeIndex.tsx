import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router';
import { LevelBar } from '../components/LevelBar';
import { Sheet } from '../components/Shell';
import { currentTopicId, topics, words } from '../data/demo';
import { isDue, isNew, levelCounts } from '../lib/session';
import { useTitle } from '../lib/useTitle';
import { useStore } from '../state/store';
import { so, t } from '../i18n';

/** Danh sách chủ đề theo ba tầng: nhóm (h2) › chủ đề vừa (h3) › chủ đề nhỏ (dòng). Bản mẫu có hai chủ đề nhỏ. */
export function ChuDeIndex() {
    const { progress } = useStore();
    useTitle(t('khung.chuDe'));
    const groups = [...new Set(topics.map((cd) => cd.group))];
    return (
        <Sheet className="px-5 py-6 md:px-8">
            <h1 className="text-headline font-bold">{t('khung.chuDe')}</h1>
            <p className="mt-1 text-ink-2">{t('chuDe.banMau', { n: so(2466) })}</p>
            {groups.map((g) => {
                const inGroup = topics.filter((cd) => cd.group === g);
                const parents = [...new Set(inGroup.map((cd) => cd.parent))];
                return (
                    <section key={g} aria-label={g} className="mt-8">
                        <h2 className="text-title font-bold">{g}</h2>
                        {parents.map((parent) => (
                            <div key={parent} className="mt-4">
                                <h3 className="text-label font-semibold text-ink-2">{parent}</h3>
                                <ul className="mt-1 divide-y divide-line border-y border-line">
                                    {inGroup
                                        .filter((cd) => cd.parent === parent)
                                        .map((cd) => {
                                            const ids = words.filter((w) => w.topicId === cd.id).map((w) => w.id);
                                            const due = ids.filter((id) => isDue(progress[id])).length;
                                            const fresh = ids.filter((id) => isNew(progress[id])).length;
                                            return (
                                                <li key={cd.id}>
                                                    <Link to={`/chu-de/${cd.id}`} className="group flex items-center gap-4 py-3">
                                                        <div className="min-w-0 flex-1">
                                                            <span className="block font-semibold group-hover:underline">
                                                                {cd.name}
                                                                {cd.id === currentTopicId && (
                                                                    <span className="ml-2 rounded-sm bg-tim-tint px-1.5 text-label font-semibold whitespace-nowrap">{t('muc.dangHoc')}</span>
                                                                )}
                                                            </span>
                                                            <span className="block text-label text-ink-2 tabular-nums">
                                                                {t('chuDe.demTu', { tu: ids.length, denHan: due, moi: fresh })}
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
