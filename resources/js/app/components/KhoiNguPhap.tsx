import { Info, Lightbulb, RotateCcw } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import type { Khoi, Nghia } from '../lib/baiTap';
import { cacDong, canNho, maPhan, tachNhan, tachTieng, tieuDeNho, tieuDePhan } from '../lib/nguPhap';
import type { NgonNguNghia } from '../lib/noiDung';
import { t, type Khoa } from '../i18n';

type Langs = Record<NgonNguNghia, boolean>;
const THU: NgonNguNghia[] = ['vi', 'en', 'zh'];

/** Phần lớn (tiêu đề h2) của bài, để làm mục lục. */
export type Phan = { id: string; chu: string };

export function cacPhan(khoi: Khoi[]): Phan[] {
    const ds: Phan[] = [];
    for (const k of khoi) {
        if (k.t === 'h2') {
            const chu = tieuDePhan(k.x);
            ds.push({ id: maPhan(chu, ds.length), chu });
        }
    }
    return ds;
}

/** Chữ có phần cần nhớ tô đậm. */
function Dam({ chu }: { chu: string }) {
    return (
        <>
            {canNho(chu).map((m, i) =>
                m.dam ? (
                    <strong key={i} className="font-semibold">
                        {m.chu}
                    </strong>
                ) : (
                    <Fragment key={i}>{m.chu}</Fragment>
                ),
            )}
        </>
    );
}

/** Đoạn tách thành dòng (mỗi câu một dòng); dòng đầu "Nhãn: …" thì nhãn đậm. */
function CacDong({ chu }: { chu: string }) {
    return (
        <>
            {cacDong(chu).map((d, i) => {
                const { nhan, chu: con } = i === 0 ? tachNhan(d) : { nhan: null, chu: d };
                return (
                    <span key={i} className="block">
                        {nhan && <span className="font-semibold">{`${nhan}: `}</span>}
                        <Dam chu={con} />
                    </span>
                );
            })}
        </>
    );
}

/** Cùng một ý theo các thứ tiếng người học bật trong Cài đặt; tắt hết thì hiện thứ tiếng đầu tiên có. */
export function CacTieng({ o, langs, dong = false, phu = false }: { o: Nghia; langs: Langs; dong?: boolean; phu?: boolean }) {
    const bat = THU.filter((l) => langs[l] && o[l]);
    const hien = bat.length > 0 ? bat : THU.filter((l) => o[l]).slice(0, 1);
    return (
        <>
            {hien.map((l, i) => (
                <span key={l} lang={l} className={`block ${phu || i > 0 ? 'text-ink-2' : ''} ${l === 'zh' ? 'font-zh' : ''}`}>
                    {dong ? <CacDong chu={o[l] ?? ''} /> : o[l]}
                </span>
            ))}
        </>
    );
}

/** Chữ của khối: một thứ tiếng thì tách dòng; nhiều thứ tiếng (EN · 中 · VI) thì theo thứ tiếng người học bật. */
function Chu({ chu, langs }: { chu: string; langs: Langs }) {
    const o = tachTieng(chu);
    return o.main !== undefined ? <CacDong chu={o.main} /> : <CacTieng o={o} langs={langs} dong />;
}

const HOP: Record<'tip' | 'recap' | 'note', { khoa: Khoa; Icon: typeof Info }> = {
    tip: { khoa: 'nguPhap.meo', Icon: Lightbulb },
    recap: { khoa: 'nguPhap.nhacLai', Icon: RotateCcw },
    note: { khoa: 'nguPhap.luuY', Icon: Info },
};

function HopGhiChu({ loai, children }: { loai: keyof typeof HOP; children: ReactNode }) {
    const { khoa, Icon } = HOP[loai];
    return (
        <aside className="mt-4 rounded-md bg-sunken px-4 py-3">
            <p className="flex items-center gap-1.5 text-label font-bold">
                <Icon size={16} aria-hidden="true" />
                {t(khoa)}
            </p>
            <div className="mt-1">{children}</div>
        </aside>
    );
}

/** Bảng: ô [chữ, số cột gộp, kiểu]: kiểu 1 tiêu đề cột, 2 tiêu đề hàng, 0 ô thường. */
function Bang({ rows }: { rows: Extract<Khoi, { t: 'tbl' }>['rows'] }) {
    return (
        <div className="mt-4 overflow-x-auto">
            <table className="border-collapse text-left">
                <tbody>
                    {rows.map((hang, i) => (
                        <tr key={i}>
                            {hang.map(([chu, gop, kieu], j) => {
                                const o = { colSpan: gop > 1 ? gop : undefined, className: 'border border-line px-3 py-1.5 align-top' };
                                return kieu === 1 ? (
                                    <th key={j} scope="col" {...o} className={`${o.className} bg-sunken font-semibold`}>
                                        <Dam chu={String(chu)} />
                                    </th>
                                ) : kieu === 2 ? (
                                    <th key={j} scope="row" {...o} className={`${o.className} font-semibold`}>
                                        <Dam chu={String(chu)} />
                                    </th>
                                ) : (
                                    <td key={j} {...o}>
                                        <Dam chu={String(chu)} />
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Nội dung bài ngữ pháp từ các khối (Lernbuch renderBlocks): tiêu đề, đoạn, danh sách, cặp khoá–giải thích, công thức,
 * ví dụ (câu tiếng Đức và bản dịch theo thứ tiếng người học bật), mẹo, nhắc lại, lưu ý, bảng.
 */
export function KhoiNguPhap({ khoi, langs }: { khoi: Khoi[]; langs: Langs }) {
    const phan = cacPhan(khoi);
    const ra: ReactNode[] = [];
    let soPhan = 0;

    for (let i = 0; i < khoi.length; i++) {
        const k = khoi[i];
        if (k.t === 'li' || k.t === 'kv') {
            // Gom các dòng liền nhau cùng kiểu thành một danh sách
            let j = i;
            while (j < khoi.length && khoi[j].t === k.t) j++;
            const nhom = khoi.slice(i, j);
            ra.push(
                k.t === 'li' ? (
                    <ul key={i} className="mt-3 list-disc space-y-1 pl-6">
                        {nhom.map((x, n) => (
                            <li key={n}>{'x' in x && <Chu chu={x.x} langs={langs} />}</li>
                        ))}
                    </ul>
                ) : (
                    <dl key={i} className="mt-3 grid gap-x-5 gap-y-2 sm:grid-cols-[minmax(0,12rem)_1fr]">
                        {nhom.map((x, n) => {
                            if (x.t !== 'kv') return null;
                            const khoa = tachTieng(x.k ?? '');
                            return (
                                <Fragment key={n}>
                                    <dt className="font-semibold">{khoa.main ?? khoa.en ?? khoa.zh}</dt>
                                    <dd className="mb-1 sm:mb-0">
                                        <Chu chu={x.x} langs={langs} />
                                    </dd>
                                </Fragment>
                            );
                        })}
                    </dl>
                ),
            );
            i = j - 1;
            continue;
        }

        switch (k.t) {
            case 'h2': {
                const p = phan[soPhan++];
                ra.push(
                    <h2 key={i} id={p.id} className="mt-8 scroll-mt-20 border-b border-line pb-1 text-title font-bold">
                        {p.chu}
                    </h2>,
                );
                break;
            }
            case 'h3': {
                const o = tachTieng(tieuDeNho(k.x));
                ra.push(
                    <h3 key={i} className="mt-5 font-bold">
                        {o.main !== undefined ? o.main : <CacTieng o={o} langs={langs} />}
                    </h3>,
                );
                break;
            }
            case 'p':
                ra.push(
                    <div key={i} className="mt-3">
                        <Chu chu={k.x} langs={langs} />
                    </div>,
                );
                break;
            case 'tip':
            case 'recap':
            case 'note':
                ra.push(
                    <HopGhiChu key={i} loai={k.t}>
                        <Chu chu={k.x} langs={langs} />
                    </HopGhiChu>,
                );
                break;
            case 'fm':
                ra.push(
                    <div key={i} className="mt-4 border-y-[1.5px] border-line-strong py-3">
                        <p lang="de" className="text-title font-bold break-words">
                            {k.x}
                        </p>
                        {k.L && <CacTieng o={k.L} langs={langs} phu />}
                    </div>,
                );
                break;
            case 'tipl':
                ra.push(
                    <HopGhiChu key={i} loai="tip">
                        {k.L && <CacTieng o={k.L} langs={langs} dong />}
                    </HopGhiChu>,
                );
                break;
            case 'ex': {
                const tr = k.L ? null : k.tr ? tachTieng(k.tr) : null;
                const dich: ReactNode = k.L ? (
                    <CacTieng o={k.L} langs={langs} phu />
                ) : tr?.main !== undefined ? (
                    <span className="block text-ink-2">{tr.main}</span>
                ) : tr ? (
                    <CacTieng o={tr} langs={langs} phu />
                ) : null;
                ra.push(
                    <div key={i} className="mt-2 border-l-[3px] border-line-strong py-0.5 pl-3">
                        <p lang="de" className="font-semibold break-words">
                            {k.x.split('\n').map((d, n) => (
                                <span key={n} className="block">
                                    {n === 0 && k.who && <span className="font-normal text-ink-2">{`${k.who} · `}</span>}
                                    {d}
                                </span>
                            ))}
                        </p>
                        {dich}
                    </div>,
                );
                break;
            }
            case 'tbl':
                ra.push(<Bang key={i} rows={k.rows} />);
                break;
            default:
                break;
        }
    }

    return <>{ra}</>;
}
