import { useEffect } from 'react';

/** Tiêu đề thẻ trình duyệt theo màn hình đang mở (trình đọc màn hình cũng đọc khi chuyển trang). */
export function useTitle(title: string) {
    useEffect(() => {
        document.title = title ? `${title} · Lernbuch` : 'Lernbuch';
    }, [title]);
}
