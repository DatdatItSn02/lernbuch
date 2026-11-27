import { RotateCw } from 'lucide-react';
import type { ReactNode } from 'react';
import type { LoiApi } from '../lib/api';
import { t } from '../i18n';

/** Chỗ giữ trong lúc tải: một dòng chữ (trình đọc màn hình đọc ra), không vẽ khung giả. Lề ngang do chỗ đặt quyết định. */
export function DangTai({ className = '' }: { className?: string }) {
    return (
        <p role="status" className={`py-8 text-ink-2 ${className}`}>
            {t('chung.dangTai')}
        </p>
    );
}

/** Báo không tải được, kèm nút thử lại; mất mạng thì nói rõ. */
export function LoiTai({ loi, taiLai, children, className = '' }: { loi: LoiApi; taiLai: () => void; children?: ReactNode; className?: string }) {
    return (
        <div role="alert" className={`py-8 ${className}`}>
            <p className="font-semibold">{loi.ma === 'mat_mang' ? t('noiDung.matMang') : t('noiDung.loiTai')}</p>
            {children}
            <button
                type="button"
                onClick={taiLai}
                className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-md border border-line-strong px-4 font-semibold transition-colors duration-200 hover:bg-sunken"
            >
                <RotateCw size={18} aria-hidden="true" />
                {t('noiDung.thuLai')}
            </button>
        </div>
    );
}
