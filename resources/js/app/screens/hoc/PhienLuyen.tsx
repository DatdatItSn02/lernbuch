// Phiên luyện tập ngoài Flashcard, theo Lernbuch (modes.js): Quiz, Listening, Typing, Ghép cặp, Hỗn hợp, Đặt câu.
// Mỗi thẻ được chấm 0 (Quên), 1 (Khó) hoặc 2 (Tốt) như lượt chấm Flashcard; chỉ lượt đầu của thẻ được tính.
import { Check, X } from 'lucide-react';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode, type Ref } from 'react';
import { DongDang, TuDuc } from '../../components/Article';
import { AudioButton } from '../../components/AudioButton';
import { Kbd } from '../../components/Kbd';
import { LevelIcon } from '../../components/LevelIcon';
import type { PhienHoc, TheHoc } from '../../lib/hoc';
import {
    chamGo,
    chiaVong,
    chonKieu,
    dapAnQuiz,
    goiYChu,
    nghiaChinh,
    tachCau,
    thuTuTron,
    tron,
    type Diem,
    type KetQuaGo,
    type Kieu,
    type TheGhep,
    type Vong,
} from '../../lib/luyen';
import type { NgonNguNghia } from '../../lib/noiDung';
import { nghe as ngheChu } from '../../lib/amThanh';
import { levelName, stageName, type Grade } from '../../lib/srs';
import { useStore } from '../../state/store';
import { t, type Khoa } from '../../i18n';
import { Empty, Header, Summary, ketQua, useGhiLuot, type Done } from './chung';

/** Thẻ sai quay lại trong phiên tối đa chừng này lần (để luyện, không tính vào tiến độ). */
const MAX_ON_LAI = 2;
/** Listening: số giây cho mỗi từ (Lernbuch: 30). */
export const GIAY_NGHE = 30;
const CHU_DAC_BIET = ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'];
const DIEM_SANG_CHAM: Record<Diem, Grade> = { 0: 'quen', 1: 'kho', 2: 'tot' };

type Langs = Record<NgonNguNghia, boolean>;
type MucMot =
    | { kieu: 'quiz'; the: TheHoc; onLai: boolean; dapAn: { cacDapAn: string[]; dung: string } }
    | { kieu: 'typing' | 'listening'; the: TheHoc; onLai: boolean }
    | { kieu: 'sentence'; the: TheHoc; onLai: boolean; thuTu: number[] };
type MucGhep = { kieu: 'match'; vong: Vong; trai: number[]; phai: number[] };
type Muc = MucMot | MucGhep;

function taoMuc(kieu: Exclude<Kieu, 'match'>, the: TheHoc, langs: Langs, onLai: boolean): MucMot {
    if (kieu === 'quiz') return { kieu, the, onLai, dapAn: dapAnQuiz(the, langs) };
    if (kieu === 'sentence' && the.example) return { kieu, the, onLai, thuTu: thuTuTron(tachCau(the.example.sentence).length) };
    // Hỗn hợp không chọn Đặt câu cho thẻ không có câu; phòng khi thiếu thì gõ từ
    return { kieu: kieu === 'sentence' ? 'typing' : kieu, the, onLai };
}

function dungHang(phien: PhienHoc, langs: Langs): Muc[] {
    if (phien.mode === 'match') {
        return chiaVong(phien.cards, langs).map((vong) => ({ kieu: 'match', vong, trai: tron(vong.cards.map((c) => c.id)), phai: tron(vong.cards.map((c) => c.id)) }));
    }
    return phien.cards.map((c) => taoMuc(phien.mode === 'mixed' ? chonKieu(c) : (phien.mode as Exclude<Kieu, 'match'>), c, langs, false));
}

const laGo = (el: EventTarget | null) => el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
const laNut = (el: EventTarget | null) => el instanceof HTMLElement && !!el.closest('button, a');

/** Phím nghe chung: P nghe, S nghe chậm; đang gõ trong ô thì Alt+P, Alt+S. url: file audio của thẻ (không có thì giọng trình duyệt). */
function phimNghe(e: KeyboardEvent, chu: string, url: string | null = null): boolean {
    const dangGo = laGo(e.target);
    if (e.ctrlKey || e.metaKey || (dangGo && !e.altKey) || (!dangGo && e.altKey)) return false;
    if (e.code !== 'KeyP' && e.code !== 'KeyS') return false;
    e.preventDefault();
    void ngheChu(chu, { url, cham: e.code === 'KeyS' });
    return true;
}

export function PhienLuyen({ phien, chuDe, chiOn, onExit }: { phien: PhienHoc; chuDe: string | null; chiOn: boolean; onExit: () => void }) {
    const { settings } = useStore();
    const cheDo = phien.mode;
    const byId = useMemo(() => new Map(phien.cards.map((c) => [c.id, c])), [phien]);
    const [hang, setHang] = useState<Muc[]>(() => dungHang(phien, settings.langs));
    const [vt, setVt] = useState(0);
    const [levels, setLevels] = useState<Record<number, number>>(() => Object.fromEntries(phien.cards.map((c) => [c.id, c.box])));
    const [done, setDone] = useState<Done[]>([]);
    const [xong, setXong] = useState(0);
    const [lanOnLai, setLanOnLai] = useState<Record<number, number>>({});
    const finished = vt >= hang.length;
    const { ghi, daGuiHet } = useGhiLuot(phien.id, cheDo, finished);
    const muc = finished ? null : hang[vt];

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !e.ctrlKey && !e.metaKey && !e.altKey) onExit();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onExit]);

    const cham = useCallback(
        (id: number, diem: Diem, hienLuc: number) => {
            const the = byId.get(id);
            if (!the) return;
            const g = DIEM_SANG_CHAM[diem];
            const d = ketQua(the, g, levels[id] ?? 0, ghi(the, g, hienLuc));
            setLevels((l) => ({ ...l, [id]: d.after }));
            setDone((x) => [...x, d]);
        },
        [byId, levels, ghi],
    );

    const tiep = useCallback(
        (diem: Diem | null) => {
            const m = hang[vt];
            if (!m) return;
            if (m.kieu === 'match') {
                setXong((x) => x + m.vong.cards.filter((c) => !c.nhieu).length);
            } else if (diem === 0 && (lanOnLai[m.the.id] ?? 0) < MAX_ON_LAI) {
                // Sai: thẻ quay lại sau vài thẻ để luyện thêm (như Lernbuch), kiểu câu hỏi mới trộn lại
                const lai = taoMuc(m.kieu, m.the, settings.langs, true);
                setHang((h) => [...h.slice(0, vt + 4), lai, ...h.slice(vt + 4)]);
                setLanOnLai((r) => ({ ...r, [m.the.id]: (r[m.the.id] ?? 0) + 1 }));
            } else {
                setXong((x) => x + 1);
            }
            setVt((v) => v + 1);
        },
        [hang, vt, lanOnLai, settings.langs],
    );

    const level = muc && muc.kieu !== 'match' ? (levels[muc.the.id] ?? 0) : 0;
    return (
        <div className="flex min-h-dvh flex-col bg-paper text-ink">
            <Header onExit={onExit} pos={xong} total={phien.cards.length} finished={finished} />
            {phien.cards.length === 0 ? (
                <Empty onExit={onExit} lyDo={cheDo === 'sentence' ? t('hoc.khongCoCau') : undefined} />
            ) : finished || !muc ? (
                <Summary done={done} byId={byId} chuDe={chuDe} chiOn={chiOn} cheDo={cheDo} tenChuDe={phien.topic?.name} daGuiHet={daGuiHet} />
            ) : muc.kieu === 'match' ? (
                <VongGhep key={vt} muc={muc} langs={settings.langs} onCham={cham} onTiep={() => tiep(null)} />
            ) : (
                <KhungThe key={vt} the={muc.the} level={level} onLai={muc.onLai} hoi={t(`luyen.hoi${HOI[muc.kieu]}`)}>
                    {muc.kieu === 'quiz' ? (
                        <CauQuiz muc={muc} langs={settings.langs} onCham={cham} onTiep={tiep} />
                    ) : muc.kieu === 'sentence' ? (
                        <CauDatCau muc={muc} langs={settings.langs} onCham={cham} onTiep={tiep} />
                    ) : (
                        <CauGo muc={muc} langs={settings.langs} onCham={cham} onTiep={tiep} />
                    )}
                </KhungThe>
            )}
        </div>
    );
}

const HOI = { quiz: 'Quiz', typing: 'Typing', listening: 'Listening', sentence: 'Sentence' } as const;

type CauProps<M> = { muc: M; langs: Langs; onCham: (id: number, diem: Diem, hienLuc: number) => void; onTiep: (diem: Diem) => void };

/** Thẻ rút từ hộp như Flashcard: tai thẻ ghi mức, dòng chủ đề và câu hỏi. */
function KhungThe({ the, level, onLai, hoi, children }: { the: TheHoc; level: number; onLai: boolean; hoi: string; children: ReactNode }) {
    return (
        <main className="mx-auto flex w-full max-w-[640px] flex-1 flex-col px-4 pt-6 pb-48">
            <div className="relative">
                <span aria-hidden="true" className="absolute inset-x-4 -top-2 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                <span aria-hidden="true" className="absolute inset-x-2 -top-1 h-4 rounded-t-md border border-b-0 border-line bg-sheet" />
                <article className="relative flex min-h-[min(56vh,30rem)] flex-col rounded-md border border-line bg-sheet shadow-sheet">
                    <p
                        className="absolute -top-6 right-4 flex h-6 items-center gap-1 rounded-t-md px-2.5 text-label font-semibold"
                        style={{ background: `var(--color-level-${level})`, color: `var(--color-on-level-${level})` }}
                    >
                        <LevelIcon level={level} size={16} title="" className="text-tim" />
                        {level === 0 ? t('hoc.tuMoi') : `${stageName(level)} · ${levelName(level)}`}
                    </p>
                    <div className="relative flex-1 py-6 pr-5 pl-14 md:pl-16">
                        <span aria-hidden="true" className="absolute inset-y-0 left-10 w-[1.5px] bg-pen opacity-45 md:left-12" />
                        <p className="mb-3 flex flex-wrap gap-x-3 text-label text-ink-2">
                            {the.topic && <span>{the.topic.name}</span>}
                            {onLai && <span className="font-semibold text-danger">{t('hoc.onLai')}</span>}
                        </p>
                        <h1 className="mb-2 text-label font-semibold tracking-wide text-ink-2 uppercase">{hoi}</h1>
                        {children}
                    </div>
                </article>
            </div>
        </main>
    );
}

function CacNghia({ the, langs, lon = false }: { the: Pick<TheHoc, 'meanings'>; langs: Langs; lon?: boolean }) {
    const ds = (['vi', 'en', 'zh'] as const).filter((l) => langs[l] && the.meanings[l]);
    const hien = ds.length > 0 ? ds : (['vi', 'en', 'zh'] as const).filter((l) => the.meanings[l]).slice(0, 1);
    return (
        <>
            {hien.map((l, i) => (
                <p key={l} lang={l} className={i === 0 ? (lon ? 'text-title font-bold' : 'font-semibold') : `mt-0.5 text-ink-2 ${l === 'zh' ? 'font-zh' : ''}`}>
                    {the.meanings[l]}
                </p>
            ))}
        </>
    );
}

/** Đáp án đủ thông tin sau khi trả lời: từ có mạo từ, dạng, nghĩa, câu ví dụ. */
function DapAnThe({ the, langs, hienTu = true }: { the: TheHoc; langs: Langs; hienTu?: boolean }) {
    return (
        <div className="lb-reveal mt-6 border-t border-dashed border-line-strong pt-5">
            {hienTu && (
                <p className="text-title font-extrabold break-words">
                    <TuDuc the={the} />
                </p>
            )}
            <p className="text-ink-2">
                <DongDang the={the} />
            </p>
            <div className="mt-2">
                <CacNghia the={the} langs={langs} />
            </div>
            {the.example && (
                <div className="mt-4 flex items-start gap-3">
                    <AudioButton text={the.example.sentence} label={t('nghe.viDu')} iconOnly />
                    <p>
                        <span lang="de" className="block font-semibold">
                            {the.example.sentence}
                        </span>
                        {langs.vi && the.example.translations.vi && <span className="block text-ink-2">{the.example.translations.vi}</span>}
                    </p>
                </div>
            )}
        </div>
    );
}

/** Dòng báo đúng sai (vùng thông báo cho trình đọc màn hình). */
function BaoKetQua({ diem, chu, phu }: { diem: Diem | null; chu: string; phu?: ReactNode }) {
    const mau = diem === 2 ? 'text-tim' : diem === 1 ? 'text-warning-ink' : 'text-danger';
    const Icon = diem === 0 || diem === null ? X : Check;
    return (
        <p role="status" className={`mt-4 flex flex-wrap items-center gap-2 font-bold ${mau}`}>
            <Icon size={20} aria-hidden="true" />
            {chu}
            {phu && <span className="font-normal text-ink-2">{phu}</span>}
        </p>
    );
}

/** Nút chính ở đáy màn hình (Kiểm tra, Tiếp), như nút Lật thẻ của Flashcard. */
function NutDay({ onClick, children, nutRef, disabled = false }: { onClick: () => void; children: ReactNode; nutRef?: Ref<HTMLButtonElement>; disabled?: boolean }) {
    return (
        <footer className="fixed inset-x-0 bottom-0 border-t border-line bg-sheet pb-[env(safe-area-inset-bottom)]">
            <div className="mx-auto max-w-[640px] px-4 py-3">
                <button
                    ref={nutRef}
                    type="button"
                    onClick={onClick}
                    disabled={disabled}
                    className="flex min-h-14 w-full items-center justify-center gap-3 rounded-md bg-tim text-title font-bold text-on-tim transition-colors duration-200 hover:bg-tim-strong disabled:opacity-60"
                >
                    {children}
                </button>
            </div>
        </footer>
    );
}

/** Lúc câu hỏi hiện lên (máy chủ dùng để nhận ra chấm quá nhanh). */
function useLucHien() {
    const luc = useRef(0);
    useEffect(() => {
        luc.current = Date.now();
    }, []);
    return luc;
}

/** Sau khi trả lời: tiêu điểm vào nút Tiếp, để Enter hay Space đi tiếp. */
function useTieuDiemTiep(daTraLoi: boolean) {
    const nut = useRef<HTMLButtonElement>(null);
    useEffect(() => {
        if (daTraLoi) nut.current?.focus();
    }, [daTraLoi]);
    return nut;
}

function CauQuiz({ muc, langs, onCham, onTiep }: CauProps<Extract<MucMot, { kieu: 'quiz' }>>) {
    const { the, dapAn } = muc;
    const [chon, setChon] = useState<number | null>(null);
    const hien = useLucHien();
    const nutTiep = useTieuDiemTiep(chon !== null);
    const dung = chon !== null && dapAn.cacDapAn[chon] === dapAn.dung;

    const pick = useCallback(
        (i: number) => {
            if (chon !== null) return;
            setChon(i);
            onCham(the.id, dapAn.cacDapAn[i] === dapAn.dung ? 2 : 0, hien.current);
        },
        [chon, onCham, the.id, dapAn, hien],
    );

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (phimNghe(e, the.front, the.audio) || e.ctrlKey || e.metaKey || e.altKey || laGo(e.target)) return;
            if (chon === null && /^[1-4]$/.test(e.key) && Number(e.key) <= dapAn.cacDapAn.length) {
                e.preventDefault();
                pick(Number(e.key) - 1);
            } else if (chon !== null && (e.key === 'Enter' || e.key === ' ') && !laNut(e.target)) {
                e.preventDefault();
                onTiep(dung ? 2 : 0);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [chon, dung, pick, onTiep, the.front, the.audio, dapAn.cacDapAn.length]);

    return (
        <>
            <p className="max-w-full text-study-word font-extrabold break-words">
                <TuDuc the={the} />
            </p>
            <div className="mt-3 flex gap-2">
                <AudioButton text={the.front} url={the.audio} shortcut="P" iconOnly />
                <AudioButton text={the.front} url={the.audio} slow shortcut="S" iconOnly />
            </div>
            <div role="group" aria-label={t('luyen.cacDapAn')} className="mt-6 grid gap-2">
                {dapAn.cacDapAn.map((chu, i) => {
                    const laDung = chon !== null && chu === dapAn.dung;
                    const laSai = chon === i && !laDung;
                    return (
                        <button
                            key={chu}
                            type="button"
                            onClick={() => pick(i)}
                            disabled={chon !== null}
                            aria-keyshortcuts={String(i + 1)}
                            className={`flex min-h-12 items-center gap-3 rounded-md border-[1.5px] px-3 py-2 text-left transition-colors duration-200 ${
                                laDung ? 'border-tim bg-tim-tint font-bold' : laSai ? 'border-danger bg-danger-tint' : 'border-line bg-sheet hover:border-line-strong hover:bg-sunken'
                            } disabled:cursor-default`}
                        >
                            <Kbd>{String(i + 1)}</Kbd>
                            <span className="min-w-0 flex-1 break-words">{chu}</span>
                            {laDung && <Check size={20} aria-hidden="true" className="text-tim" />}
                            {laSai && <X size={20} aria-hidden="true" className="text-danger" />}
                        </button>
                    );
                })}
            </div>
            {chon !== null && (
                <>
                    <BaoKetQua diem={dung ? 2 : 0} chu={dung ? t('luyen.dung') : t('luyen.chuaDung')} phu={dung ? undefined : t('luyen.banChon', { chu: dapAn.cacDapAn[chon] })} />
                    <DapAnThe the={the} langs={langs} hienTu={false} />
                    <NutDay nutRef={nutTiep} onClick={() => onTiep(dung ? 2 : 0)}>
                        {t('luyen.tiep')} <Kbd>{t('phim.enter')}</Kbd>
                    </NutDay>
                </>
            )}
        </>
    );
}

const CHU_LY_DO: Record<KetQuaGo['lyDo'], Khoa> = {
    dung: 'luyen.dung',
    thieuMaoTu: 'luyen.thieuMaoTu',
    saiMaoTu: 'luyen.saiMaoTu',
    ganDung: 'luyen.ganDung',
    chuaDung: 'luyen.chuaDung',
    hetGio: 'luyen.hetGio',
};

/** Typing (xem nghĩa, gõ từ) và Listening (nghe, gõ lại trong 30 giây). Gợi ý thì điểm tối đa là Khó. */
function CauGo({ muc, langs, onCham, onTiep }: CauProps<Extract<MucMot, { kieu: 'typing' | 'listening' }>>) {
    const { the } = muc;
    const nghe = muc.kieu === 'listening';
    const [go, setGo] = useState('');
    const [goiY, setGoiY] = useState(false);
    const [kq, setKq] = useState<KetQuaGo | null>(null);
    const [conLai, setConLai] = useState(GIAY_NGHE);
    const oGo = useRef<HTMLInputElement>(null);
    const hien = useLucHien();
    const nutTiep = useTieuDiemTiep(kq !== null);

    const kiem = useCallback(
        (hetGio: boolean) => {
            if (kq) return;
            const v = go.trim();
            if (!v && !hetGio) {
                oGo.current?.focus();
                return;
            }
            let r: KetQuaGo = hetGio && !v ? { diem: 0, lyDo: 'hetGio' } : chamGo(the, v);
            if (goiY) r = { ...r, diem: Math.min(r.diem, 1) as Diem };
            setKq(r);
            onCham(the.id, r.diem, hien.current);
        },
        [kq, go, goiY, the, onCham, hien],
    );

    // Listening: đọc ngay khi hiện, đếm ngược 30 giây; hết giờ thì chấm phần đã gõ
    const kiemMoiNhat = useRef(kiem);
    useLayoutEffect(() => {
        kiemMoiNhat.current = kiem;
    }, [kiem]);
    useEffect(() => {
        if (nghe) void ngheChu(the.front, { url: the.audio });
    }, [nghe, the.front, the.audio]);
    useEffect(() => {
        if (!nghe || kq) return;
        const bat = Date.now();
        const dem = window.setInterval(() => {
            const left = Math.max(0, GIAY_NGHE - (Date.now() - bat) / 1000);
            setConLai(left);
            if (left <= 0) {
                window.clearInterval(dem);
                kiemMoiNhat.current(true);
            }
        }, 100);
        return () => window.clearInterval(dem);
    }, [nghe, kq]);

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (phimNghe(e, the.front, the.audio) || e.ctrlKey || e.metaKey || e.altKey) return;
            if (e.key !== 'Enter' && !(e.key === ' ' && kq)) return;
            if (!kq && e.target === oGo.current) {
                e.preventDefault();
                kiem(false);
            } else if (kq && !laNut(e.target)) {
                e.preventDefault();
                onTiep(kq.diem);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [kq, kiem, onTiep, the.front, the.audio]);

    useEffect(() => {
        oGo.current?.focus();
    }, []);

    const chen = (ch: string) => {
        const o = oGo.current;
        if (!o || kq) return;
        const a = o.selectionStart ?? o.value.length;
        const b = o.selectionEnd ?? a;
        const moi = o.value.slice(0, a) + ch + o.value.slice(b);
        setGo(moi);
        requestAnimationFrame(() => {
            o.focus();
            o.setSelectionRange(a + ch.length, a + ch.length);
        });
    };

    return (
        <>
            {nghe ? (
                <div className="flex flex-wrap items-center gap-2">
                    <AudioButton text={the.front} url={the.audio} label={t('nghe.nghe')} shortcut="Alt+P" />
                    <AudioButton text={the.front} url={the.audio} slow label={t('nghe.cham')} shortcut="Alt+S" />
                </div>
            ) : (
                <CacNghia the={the} langs={langs} lon />
            )}
            {nghe && !kq && (
                <div role="timer" aria-label={t('luyen.thoiGian')} className="mt-4 h-2 overflow-hidden rounded-sm bg-sunken">
                    <div className={`h-full transition-[width] duration-100 ${conLai <= 5 ? 'bg-danger' : 'bg-tim'}`} style={{ width: `${(conLai / GIAY_NGHE) * 100}%` }} />
                    <span className="sr-only">{t('luyen.conLai', { n: Math.ceil(conLai) })}</span>
                </div>
            )}
            {goiY && !kq && (
                <div className="mt-4 text-ink-2">
                    {nghe ? <CacNghia the={the} langs={langs} /> : <p lang="de" className="font-mono text-title tracking-widest">{goiYChu(the)}</p>}
                </div>
            )}
            <div className="mt-5">
                <input
                    ref={oGo}
                    type="text"
                    lang="de"
                    value={go}
                    onChange={(e) => setGo(e.target.value)}
                    readOnly={kq !== null}
                    autoComplete="off"
                    autoCapitalize="off"
                    spellCheck={false}
                    aria-label={t('luyen.traLoi')}
                    className="min-h-12 w-full rounded-md border-[1.5px] border-line-strong bg-paper px-3 text-title outline-offset-2 read-only:bg-sunken"
                />
                {!kq && (
                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                        {CHU_DAC_BIET.map((ch) => (
                            <button
                                key={ch}
                                type="button"
                                tabIndex={-1}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => chen(ch)}
                                aria-label={t('luyen.goChu', { chu: ch })}
                                className="min-h-11 min-w-11 rounded-md border border-line bg-sheet font-semibold hover:bg-sunken"
                            >
                                {ch}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => {
                                setGoiY(true);
                                oGo.current?.focus();
                            }}
                            disabled={goiY}
                            className="ml-auto min-h-11 rounded-md border border-line px-3 text-label font-semibold hover:bg-sunken disabled:opacity-60"
                        >
                            {nghe ? t('luyen.goiYNghia') : t('luyen.goiYChu')}
                        </button>
                    </div>
                )}
            </div>
            {kq && (
                <>
                    <BaoKetQua
                        diem={kq.diem}
                        chu={t(CHU_LY_DO[kq.lyDo])}
                        phu={
                            <>
                                {goiY && kq.diem > 0 && `${t('luyen.coGoiY')} `}
                                {go.trim() && kq.lyDo !== 'dung' && <s>{t('luyen.banGo', { chu: go.trim() })}</s>}
                            </>
                        }
                    />
                    <DapAnThe the={the} langs={langs} />
                </>
            )}
            {kq ? (
                <NutDay nutRef={nutTiep} onClick={() => onTiep(kq.diem)}>
                    {t('luyen.tiep')} <Kbd>{t('phim.enter')}</Kbd>
                </NutDay>
            ) : (
                <NutDay onClick={() => kiem(false)}>
                    {t('luyen.kiemTra')} <Kbd>{t('phim.enter')}</Kbd>
                </NutDay>
            )}
        </>
    );
}

/** Đặt câu: bấm các chữ theo đúng thứ tự của câu ví dụ. Sửa rồi mới đúng thì được Khó. */
function CauDatCau({ muc, langs, onCham, onTiep }: CauProps<Extract<MucMot, { kieu: 'sentence' }>>) {
    const { the, thuTu } = muc;
    const cau = the.example?.sentence ?? '';
    const chu = useMemo(() => tachCau(cau), [cau]);
    const [da, setDa] = useState<number[]>([]);
    const [loi, setLoi] = useState(0);
    const [kq, setKq] = useState<Diem | null>(null);
    const [boCuoc, setBoCuoc] = useState(false);
    const hien = useLucHien();
    const nutTiep = useTieuDiemTiep(kq !== null);
    const du = da.length === chu.length;

    const kiem = useCallback(() => {
        if (kq !== null || !du) return;
        if (da.every((i, k) => chu[i] === chu[k])) {
            const d: Diem = loi > 0 ? 1 : 2;
            setKq(d);
            onCham(the.id, d, hien.current);
        } else {
            setLoi((n) => n + 1);
        }
    }, [kq, du, da, chu, loi, onCham, the.id, hien]);

    const xemDapAn = () => {
        if (kq !== null) return;
        setBoCuoc(true);
        setKq(0);
        setDa(chu.map((_, i) => i));
        onCham(the.id, 0, hien.current);
    };

    useLayoutEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (phimNghe(e, cau) || e.ctrlKey || e.metaKey || e.altKey || laGo(e.target)) return;
            if (kq === null && e.key === 'Backspace' && da.length > 0) {
                e.preventDefault();
                setDa((x) => x.slice(0, -1));
            } else if (e.key === 'Enter' && !laNut(e.target)) {
                e.preventDefault();
                if (kq === null) kiem();
                else onTiep(kq);
            } else if (e.key === ' ' && kq !== null && !laNut(e.target)) {
                e.preventDefault();
                onTiep(kq);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [kq, da.length, kiem, onTiep, cau]);

    const nutChu = 'min-h-11 rounded-md border-[1.5px] px-3 font-semibold transition-colors duration-200 disabled:cursor-default';
    return (
        <>
            <div className="text-title">
                <CacNghia the={the} langs={langs} />
                {langs.vi && the.example?.translations.vi && <p className="mt-1 text-ink-2">{the.example.translations.vi}</p>}
            </div>
            <div className="mt-3 flex gap-2">
                <AudioButton text={cau} label={t('luyen.ngheCau')} shortcut="P" iconOnly />
                <AudioButton text={cau} slow shortcut="S" iconOnly />
            </div>
            <div role="group" aria-label={t('luyen.cauCuaBan')} className={`mt-5 flex min-h-16 flex-wrap gap-2 rounded-md border-[1.5px] border-dashed p-2 ${kq === 2 ? 'border-tim' : 'border-line-strong'}`}>
                {da.map((i, k) => (
                    <button
                        key={`${i}-${k}`}
                        type="button"
                        lang="de"
                        disabled={kq !== null}
                        onClick={() => setDa((x) => x.filter((_, j) => j !== k))}
                        aria-label={kq === null ? t('luyen.boChu', { chu: chu[i] }) : undefined}
                        className={`${nutChu} border-tim bg-tim-tint`}
                    >
                        {chu[i]}
                    </button>
                ))}
            </div>
            {kq === null && (
                <div role="group" aria-label={t('luyen.cacChu')} className="mt-3 flex flex-wrap gap-2">
                    {thuTu.map((i) =>
                        da.includes(i) ? null : (
                            <button
                                key={i}
                                type="button"
                                lang="de"
                                onClick={() => setDa((x) => [...x, i])}
                                aria-label={t('luyen.themChu', { chu: chu[i] })}
                                className={`${nutChu} border-line bg-sheet hover:border-line-strong hover:bg-sunken`}
                            >
                                {chu[i]}
                            </button>
                        ),
                    )}
                </div>
            )}
            {kq === null && loi > 0 && <BaoKetQua key={loi} diem={0} chu={t('luyen.thuLai')} />}
            {kq === null && (
                <div className="mt-4">
                    <button type="button" onClick={xemDapAn} className="min-h-11 rounded-md border border-line px-3 text-label font-semibold hover:bg-sunken">
                        {t('luyen.xemDapAn')}
                    </button>
                </div>
            )}
            {kq !== null && (
                <>
                    <BaoKetQua diem={kq} chu={kq === 2 ? t('luyen.dung') : kq === 1 ? t('luyen.dungSauKhiSua') : boCuoc ? t('luyen.dapAn') : t('luyen.chuaDung')} />
                    <DapAnThe the={the} langs={langs} />
                </>
            )}
            {kq !== null ? (
                <NutDay nutRef={nutTiep} onClick={() => onTiep(kq)}>
                    {t('luyen.tiep')} <Kbd>{t('phim.enter')}</Kbd>
                </NutDay>
            ) : (
                <NutDay onClick={kiem} disabled={!du}>
                    {t('luyen.kiemTra')} <Kbd>{t('phim.enter')}</Kbd>
                </NutDay>
            )}
        </>
    );
}

/** Ghép cặp: nối từ tiếng Đức với nghĩa. Thẻ nối sai lần nào thì được Khó; cặp nhiễu không chấm. */
function VongGhep({ muc, langs, onCham, onTiep }: { muc: MucGhep; langs: Langs; onCham: (id: number, diem: Diem, hienLuc: number) => void; onTiep: () => void }) {
    const theo = useMemo(() => new Map<number, TheGhep>(muc.vong.cards.map((c) => [c.id, c])), [muc]);
    const [trai, setTrai] = useState<number | null>(null);
    const [phai, setPhai] = useState<number | null>(null);
    const [noiTrai, setNoiTrai] = useState<number[]>([]);
    const [noiPhai, setNoiPhai] = useState<number[]>([]);
    const [sai, setSai] = useState<Record<number, number>>({});
    const [bao, setBao] = useState<{ ok: boolean; chu: string } | null>(null);
    const [rung, setRung] = useState<[number, number] | null>(null);
    const hienVong = useLucHien();
    const xongVong = noiTrai.length === muc.vong.cards.length;

    useEffect(() => {
        if (!xongVong) return;
        const hen = window.setTimeout(onTiep, 650);
        return () => window.clearTimeout(hen);
    }, [xongVong, onTiep]);
    useEffect(() => {
        if (!rung) return;
        const hen = window.setTimeout(() => setRung(null), 520);
        return () => window.clearTimeout(hen);
    }, [rung]);

    const thu = (a: number, b: number) => {
        setTrai(null);
        setPhai(null);
        const ca = theo.get(a)!;
        const cb = theo.get(b)!;
        if (a === b || nghiaChinh(ca, langs) === nghiaChinh(cb, langs)) {
            setNoiTrai((x) => [...x, a]);
            setNoiPhai((x) => [...x, b]);
            setBao({ ok: true, chu: t('luyen.daKhop', { tu: ca.front, nghia: nghiaChinh(cb, langs) }) });
            // Thời gian của mỗi cặp tính từ cặp nối trước (useGhiLuot lấy mốc muộn hơn giữa lúc hiện vòng và lượt trước)
            if (!ca.nhieu) onCham(a, sai[a] ? 1 : 2, hienVong.current);
        } else {
            setSai((s) => ({ ...s, [a]: (s[a] ?? 0) + 1 }));
            setBao({ ok: false, chu: t('luyen.chuaKhop', { tu: ca.front, nghia: nghiaChinh(cb, langs) }) });
            setRung([a, b]);
        }
    };
    const chonTrai = (id: number) => {
        if (rung) return;
        const moi = trai === id ? null : id;
        setTrai(moi);
        if (moi !== null && phai !== null) thu(moi, phai);
    };
    const chonPhai = (id: number) => {
        if (rung) return;
        const moi = phai === id ? null : id;
        setPhai(moi);
        if (moi !== null && trai !== null) thu(trai, moi);
    };

    const nut = (dangChon: boolean, daNoi: boolean, dangRung: boolean) =>
        `flex min-h-14 w-full items-center gap-2 rounded-md border-[1.5px] px-3 py-2 text-left transition-colors duration-200 disabled:cursor-default ${
            daNoi ? 'border-tim bg-tim-tint text-ink-2' : dangRung ? 'border-danger bg-danger-tint' : dangChon ? 'border-tim bg-tim-tint font-bold' : 'border-line bg-sheet hover:border-line-strong hover:bg-sunken'
        }`;
    return (
        <main className="mx-auto flex w-full max-w-[720px] flex-1 flex-col px-4 pt-6 pb-16">
            <h1 className="text-title font-bold">{t('luyen.hoiMatch')}</h1>
            <div className="mt-4 grid grid-cols-2 gap-3">
                <div role="group" aria-label={t('luyen.cotTu')} className="grid content-start gap-2">
                    {muc.trai.map((id) => {
                        const c = theo.get(id)!;
                        const daNoi = noiTrai.includes(id);
                        return (
                            <button key={id} type="button" lang="de" disabled={daNoi} aria-pressed={trai === id} onClick={() => chonTrai(id)} className={nut(trai === id, daNoi, rung?.[0] === id)}>
                                <span className="min-w-0 flex-1 font-semibold break-words">
                                    <TuDuc the={c} />
                                </span>
                                {daNoi && <Check size={18} aria-hidden="true" className="shrink-0 text-tim" />}
                            </button>
                        );
                    })}
                </div>
                <div role="group" aria-label={t('luyen.cotNghia')} className="grid content-start gap-2">
                    {muc.phai.map((id) => {
                        const c = theo.get(id)!;
                        const daNoi = noiPhai.includes(id);
                        return (
                            <button key={id} type="button" disabled={daNoi} aria-pressed={phai === id} onClick={() => chonPhai(id)} className={nut(phai === id, daNoi, rung?.[1] === id)}>
                                <span className="min-w-0 flex-1 break-words">{nghiaChinh(c, langs)}</span>
                                {daNoi && <Check size={18} aria-hidden="true" className="shrink-0 text-tim" />}
                            </button>
                        );
                    })}
                </div>
            </div>
            <p role="status" className={`mt-4 min-h-6 font-semibold ${bao?.ok === false ? 'text-danger' : 'text-ink-2'}`}>
                {bao?.chu}
            </p>
        </main>
    );
}
