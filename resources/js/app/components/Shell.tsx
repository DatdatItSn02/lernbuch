import { BookOpen, CalendarDays, Library, Search, Settings } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { NavLink, useLocation } from 'react-router';

const NAV = [
    { to: '/hom-nay', label: 'Hôm nay', icon: CalendarDays },
    { to: '/chu-de', label: 'Chủ đề', icon: Library },
    { to: '/ngu-phap', label: 'Ngữ pháp', icon: BookOpen },
    { to: '/tra-tu', label: 'Tra từ', icon: Search },
    { to: '/cai-dat', label: 'Cài đặt', icon: Settings },
];

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
                Bỏ qua thanh điều hướng
            </a>
            <header className="sticky top-0 z-20 bg-box text-on-box">
                <div className="mx-auto flex h-12 max-w-[var(--sheet-max)] items-center gap-8 px-5 md:h-14 md:px-6">
                    <NavLink to="/hom-nay" className="text-title font-extrabold tracking-tight focus-visible:outline-on-box!">
                        Lernbuch
                    </NavLink>
                    <nav aria-label="Điều hướng chính" className="hidden h-full items-stretch gap-1 md:flex">
                        {NAV.map(({ to, label, icon: Icon }) => (
                            <NavLink
                                key={to}
                                to={to}
                                className={({ isActive }) =>
                                    `flex items-center gap-2 border-b-[3px] px-3 pt-[3px] text-label font-semibold transition-colors duration-200 focus-visible:outline-on-box! ${
                                        isActive ? 'border-on-box text-on-box' : 'border-transparent text-on-box/75 hover:text-on-box'
                                    }`
                                }
                            >
                                <Icon size={18} aria-hidden="true" />
                                {label}
                            </NavLink>
                        ))}
                    </nav>
                    <span className="ml-auto text-label text-on-box/80">Dữ liệu mẫu</span>
                </div>
            </header>

            <main ref={mainRef} id="noi-dung" tabIndex={-1} className="mx-auto max-w-[var(--sheet-max)] pb-24 md:px-6 md:pt-6 md:pb-12">
                <p className="border-b border-line px-5 py-1.5 text-label text-ink-2 md:hidden">Tiến độ và ghi chú mất khi tải lại trang.</p>
                {children}
            </main>

            <nav
                aria-label="Điều hướng chính"
                className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)] md:hidden"
            >
                <ul className="grid grid-cols-5">
                    {NAV.map(({ to, label, icon: Icon }) => (
                        <li key={to}>
                            <NavLink
                                to={to}
                                className={({ isActive }) =>
                                    `relative flex min-h-16 flex-col items-center justify-center gap-1 text-[calc(0.75rem*var(--type-scale))] font-semibold leading-tight ${
                                        isActive ? 'text-tim before:absolute before:inset-x-5 before:top-0 before:h-[3px] before:bg-tim' : 'text-ink-2'
                                    }`
                                }
                            >
                                <Icon size={22} aria-hidden="true" />
                                {label}
                            </NavLink>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}

/** Trang vở: khung nội dung chính; điện thoại tràn màn hình, máy tính có viền mảnh. */
export function Sheet({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`bg-sheet md:rounded-md md:border md:border-line ${className}`}>{children}</div>;
}
