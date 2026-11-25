import type { Gender, Word } from '../data/demo';

const COLOR: Record<Gender, string> = { der: 'text-der', die: 'text-die', das: 'text-das' };
const POS: Record<Word['pos'], string> = { noun: 'danh từ', verb: 'động từ', adj: 'tính từ' };

export const posLabel = (w: Word) => POS[w.pos];

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
    if (word.plural === null || word.plural === undefined) return <span>thường chỉ dùng số ít</span>;
    return (
        <span lang="de">
            <span className="font-bold text-plural">die</span> {word.plural}
        </span>
    );
}
