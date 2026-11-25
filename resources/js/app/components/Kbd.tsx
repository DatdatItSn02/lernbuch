/** Gợi ý phím tắt, chỉ hiện khi màn hình đủ rộng (thường là máy tính có bàn phím). */
export function Kbd({ children }: { children: string }) {
    return <kbd className="hidden rounded-sm border border-current/40 px-1.5 font-sans text-label tabular-nums sm:inline">{children}</kbd>;
}
