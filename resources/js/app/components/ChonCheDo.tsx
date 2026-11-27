import { Link } from 'react-router';
import { CAC_CHE_DO, XU } from '../lib/hoc';
import { truyVan } from '../lib/noiDung';
import { t } from '../i18n';

/**
 * Ô chọn chế độ học như Lernbuch: mỗi ô mở một phiên bằng chế độ đó (trong chủ đề này nếu có), ghi số xu mỗi thẻ.
 * Flashcard là chế độ mặc định nên đường dẫn không ghi chế độ.
 */
export function ChonCheDo({ chuDe, nhan }: { chuDe?: string; nhan: string }) {
    return (
        <nav aria-label={nhan}>
            <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                {CAC_CHE_DO.map((m) => (
                    <li key={m}>
                        <Link
                            to={`/hoc${truyVan({ 'chu-de': chuDe, 'che-do': m === 'flashcard' ? null : m })}`}
                            className="flex h-full min-h-20 flex-col gap-0.5 rounded-md border border-line bg-sheet px-3 py-2.5 transition-colors duration-200 hover:border-tim hover:bg-tim-tint"
                        >
                            <span className="font-bold">{t(`cheDo.${m}`)}</span>
                            <span className="text-label text-ink-2">{t(`cheDoMoTa.${m}`)}</span>
                            <span className="mt-auto text-label font-semibold tabular-nums text-tim">{t('cheDo.xu', { n: XU[m] })}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
