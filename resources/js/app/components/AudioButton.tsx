import { Volume2, Snail } from 'lucide-react';
import { useState } from 'react';
import { SPEAK_MESSAGES, speak, type SpeakResult } from '../lib/speech';

type Props = { text: string; slow?: boolean; label?: string; shortcut?: string; iconOnly?: boolean; onResult?: (r: SpeakResult) => void };

/** Nút nghe bằng giọng đọc của trình duyệt; báo rõ khi máy không có giọng tiếng Đức. */
export function AudioButton({ text, slow = false, label, shortcut, iconOnly = false, onResult }: Props) {
    const [state, setState] = useState<'idle' | 'busy' | SpeakResult>('idle');
    const name = label ?? (slow ? 'Nghe chậm' : 'Nghe');
    const Icon = slow ? Snail : Volume2;
    const play = async () => {
        setState('busy');
        const r = await speak(text, slow);
        setState(r === 'ok' ? 'idle' : r);
        onResult?.(r);
    };
    const error = state !== 'idle' && state !== 'busy' && state !== 'ok' ? SPEAK_MESSAGES[state] : null;
    return (
        <span className="inline-flex flex-col">
            <button
                type="button"
                onClick={play}
                aria-busy={state === 'busy'}
                aria-label={iconOnly ? `${name}${shortcut ? ` (phím ${shortcut})` : ''}` : undefined}
                className="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-md border border-line bg-sheet px-3 text-label font-semibold text-ink transition-colors duration-200 hover:border-line-strong hover:bg-sunken disabled:opacity-60"
            >
                <Icon size={18} aria-hidden="true" className={state === 'busy' ? 'animate-pulse' : ''} />
                {!iconOnly && <span>{name}</span>}
                {!iconOnly && shortcut && <kbd className="hidden rounded-sm border border-line px-1.5 text-label text-ink-2 sm:inline">{shortcut}</kbd>}
            </button>
            {error && (
                <span role="status" className="mt-1 max-w-72 text-label text-danger">
                    {error}
                </span>
            )}
        </span>
    );
}
