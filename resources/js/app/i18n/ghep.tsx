import { Fragment, type ReactNode } from 'react';
import { t, type Khoa } from './index';

/**
 * Câu dịch có chỗ cần chèn thành phần React (từ tiếng Đức có lang="de", phím tắt…): "{tu} lên mức {muc}".
 * Mỗi ngôn ngữ tự đặt vị trí của chỗ chèn trong câu, nên không phải cắt câu ra từng mảnh trong code.
 */
export function tGhep(khoa: Khoa, cho: Record<string, ReactNode>): ReactNode {
    return t(khoa)
        .split(/\{(\w+)\}/)
        .map((phan, i) => (i % 2 === 1 ? <Fragment key={i}>{phan in cho ? cho[phan] : `{${phan}}`}</Fragment> : phan));
}
