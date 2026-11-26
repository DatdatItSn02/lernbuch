import { levelName, stageName } from '../lib/srs';
import { t } from '../i18n';

// Dấu ấn của Lernbuch: 6 mức thuộc vẽ thành 6 giai đoạn của cây (hạt, mầm, lá, nụ, hoa, quả).
// Vẽ bằng nét mực như hình vẽ tay trong vở: chỉ nét, không tô màu; nét theo màu chữ (currentColor).
// Đọc được ở cỡ 16–24 px: mỗi giai đoạn khác nhau ở hình ngọn (hạt nằm, hai lá nhỏ, hai lá lớn, nụ, hoa 5 cánh, quả tròn).

export function LevelIcon({ level, size = 24, title, className = '' }: { level: number; size?: number; title?: string; className?: string }) {
    const lv = Math.min(5, Math.max(0, Math.round(level)));
    // title="" là hình trang trí (chữ bên cạnh đã nói mức): ẩn khỏi trình đọc màn hình.
    const label = title ?? t('thanhMuc.hinh', { giaiDoan: stageName(lv), muc: levelName(lv) });
    const a11y = label ? { role: 'img', 'aria-label': label } : { 'aria-hidden': true as const, focusable: 'false' as const };
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            {...a11y}
            className={`shrink-0 ${className}`}
            fill="none"
            stroke="currentColor"
            // Ở cỡ nhỏ (tai thẻ, hộp thẻ) nét dày hơn để hình cây vẫn đọc ra
            strokeWidth={size <= 16 ? 2.2 : size <= 20 ? 1.8 : 1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            {/* mặt đất: một nét hơi lượn như vẽ tay */}
            <path d="M4.5 20.6c2.4-.5 4.8-.6 7.5-.5s5 .3 7.5-.1" />
            {lv === 0 && (
                <>
                    <ellipse cx="12" cy="17.4" rx="3.2" ry="2.1" transform="rotate(-20 12 17.4)" />
                    <path d="M10.4 17.9c.9-.9 2.1-1.3 3.3-1.2" />
                </>
            )}
            {lv === 1 && (
                <>
                    <path d="M12 20.4v-4.6" />
                    <path d="M12 16.2c-2.4.1-3.9-1.2-4.1-3.3 2.2-.1 3.8 1.1 4.1 3.3z" />
                    <path d="M12 15.4c.2-2.1 1.6-3.4 3.8-3.4-.1 2-1.6 3.4-3.8 3.4z" />
                </>
            )}
            {lv === 2 && (
                <>
                    <path d="M12 20.4V9.5" />
                    <path d="M12 16.8c-3.2.1-5.4-1.7-5.6-4.5 3 0 5.3 1.7 5.6 4.5zM8.6 13.6l2.2 1.8" />
                    <path d="M12 13.4c.2-2.8 2.4-4.6 5.5-4.6-.1 2.8-2.3 4.6-5.5 4.6zM15.2 10.5l-1.9 1.8" />
                </>
            )}
            {lv === 3 && (
                <>
                    <path d="M12 20.4V9.4" />
                    <path d="M12 17.2c-2.8.1-4.6-1.4-4.8-3.8 2.5 0 4.5 1.5 4.8 3.8z" />
                    <path d="M12 14.4c.2-2.4 2-3.9 4.6-3.9-.1 2.4-1.9 3.9-4.6 3.9z" />
                    <path d="M12 9.4c-1.7-.9-2.5-2.3-2.4-3.7.1-1.3 1.1-2.4 2.4-2.9 1.3.5 2.3 1.6 2.4 2.9.1 1.4-.7 2.8-2.4 3.7zM12 9.2V5.4" />
                </>
            )}
            {lv === 4 && (
                <>
                    <path d="M12 20.4V10.3" />
                    <path d="M12 17.4c-2.6.1-4.3-1.3-4.5-3.5 2.3 0 4.2 1.4 4.5 3.5z" />
                    {[0, 72, 144, 216, 288].map((a) => {
                        const r = (a * Math.PI) / 180;
                        const cx = 12 + 2.7 * Math.sin(r);
                        const cy = 6.4 - 2.7 * Math.cos(r);
                        return <ellipse key={a} cx={cx} cy={cy} rx="1.7" ry="2" transform={`rotate(${a} ${cx} ${cy})`} />;
                    })}
                    <circle cx="12" cy="6.4" r="0.9" />
                </>
            )}
            {lv === 5 && (
                <>
                    <path d="M12 20.4v-7.6" />
                    <path d="M12 17.6c-2.5.1-4.2-1.2-4.4-3.4 2.2 0 4.1 1.3 4.4 3.4z" />
                    <circle cx="12" cy="8.6" r="4" />
                    <path d="M12 4.6c.5-1.3 1.6-2 3-2.1-.1 1.3-1.2 2.1-3 2.1zM10.1 7.2c.5-.6 1.1-.9 1.8-1" />
                </>
            )}
        </svg>
    );
}
