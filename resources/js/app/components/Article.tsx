import type { Gender, Word } from '../data/demo';
import type { TheTomTat } from '../lib/noiDung';
import { t, type Khoa } from '../i18n';

const COLOR: Record<Gender, string> = { der: 'text-der', die: 'text-die', das: 'text-das' };
const POS = { noun: 'loaiTu.danhTu', verb: 'loaiTu.dongTu', adj: 'loaiTu.tinhTu' } as const satisfies Record<Word['pos'], Khoa>;
/** Mạo từ số nhiều của tiếng Đức (chữ của ngôn ngữ đang học, không dịch). */
const DIE = 'die';

export const posLabel = (w: Word) => t(POS[w.pos]);

/** Mạo từ tô màu theo giống, luôn có chữ mạo từ để người mù màu vẫn đọc được. */
export function Article({ gender }: { gender?: Gender }) {
    if (!gender) return null;
    return <span className={`${COLOR[gender]} font-bold`}>{gender}</span>;
}

/** Từ tiếng Đức kèm mạo từ, đặt lang="de". */
export function GermanWord({ word, className = '' }: { word: Word; className?: string }) {
    return (
        <span lang="de" className={className}>
            {word.gender && (
                <>
                    <Article gender={word.gender} />{' '}
                </>
            )}
            {word.lemma}
        </span>
    );
}

export function PluralLine({ word }: { word: Word }) {
    if (word.pos === 'verb' && word.principalParts) return <span lang="de">{word.principalParts}</span>;
    if (word.pos !== 'noun') return null;
    if (word.plural === null || word.plural === undefined) return <span>{t('loaiTu.chiSoIt')}</span>;
    return (
        <span lang="de">
            <span className="font-bold text-plural">{DIE}</span> {word.plural}
        </span>
    );
}

/** Mặt trước của thẻ (dữ liệu API): mạo từ tô màu khi nó đứng đầu ("das Brötchen"), còn lại giữ nguyên; lang="de". */
export function TuDuc({ the, className = '' }: { the: Pick<TheTomTat, 'article' | 'word' | 'front'>; className?: string }) {
    return (
        <span lang="de" className={className}>
            {the.article ? (
                <>
                    <Article gender={the.article} /> {the.word}
                </>
            ) : (
                the.front
            )}
        </span>
    );
}

/** DongDang có hiện gì không (để biết có cần dấu ngăn trước phần sau). */
export const coDang = (the: Pick<TheTomTat, 'pos' | 'forms'>) => !!the.forms && (the.forms !== '–' || the.pos === 'noun');

/** Dòng dạng từ dưới mặt trước: số nhiều của danh từ ("die" tô màu), dạng chia, giới từ đi kèm; "–" ở danh từ là không có số nhiều. */
export function DongDang({ the }: { the: Pick<TheTomTat, 'pos' | 'forms'> }) {
    const f = the.forms;
    if (!f || !coDang(the)) return null;
    if (f === '–') return <span>{t('loaiTu.chiSoIt')}</span>;
    if (the.pos === 'noun' && f.startsWith(`${DIE} `)) {
        return (
            <span lang="de">
                <span className="font-bold text-plural">{DIE}</span> {f.slice(DIE.length + 1)}
            </span>
        );
    }
    return <span lang="de">{f}</span>;
}
