import { useId, useState } from 'react';
import { Choice } from '../components/Choice';
import { Sheet } from '../components/Shell';
import { NEW_COUNTS, canPersist, useStore, type FontSize, type Lang, type NewCount, type Season, type Settings, type Theme } from '../state/store';
import { useTitle } from '../lib/useTitle';

const SEASON_NAME: Record<Exclude<Season, 'auto'>, string> = { spring: 'xuân', summer: 'hạ', autumn: 'thu', winter: 'đông' };
const LANGS: { id: Lang; label: string }[] = [
    { id: 'vi', label: 'Tiếng Việt' },
    { id: 'en', label: 'Tiếng Anh' },
    { id: 'zh', label: 'Tiếng Trung' },
];

export function CaiDat() {
    const { settings, season, dispatch } = useStore();
    useTitle('Cài đặt');
    const [saving] = useState(canPersist);
    const uid = useId();
    const set = (patch: Partial<Settings>) => dispatch({ type: 'settings', patch });

    return (
        <Sheet className="mx-auto max-w-[720px] px-5 py-6 md:px-8">
            <h1 className="text-headline font-bold">Cài đặt</h1>
            <p role={saving ? undefined : 'alert'} className={`mt-1 ${saving ? 'text-ink-2' : 'font-semibold text-danger'}`}>
                {saving
                    ? 'Cài đặt lưu trên trình duyệt này.'
                    : 'Trình duyệt đang chặn lưu (có thể là chế độ riêng tư): cài đặt chỉ giữ đến khi tải lại trang.'}
            </p>

            <div className="mt-6 space-y-6">
                <Choice<Theme>
                    legend="Giao diện"
                    name={`${uid}-giao-dien`}
                    value={settings.theme}
                    onChange={(theme) => set({ theme })}
                    options={[
                        { value: 'light', label: 'Sáng' },
                        { value: 'dark', label: 'Tối' },
                    ]}
                />
                <Choice<FontSize>
                    legend="Cỡ chữ"
                    name={`${uid}-co-chu`}
                    value={settings.fontSize}
                    onChange={(fontSize) => set({ fontSize })}
                    options={[
                        { value: 'sm', label: 'Nhỏ' },
                        { value: 'md', label: 'Vừa' },
                        { value: 'lg', label: 'Lớn' },
                    ]}
                />
                <Choice<Season>
                    legend="Cành theo mùa ở Hôm nay"
                    name={`${uid}-mua`}
                    value={settings.season}
                    onChange={(s) => set({ season: s })}
                    options={[
                        { value: 'auto', label: `Theo tháng (đang ${SEASON_NAME[season]})` },
                        { value: 'spring', label: 'Xuân' },
                        { value: 'summer', label: 'Hạ' },
                        { value: 'autumn', label: 'Thu' },
                        { value: 'winter', label: 'Đông' },
                    ]}
                />

                <fieldset>
                    <legend className="text-label font-semibold">Nghĩa hiện trên thẻ</legend>
                    <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        {LANGS.map((l) => (
                            <label key={l.id} className="flex min-h-11 cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={settings.langs[l.id]}
                                    onChange={() => set({ langs: { ...settings.langs, [l.id]: !settings.langs[l.id] } })}
                                    className="size-5"
                                />
                                {l.label}
                            </label>
                        ))}
                    </div>
                </fieldset>

                <Choice<NewCount>
                    legend="Từ mới mỗi lượt học"
                    name={`${uid}-tu-moi`}
                    value={settings.newPerSession}
                    onChange={(newPerSession) => set({ newPerSession })}
                    options={NEW_COUNTS.map((n) => ({ value: n, label: n === 'all' ? 'Tất cả' : String(n) }))}
                />
            </div>
        </Sheet>
    );
}
