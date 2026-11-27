import { BookOpen, CalendarDays, Library, Search, Settings, Star } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { Link, NavLink, useLocation } from 'react-router';
import { ChoGui } from './ChoGui';
import { t, type Khoa } from '../i18n';

type MucDieuHuong = { to: string; khoa: Khoa; icon: typeof CalendarDays; cung?: string[]; dienThoai?: boolean };

// Chủ đề gồm cả Cấp độ, Giao tiếp và thẻ từ. Điện thoại: Cài đặt ở nút bánh răng trên thanh trên, thanh dưới còn 5 mục.
const NAV: MucDieuHuong[] = [
    { to: '/hom-nay', khoa: 'khung.homNay', icon: CalendarDays },
    { to: '/chu-de', khoa: 'khung.chuDe', icon: Library, cung: ['/cap-do', '/giao-tiep', '/tu/'] },
    { to: '/ngu-phap', khoa: 'khung.nguPhap', icon: BookOpen },
    { to: '/tra-tu', khoa: 'khung.traTu', icon: Search },
    { to: '/so-tay', khoa: 'khung.soTay', icon: Star },
    { to: '/cai-dat', khoa: 'khung.caiDat', icon: Settings, dienThoai: false },
];

const dangMo = (m: MucDieuHuong, duong: string) => duong === m.to || duong.startsWith(`${m.to}/`) || (m.cung ?? []).some((p) => duong.startsWith(p));

/** Khung app học: thanh mực tím trên cùng (điều hướng ở máy tính), thanh điều hướng dưới ở điện thoại; nội dung trên nền giấy vở. */
export function Shell({ children }: { children: ReactNode }) {
    const { pathname } = useLocation();
    const mainRef = useRef<HTMLElement>(null);
    const firstPath = useRef(pathname);

    // Chuyển màn hình: lên đầu trang và đặt tiêu điểm vào nội dung mới (lần mở trang đầu thì để nguyên).
    useEffect(() => {
        if (pathname === firstPath.current) return;
        firstPath.current = '';
        window.scrollTo(0, 0);
        mainRef.current?.focus({ preventScroll: true });
    }, [pathname]);

    return (
        <div className="min-h-dvh bg-paper text-ink">
            <a href="#noi-dung" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-sheet focus:px-4 focus:py-2">
                {t('khung.boQua')}
            </a>
            <header className="sticky top-0 z-20 bg-box text-on-box">
                <div className="mx-auto flex h-12 max-w-[var(--sheet-max)] items-center gap-8 px-5 md:h-14 md:px-6">
                    <NavLink to="/hom-nay" className="text-title font-extrabold tracking-tight focus-visible:outline-on-box!">
                        {t('chung.tenApp')}
                    </NavLink>
                    <nav aria-label={t('khung.dieuHuong')} className="hidden h-full items-stretch gap-1 md:flex">
                        {NAV.map((m) => {
                            const on = dangMo(m, pathname);
                            const Icon = m.icon;
                            return (
                                <Link
                                    key={m.to}
                                    to={m.to}
                                    aria-current={on ? 'page' : undefined}
                                    className={`flex items-center gap-2 border-b-[3px] px-2.5 pt-[3px] text-label font-semibold whitespace-nowrap transition-colors duration-200 focus-visible:outline-on-box! lg:px-3 ${
                                        on ? 'border-on-box text-on-box' : 'border-transparent text-on-box/75 hover:text-on-box'
                                    }`}
                                >
                                    <Icon size={18} aria-hidden="true" className="hidden lg:block" />
                                    {t(m.khoa)}
                                </Link>
                            );
                        })}
                    </nav>
                    <span className="ml-auto flex items-center gap-1">
                        <ChoGui mau="text-on-box/85" />
                        <NavLink
                            to="/cai-dat"
                            aria-label={t('khung.caiDat')}
                            className={({ isActive }) =>
                                `-mr-2 flex size-11 items-center justify-center rounded-md focus-visible:outline-on-box! md:hidden ${isActive ? 'text-on-box' : 'text-on-box/80 hover:text-on-box'}`
                            }
                        >
                            <Settings size={22} aria-hidden="true" />
                        </NavLink>
                    </span>
                </div>
            </header>

            <main ref={mainRef} id="noi-dung" tabIndex={-1} className="mx-auto max-w-[var(--sheet-max)] pb-24 md:px-6 md:pt-6 md:pb-12">
                {children}
            </main>

            <nav
                aria-label={t('khung.dieuHuong')}
                className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)] md:hidden"
            >
                <ul className="grid grid-cols-5">
                    {NAV.filter((m) => m.dienThoai !== false).map((m) => {
                        const on = dangMo(m, pathname);
                        const Icon = m.icon;
                        return (
                            <li key={m.to}>
                                <Link
                                    to={m.to}
                                    aria-current={on ? 'page' : undefined}
                                    className={`relative flex min-h-16 flex-col items-center justify-center gap-1 text-[calc(0.75rem*var(--type-scale))] font-semibold leading-tight ${
                                        on ? 'text-tim before:absolute before:inset-x-5 before:top-0 before:h-[3px] before:bg-tim' : 'text-ink-2'
                                    }`}
                                >
                                    <Icon size={22} aria-hidden="true" />
                                    {t(m.khoa)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}

/** Trang vở: khung nội dung chính; điện thoại tràn màn hình, máy tính có viền mảnh. */
export function Sheet({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`bg-sheet md:rounded-md md:border md:border-line ${className}`}>{children}</div>;
}
