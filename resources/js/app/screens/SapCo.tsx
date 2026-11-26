import { Link } from 'react-router';
import { Sheet } from '../components/Shell';
import { useTitle } from '../lib/useTitle';
import { t } from '../i18n';

/** Mục có trong thanh điều hướng nhưng làm ở G1 (Ngữ pháp, Tra từ). */
export function SapCo({ title, what }: { title: string; what: string }) {
    useTitle(title);
    return (
        <Sheet className="mx-auto max-w-[720px] px-5 py-10 md:px-8">
            <h1 className="text-headline font-bold">{title}</h1>
            <p className="mt-2 text-ink-2">{what}</p>
            <p className="mt-1 text-ink-2">{t('sapCo.dangLam')}</p>
            <Link to="/hom-nay" className="mt-6 inline-flex min-h-12 items-center rounded-md border border-line-strong px-5 font-semibold hover:bg-sunken">
                {t('chung.veHomNay')}
            </Link>
        </Sheet>
    );
}
