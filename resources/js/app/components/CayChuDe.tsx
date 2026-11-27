import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router';
import { demThe, type CayChuDe } from '../lib/noiDung';
import { so, t, type Khoa } from '../i18n';

const CACH_XEM: { to: string; khoa: Khoa }[] = [
    { to: '/chu-de', khoa: 'noiDung.chuDe' },
    { to: '/cap-do', khoa: 'noiDung.capDo' },
    { to: '/giao-tiep', khoa: 'noiDung.giaoTiep' },
];

/** Ba cách xem nội dung (Chủ đề, Cấp độ, Giao tiếp) là ba trang; dải này chuyển giữa chúng. */
export function CachXem({ dang }: { dang: string }) {
    return (
        <nav aria-label={t('noiDung.cachXem')} className="flex overflow-x-auto border-b border-line px-2 md:px-6">
            {CACH_XEM.map((c) => {
                const on = c.to === dang;
                return (
                    <Link
                        key={c.to}
                        to={c.to}
                        aria-current={on ? 'page' : undefined}
                        className={`-mb-px flex min-h-12 shrink-0 items-center border-b-2 px-3 text-label font-semibold whitespace-nowrap transition-colors duration-200 ${
                            on ? 'border-tim text-ink' : 'border-transparent text-ink-2 hover:text-ink'
                        }`}
                    >
                        {t(c.khoa)}
                    </Link>
                );
            })}
        </nav>
    );
}

/**
 * Cây chủ đề: nhóm (h2) › chủ đề vừa (h3) › chủ đề nhỏ (dòng có số thẻ). Nhiều nhóm thì có dải "Đến nhóm" ở đầu.
 * duongChuDe dựng đường dẫn tới trang chủ đề (trang Cấp độ gắn thêm bộ lọc).
 */
export function CayChuDeView({ cay, duongChuDe }: { cay: CayChuDe; duongChuDe: (slug: string) => string }) {
    const bo = cay.collection.code;
    return (
        <>
            {cay.groups.length > 1 && (
                <nav aria-label={t('noiDung.denNhom')} className="mt-5">
                    <ul className="flex flex-wrap gap-1.5">
                        {cay.groups.map((g) => (
                            <li key={g.slug}>
                                <a
                                    href={`#nhom-${g.slug}`}
                                    className="inline-flex min-h-11 items-center rounded-md border border-line px-3 text-label font-semibold text-ink-2 transition-colors duration-200 hover:border-line-strong hover:text-ink"
                                >
                                    {g.name}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
            {cay.groups.map((g) => (
                <section key={g.slug} aria-labelledby={`nhom-${g.slug}`} className="mt-8">
                    <h2 id={`nhom-${g.slug}`} tabIndex={-1} className="scroll-mt-20 text-title font-bold">
                        {g.name}
                    </h2>
                    <p className="text-label text-ink-2 tabular-nums">{t('noiDung.tong', { chuDe: so(g.topic_count), the: demThe(bo, g.card_count) })}</p>
                    {g.parents.map((p) => (
                        <div key={p.slug} className="mt-4">
                            <h3 className="text-label font-semibold text-ink-2">{p.name}</h3>
                            {/* Máy tính lớn: hai cột để danh sách dài (gần 200 chủ đề) ngắn đi một nửa */}
                            <ul className="mt-1 border-t border-line lg:grid lg:grid-cols-2 lg:gap-x-10">
                                {p.topics.map((cd) => (
                                    <li key={cd.slug} className="border-b border-line">
                                        <Link to={duongChuDe(cd.slug)} className="group flex min-h-12 items-center gap-4 py-2.5">
                                            <span className="min-w-0 flex-1 font-semibold break-words group-hover:underline">{cd.name}</span>
                                            <span className="shrink-0 text-label text-ink-2 tabular-nums">{demThe(bo, cd.card_count)}</span>
                                            <ChevronRight size={20} aria-hidden="true" className="shrink-0 text-ink-3" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </section>
            ))}
        </>
    );
}
