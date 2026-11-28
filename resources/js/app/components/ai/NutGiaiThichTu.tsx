import { GiaiThich } from '../../screens/ai/TabHoiAi';
import { useTaiKhoan } from '../../state/taiKhoan';

/** Bật tuỳ chọn tự giải thích, vừa trả lời sai, và còn lượt AI theo hồ sơ: tự gọi AI. */
export function nenTuGiaiThich(toi: ReturnType<typeof useTaiKhoan>['toi'], sai: boolean): boolean {
    const ai = toi?.ai;
    if (!sai || !toi?.settings.ai_auto_explain || ai?.state !== 'ready') return false;
    return ai.limit < 0 || ai.used < ai.limit;
}

/** Giải thích từ ngay trong phiên học (mặt sau thẻ, sau khi trả lời). Chỉ hiện khi AI dùng được cho tài khoản. */
export function NutGiaiThichTu({ cardId, sai = false }: { cardId: number; sai?: boolean }) {
    const { toi } = useTaiKhoan();
    if (toi?.ai.state !== 'ready') return null;
    return (
        <div className="mt-5 border-t border-line pt-4">
            <GiaiThich cardId={cardId} tuDong={nenTuGiaiThich(toi, sai)} />
        </div>
    );
}
