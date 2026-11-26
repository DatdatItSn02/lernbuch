import type { ReactNode } from 'react';

export type ChoiceOption<T> = { value: T; label: ReactNode };

/** Nhóm chọn một (radio) vẽ thành hàng ô vuông; chọn bằng chuột, chạm hoặc phím mũi tên như radio thường. */
export function Choice<T extends string | number>({
    legend,
    name,
    value,
    options,
    onChange,
    hideLegend = false,
    className = '',
}: {
    legend: string;
    name: string;
    value: T;
    options: ChoiceOption<T>[];
    onChange: (v: T) => void;
    hideLegend?: boolean;
    className?: string;
}) {
    return (
        <fieldset className={className}>
            <legend className={hideLegend ? 'sr-only' : 'text-label font-semibold'}>{legend}</legend>
            <div className={`flex flex-wrap gap-1.5 ${hideLegend ? '' : 'mt-2'}`}>
                {options.map((o) => {
                    const on = o.value === value;
                    return (
                        <label
                            key={String(o.value)}
                            className={`flex min-h-11 min-w-11 cursor-pointer items-center justify-center gap-1.5 rounded-md border px-3.5 text-label font-semibold transition-colors duration-200 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-focus ${
                                on ? 'border-tim bg-tim-tint text-ink' : 'border-line text-ink-2 hover:border-line-strong hover:text-ink'
                            }`}
                        >
                            <input type="radio" name={name} className="sr-only" checked={on} onChange={() => onChange(o.value)} />
                            {o.label}
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
