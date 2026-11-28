import { useId, useRef, type KeyboardEvent } from 'react';
import { useSearchParams } from 'react-router';
import { CongAi } from '../../components/ai/CongAi';
import { Sheet } from '../../components/Shell';
import { DangTai } from '../../components/TaiNoiDung';
import { t, type Khoa } from '../../i18n';
import { coTabAi } from '../../lib/ai';
import { useTitle } from '../../lib/useTitle';
import { useTaiKhoan } from '../../state/taiKhoan';
import { KhongThay } from '../KhongThay';
import { BaiHocAi } from './BaiHocAi';
import { Dich } from './Dich';
import { SuaNguPhap } from './SuaNguPhap';
import { TroChuyen } from './TroChuyen';

const TAB = ['dich', 'sua', 'tro-chuyen', 'bai-hoc'] as const;
type Tab = (typeof TAB)[number];
const NHAN: Record<Tab, Khoa> = { dich: 'ai.trang.dich', sua: 'ai.trang.sua', 'tro-chuyen': 'ai.trang.troChuyen', 'bai-hoc': 'ai.trang.baiHoc' };

/** Trang Hỏi AI (tải riêng): Dịch, Sửa ngữ pháp, Trò chuyện, Bài học AI; tab ghi trong ?tab= để quay lại đúng chỗ. */
export default function HoiAi() {
    const { toi, trangThai } = useTaiKhoan();
    useTitle(t('ai.trang.tieuDe'));
    const [params, setParams] = useSearchParams();
    const uid = useId();
    const tabsRef = useRef<HTMLDivElement>(null);
    const chon = params.get('tab');
    const tab: Tab = TAB.includes(chon as Tab) ? (chon as Tab) : 'dich';

    if (trangThai === 'dang-tai') {
        return (
            <Sheet className="px-5 py-6 md:px-8">
                <DangTai />
            </Sheet>
        );
    }
    if (!coTabAi(toi?.ai)) return <KhongThay />;

    const doiTab = (moi: Tab) => setParams(moi === 'dich' ? {} : { tab: moi }, { replace: true });
    const onKey = (e: KeyboardEvent<HTMLDivElement>) => {
        const i = TAB.indexOf(tab);
        const sau = e.key === 'ArrowRight' ? TAB[(i + 1) % TAB.length] : e.key === 'ArrowLeft' ? TAB[(i + TAB.length - 1) % TAB.length] : null;
        if (!sau) return;
        e.preventDefault();
        doiTab(sau);
        tabsRef.current?.querySelector<HTMLElement>(`[data-tab="${sau}"]`)?.focus();
    };

    return (
        <Sheet>
            <div className="px-5 pt-6 md:px-8">
                <h1 className="text-headline font-bold">{t('ai.trang.tieuDe')}</h1>
                <p className="mt-1 text-ink-2">{t('ai.trang.moTa')}</p>
            </div>
            <div className="mt-4">
                <CongAi>
                    <div ref={tabsRef} role="tablist" aria-label={t('ai.trang.cacMuc')} onKeyDown={onKey} className="flex overflow-x-auto border-b border-line px-2 md:px-6">
                        {TAB.map((tb) => {
                            const on = tb === tab;
                            return (
                                <button
                                    key={tb}
                                    type="button"
                                    role="tab"
                                    data-tab={tb}
                                    id={`${uid}-tab-${tb}`}
                                    aria-selected={on}
                                    aria-controls={on ? `${uid}-panel` : undefined}
                                    tabIndex={on ? 0 : -1}
                                    onClick={() => doiTab(tb)}
                                    className={`-mb-px min-h-12 shrink-0 border-b-2 px-3 text-label font-semibold whitespace-nowrap transition-colors duration-200 ${
                                        on ? 'border-tim text-ink' : 'border-transparent text-ink-2 hover:text-ink'
                                    }`}
                                >
                                    {t(NHAN[tb])}
                                </button>
                            );
                        })}
                    </div>
                    <div role="tabpanel" id={`${uid}-panel`} aria-labelledby={`${uid}-tab-${tab}`} className="px-5 py-5 md:px-8 md:py-6">
                        {tab === 'dich' && <Dich />}
                        {tab === 'sua' && <SuaNguPhap />}
                        {tab === 'tro-chuyen' && <TroChuyen />}
                        {tab === 'bai-hoc' && <BaiHocAi />}
                    </div>
                </CongAi>
            </div>
        </Sheet>
    );
}
