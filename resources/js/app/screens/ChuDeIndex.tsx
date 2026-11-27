import { CachXem, CayChuDeView } from '../components/CayChuDe';
import { Sheet } from '../components/Shell';
import { DangTai, LoiTai } from '../components/TaiNoiDung';
import { demThe, type CayChuDe } from '../lib/noiDung';
import { useApi } from '../lib/useApi';
import { useTitle } from '../lib/useTitle';
import { so, t } from '../i18n';

type Kieu = 'chu-de' | 'giao-tiep';

const KIEU = {
    'chu-de': { bo: 'vocab', duong: '/chu-de', tieuDe: 'noiDung.chuDe', moTa: 'noiDung.chuDeMoTa' },
    'giao-tiep': { bo: 'comm', duong: '/giao-tiep', tieuDe: 'noiDung.giaoTiep', moTa: 'noiDung.giaoTiepMoTa' },
} as const;

/** Chủ đề (Từ vựng) và Giao tiếp: cây chủ đề 3 tầng của một loại thẻ, lấy từ API. */
export function ChuDeIndex({ kieu = 'chu-de' }: { kieu?: Kieu }) {
    const k = KIEU[kieu];
    useTitle(t(k.tieuDe));
    const { duLieu, loi, taiLai } = useApi<{ data: CayChuDe }>(`/chu-de?bo=${k.bo}`);
    const cay = duLieu?.data;

    return (
        <Sheet>
            <CachXem dang={k.duong} />
            <div className="px-5 py-6 md:px-8">
                <h1 className="text-headline font-bold">{t(k.tieuDe)}</h1>
                <p className="mt-1 text-ink-2">{t(k.moTa)}</p>
                {cay && (
                    <p className="mt-1 text-label font-semibold tabular-nums">{t('noiDung.tong', { chuDe: so(cay.topic_count), the: demThe(k.bo, cay.card_count) })}</p>
                )}
                {cay ? (
                    cay.groups.length === 0 ? (
                        <p className="mt-6 text-ink-2">{t('noiDung.khongCoChuDe')}</p>
                    ) : (
                        <CayChuDeView cay={cay} duongChuDe={(slug) => `/chu-de/${slug}`} />
                    )
                ) : loi ? (
                    <LoiTai loi={loi} taiLai={taiLai} />
                ) : (
                    <DangTai />
                )}
            </div>
        </Sheet>
    );
}
