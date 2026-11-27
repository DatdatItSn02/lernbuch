// Các lượt làm bài: theo bộ, Luyện nhanh, Ôn câu sai (/bai-tap/lam), Điền câu, Chia động từ. Tải câu từ máy chủ,
// làm bằng ChayBai (chấm ngay) hoặc PhieuBai (làm cả bộ rồi chấm), gửi kết quả; Điền câu gửi lượt chấm thẻ qua hàng chờ.
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { DangTai, LoiTai } from '../../components/TaiNoiDung';
import { guiKetQua, type CauBaiTap, type CauChiaDongTu, type Diem, type KetQuaGui, type LoaiLuot, type TheDienCau } from '../../lib/baiTap';
import { docTrangThai, guiDi, khiNhanLo, themLuot } from '../../lib/hangCho';
import { nghiaChinh, tron } from '../../lib/luyen';
import { docCap, truyVan } from '../../lib/noiDung';
import { ulidTangDan } from '../../lib/ulid';
import { useApi } from '../../lib/useApi';
import { useTitle } from '../../lib/useTitle';
import { useStore } from '../../state/store';
import { t } from '../../i18n';
import { CacTieng } from '../../components/KhoiNguPhap';
import { ChayBai, PhieuBai, type CauChay, type DaGui, type KetQuaCau } from './ChayBai';
import { LOP_NUT_PHU, duongVe } from './chung';

function Trong({ lyDo, ve }: { lyDo: string; ve: string }) {
    return (
        <main className="mx-auto flex min-h-dvh w-full max-w-[640px] flex-col items-center justify-center bg-paper px-5 text-center text-ink">
            <h1 className="text-headline font-bold">{lyDo}</h1>
            <Link to={ve} className={`${LOP_NUT_PHU} mt-6`}>
                {t('chung.quayLai')}
            </Link>
        </main>
    );
}

function DangTaiDay({ loi, taiLai }: { loi: Parameters<typeof LoiTai>[0]['loi'] | null; taiLai: () => void }) {
    return <main className="mx-auto min-h-dvh w-full max-w-[640px] bg-paper px-5 text-ink">{loi ? <LoiTai loi={loi} taiLai={taiLai} /> : <DangTai />}</main>;
}

/** Gửi kết quả một lượt làm bài và đổi sang dòng xu ở tổng kết. */
async function gui(kq: Omit<KetQuaGui, 'uid' | 'finished_at_ms'>): Promise<DaGui> {
    try {
        const r = await guiKetQua({ ...kq, uid: ulidTangDan(Date.now()), finished_at_ms: Date.now() });
        return r ? { xu: r.coins, quaNhanh: r.too_fast, chuaGui: false, loi: false } : { xu: null, quaNhanh: false, chuaGui: true, loi: false };
    } catch {
        return { xu: null, quaNhanh: false, chuaGui: false, loi: true };
    }
}

/** Một lượt (chạy lại thì đổi số để dựng lại từ đầu) và lúc bắt đầu lượt (máy chủ dùng để nhận ra làm quá nhanh). */
function useLuot<T>(dau: T[] | undefined) {
    const [luot, setLuot] = useState<{ so: number; ds: T[] | null; batDau: number }>({ so: 0, ds: null, batDau: 0 });
    const moTrang = useRef(0);
    useEffect(() => {
        moTrang.current = Date.now();
    }, []);
    const lamLai = useCallback((ds: T[]) => setLuot((l) => ({ so: l.so + 1, ds, batDau: Date.now() })), []);
    const datLai = useCallback(() => setLuot((l) => ({ so: l.so + 1, ds: null, batDau: Date.now() })), []);
    const batDau = luot.batDau;
    const lucBatDau = useCallback(() => batDau || moTrang.current, [batDau]);
    return { ds: luot.ds ?? dau, so: luot.so, lucBatDau, lamLai, datLai };
}

const tuCau = (c: CauBaiTap): CauChay => ({
    khoa: String(c.id),
    kind: c.kind,
    prompt: c.prompt,
    answers: c.answers,
    options: c.options,
    hint: c.hint,
    explanation: c.explanation,
    label: c.label,
    case_sensitive: c.case_sensitive,
    bank: c.bank,
    tokens: c.tokens,
    lead: c.lead,
    frag: c.frag,
    nhom: c.set.title,
});

/** Làm bộ bài: ?bo=…&n=&thu-tu=1&cham=phieu (một bộ theo thứ tự thì làm được cả phiếu), ?on=1 Ôn câu sai, không có bộ là Luyện nhanh. */
export function LamBai() {
    useTitle(t('khung.baiTap'));
    const [params] = useSearchParams();
    const boChu = params.get('bo') ?? '';
    const bo = boChu.split(',').filter(Boolean);
    const thuTu = params.get('thu-tu') === '1' && bo.length > 0;
    const on = params.get('on') === '1';
    const n = Number(params.get('n') ?? '15') || 15;
    const cap = docCap(params.get('cap')).join(',');
    const ve = duongVe(params.get('ve'), '/bai-tap');
    const phieu = params.get('cham') === 'phieu' && thuTu && bo.length === 1;
    const loai: LoaiLuot = on ? 'wrong_review' : bo.length === 0 ? 'quick' : bo.every((s) => s.startsWith('pl-')) ? 'plural' : 'set';
    const [lan, setLan] = useState(0);
    const { duLieu, loi, taiLai, dangTai } = useApi<{ data: CauBaiTap[] }>(
        `/bai-tap/phien${truyVan({ bo: bo.join(','), cap: bo.length > 0 ? null : cap, on: on ? 1 : null, n, thu_tu: thuTu ? 1 : null, lan: lan || null })}`,
    );
    const goc = duLieu?.data;
    const cacCau = useMemo(() => goc?.map(tuCau), [goc]);
    const { ds, so, lucBatDau, lamLai, datLai } = useLuot(cacCau);

    const onXong = useCallback(
        (kq: KetQuaCau[]) => {
            const cacBo = boChu.split(',').filter(Boolean);
            return gui({
                mode: loai,
                grading: phieu ? 'sheet' : 'instant',
                set: cacBo.length === 1 ? cacBo[0] : null,
                started_at_ms: lucBatDau(),
                items: kq.map((k) => ({ id: Number(k.khoa), answer: k.traLoi })),
            });
        },
        [loai, phieu, lucBatDau, boChu],
    );

    if (!goc || dangTai || !ds) return <DangTaiDay loi={loi} taiLai={taiLai} />;
    if (ds.length === 0) return <Trong lyDo={on ? t('lamBai.khongCoCauSai') : t('lamBai.khongCoCau')} ve={ve} />;

    if (phieu) {
        const dau = goc[0];
        return (
            <PhieuBai
                key={so}
                cau={ds}
                tieuDe={dau.set.title.replace(/^\S+\s·\s/, '')}
                phu={[dau.set.group, dau.set.title.split(' · ')[0]].filter(Boolean).join(' · ') || null}
                loiDan={dau.instr}
                ghiNho={dau.set.note}
                ve={ve}
                onXong={onXong}
                onLamLai={lamLai}
            />
        );
    }
    return (
        <ChayBai
            key={so}
            cau={ds}
            ve={ve}
            onXong={onXong}
            onLamLai={(sai) => lamLai(tron(sai))}
            onLuyenTiep={() => {
                setLan((x) => x + 1);
                datLai();
            }}
        />
    );
}

/** Chia động từ: mỗi động từ một câu (du, er, Präteritum, Perfekt hay một ngôi ở một thì); 10 xu mỗi câu đúng. */
export function ChiaDongTu() {
    useTitle(t('baiTap.chiaDongTu'));
    const { settings } = useStore();
    const [params] = useSearchParams();
    const cap = docCap(params.get('cap')).join(',');
    const tach = params.get('tach') === '1';
    const ve = duongVe(params.get('ve'), '/bai-tap');
    const [lan, setLan] = useState(0);
    const { duLieu, loi, taiLai, dangTai } = useApi<{ data: CauChiaDongTu[] }>(`/dong-tu/luyen${truyVan({ cap, tach: tach ? 1 : null, lan: lan || null })}`);
    const goc = duLieu?.data;
    const cacCau = useMemo(
        () =>
            goc?.map(
                (c): CauChay => ({
                khoa: c.sig,
                kind: 'fill',
                prompt: c.prompt,
                answers: c.answers,
                options: null,
                hint: nghiaChinh(c, settings.langs) || null,
                explanation: c.explanation,
                label: null,
                case_sensitive: false,
                bank: null,
                tokens: null,
                lead: null,
                frag: [],
                nhom: t('baiTap.chiaDongTu'),
                nghe: { chu: c.verb, url: c.audio },
            }),
            ),
        [goc, settings.langs],
    );
    const { ds, so, lucBatDau, lamLai, datLai } = useLuot(cacCau);
    const onXong = useCallback(
        (kq: KetQuaCau[]) => gui({ mode: 'conjugate', grading: 'instant', set: null, started_at_ms: lucBatDau(), items: kq.map((k) => ({ sig: k.khoa, answer: k.traLoi })) }),
        [lucBatDau],
    );

    if (!goc || dangTai || !ds) return <DangTaiDay loi={loi} taiLai={taiLai} />;
    if (ds.length === 0) return <Trong lyDo={t('lamBai.khongCoDongTu')} ve={ve} />;
    return (
        <ChayBai
            key={so}
            cau={ds}
            ve={ve}
            onXong={onXong}
            onLamLai={(sai) => lamLai(tron(sai))}
            onLuyenTiep={() => {
                setLan((x) => x + 1);
                datLai();
            }}
        />
    );
}

/**
 * Điền câu: câu có chỗ trống của thẻ Từ vựng (ưu tiên thẻ đã gặp mà mức còn thấp). Mỗi câu trả lời lần đầu là một lượt
 * chấm thẻ chế độ Điền câu (Tốt khi đúng, Khó khi chỉ sai hoa thường, Quên khi sai), gửi qua hàng chờ như các chế độ học.
 */
export function DienCau() {
    useTitle(t('baiTap.dienCau'));
    const { settings } = useStore();
    const [params] = useSearchParams();
    const cap = docCap(params.get('cap')).join(',');
    const ve = duongVe(params.get('ve'), '/bai-tap');
    const [lan, setLan] = useState(0);
    const { duLieu, loi, taiLai, dangTai } = useApi<{ data: TheDienCau[] }>(`/bai-tap/dien-cau${truyVan({ cap, lan: lan || null })}`);
    const goc = duLieu?.data;
    const theCua = useMemo(() => new Map((goc ?? []).map((c) => [`c${c.id}`, c])), [goc]);
    const cacCau = useMemo(
        () =>
            goc?.map((c): CauChay => ({
                khoa: `c${c.id}`,
                kind: 'fill',
                prompt: c.cloze,
                answers: [c.answer],
                options: null,
                hint: nghiaChinh(c, settings.langs) || null,
                explanation: null,
                label: null,
                case_sensitive: c.case_sensitive,
                bank: null,
                tokens: null,
                lead: null,
                frag: [],
                nhom: c.topic?.name ?? t('baiTap.dienCau'),
                nghe: { chu: c.front, url: c.audio },
                sau: c.sentence ? (
                    <p className="mt-3">
                        <span lang="de" className="block font-semibold">
                            {c.sentence.sentence}
                        </span>
                        <CacTieng o={c.sentence.translations} langs={settings.langs} phu />
                    </p>
                ) : null,
            })),
        [goc, settings.langs],
    );
    const { ds, so, lamLai, datLai } = useLuot(cacCau);

    // Xu của các lô lượt chấm máy chủ nhận trong lúc làm (cộng dồn để hiện ở tổng kết)
    const xu = useRef(0);
    const lanCuoi = useRef(0);
    useEffect(() => khiNhanLo((kq) => (xu.current += kq.coins)), []);
    useEffect(() => {
        lanCuoi.current = Date.now();
        xu.current = 0;
    }, [so]);

    const onCham = useCallback(
        (c: CauChay, diem: Diem) => {
            const the = theCua.get(c.khoa);
            if (!the) return;
            const now = Date.now();
            const bat = lanCuoi.current;
            lanCuoi.current = now;
            void themLuot({ uid: ulidTangDan(now), card_id: the.id, session_id: null, mode: 'cloze', rating: diem, reviewed_at_ms: now, duration_ms: Math.max(0, now - bat) });
        },
        [theCua],
    );

    const onXong = useCallback(async (): Promise<DaGui> => {
        await guiDi();
        const cho = docTrangThai().cho;
        return { xu: xu.current, quaNhanh: false, chuaGui: cho > 0, loi: false };
    }, []);

    if (!goc || dangTai || !ds) return <DangTaiDay loi={loi} taiLai={taiLai} />;
    if (ds.length === 0) return <Trong lyDo={t('lamBai.khongCoDienCau')} ve={ve} />;
    return (
        <ChayBai
            key={so}
            cau={ds}
            ve={ve}
            onCham={onCham}
            onXong={onXong}
            onLamLai={(sai) => lamLai(tron(sai))}
            onLuyenTiep={() => {
                setLan((x) => x + 1);
                datLai();
            }}
            ghiChu={t('lamBai.vaoTienDo')}
        />
    );
}
