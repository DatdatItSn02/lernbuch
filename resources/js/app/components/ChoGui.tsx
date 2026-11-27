import { CloudOff } from 'lucide-react';
import { useHangCho } from '../lib/hangCho';
import { t } from '../i18n';

/** Số lượt chấm còn nằm trên máy chưa tới máy chủ (mất mạng thì có biểu tượng mây gạch); không còn lượt nào thì ẩn. */
export function ChoGui({ mau = 'text-ink-2' }: { mau?: string }) {
    const { cho, loi } = useHangCho();
    if (cho === 0) return null;
    return (
        <span className={`inline-flex shrink-0 items-center gap-1 text-label tabular-nums ${mau}`} title={loi === 'mat-mang' ? t('hoc.matMangChoGui') : undefined}>
            {loi === 'mat-mang' && <CloudOff size={16} aria-hidden="true" />}
            {t('hoc.choGui', { n: cho })}
            {loi === 'mat-mang' && <span className="sr-only">{t('hoc.matMangChoGui')}</span>}
        </span>
    );
}
