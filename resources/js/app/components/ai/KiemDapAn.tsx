import { useEffect, useRef } from 'react';
import { DoanAi, NUT_PHU_AI, TrangThaiHoi, useHoiAi } from './KetQuaAi';
import { t } from '../../i18n';
import { coChu, GIOI_HAN, kiemDapAn, type KetQuaAi, type KetQuaDapAn } from '../../lib/ai';
import { useTaiKhoan } from '../../state/taiKhoan';

type Than = { exercise_id: number } | { card_id: number };

/**
 * Kiểm đáp án khác mẫu: câu trả lời không khớp đáp án mẫu thì hỏi AI xem có đúng không. Kết quả không đổi điểm của câu.
 * Chỉ hiện khi AI dùng được và câu trả lời có chữ. tuDong: hỏi ngay (tuỳ chọn "AI tự giải thích khi trả lời sai").
 */
export function KiemDapAn({ than, traLoi, tuDong = false }: { than: Than; traLoi: string; tuDong?: boolean }) {
    const { toi } = useTaiKhoan();
    const { kq, dangHoi, loi, hoi, thuLai } = useHoiAi<KetQuaAi<KetQuaDapAn>>();
    const chu = traLoi.trim();
    const dung = toi?.ai.state === 'ready' && chu !== '' && chu.length <= GIOI_HAN.kiemDapAn && coChu(chu);
    const daGoi = useRef(false);
    const hoiNgay = () => void hoi(() => kiemDapAn(than, chu));

    useEffect(() => {
        if (!dung || !tuDong || daGoi.current) return;
        daGoi.current = true;
        hoiNgay();
        // chỉ một lần cho mỗi câu sai
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [dung, tuDong]);

    if (!dung) return null;
    const d = kq?.data;
    return (
        <div className="mt-3">
            {!d && !dangHoi && (
                <button type="button" onClick={hoiNgay} className={NUT_PHU_AI}>
                    {t('ai.kiemDapAn.nut')}
                </button>
            )}
            <TrangThaiHoi dangHoi={dangHoi} loi={loi} onThuLai={() => void thuLai()} o="text" />
            {d && (
                <div aria-live="polite" className="mt-2 space-y-1">
                    <p className="font-semibold">{d.ok ? t('ai.kiemDapAn.dung') : t('ai.kiemDapAn.sai')}</p>
                    <DoanAi chu={d.why} />
                    {d.better && <p>{t('ai.kiemDapAn.tot', { cau: d.better })}</p>}
                    <p className="text-label text-ink-2">{t('ai.kiemDapAn.khongDoiDiem')}</p>
                </div>
            )}
        </div>
    );
}
