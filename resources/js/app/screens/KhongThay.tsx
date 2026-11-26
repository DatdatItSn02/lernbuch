import { Link } from 'react-router';
import { Sheet } from '../components/Shell';
import { useTitle } from '../lib/useTitle';

type Props = { title?: string; message?: string; back?: { to: string; label: string } };

/** Trang báo không tìm thấy: đường dẫn sai, từ hay chủ đề không có. */
export function KhongThay({
    title = 'Không tìm thấy trang này',
    message = 'Đường dẫn có thể đã đổi hoặc gõ nhầm.',
    back = { to: '/hom-nay', label: 'Về Hôm nay' },
}: Props) {
    useTitle(title);
    return (
        <Sheet className="mx-auto max-w-[720px] px-5 py-10 md:px-8">
            <h1 className="text-headline font-bold">{title}</h1>
            <p className="mt-2 text-ink-2">{message}</p>
            <Link to={back.to} className="mt-6 inline-flex min-h-12 items-center rounded-md border border-line-strong px-5 font-semibold hover:bg-sunken">
                {back.label}
            </Link>
        </Sheet>
    );
}
