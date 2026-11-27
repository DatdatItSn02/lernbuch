import { useEffect, useState } from 'react';

// Cài app (PWA): Chrome, Edge, Android báo "có thể cài" bằng sự kiện beforeinstallprompt; giữ sự kiện đó để nút Cài app gọi.
// iPhone, iPad không có sự kiện này: chỉ cài được bằng Chia sẻ → Thêm vào MH chính của Safari.

type SuKienCai = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }> };

let suKien: SuKienCai | null = null;
const nguoiNghe = new Set<() => void>();
const bao = () => {
    for (const f of nguoiNghe) f();
};

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        suKien = e as SuKienCai;
        bao();
    });
    window.addEventListener('appinstalled', () => {
        suKien = null;
        bao();
    });
}

export type CachCai = 'da-cai' | 'nut' | 'ios' | 'menu';

const dangMoNhuApp = () =>
    typeof window !== 'undefined' &&
    ((typeof window.matchMedia === 'function' && window.matchMedia('(display-mode: standalone)').matches) || (navigator as { standalone?: boolean }).standalone === true);

const laIos = () => typeof navigator !== 'undefined' && /iPad|iPhone|iPod/.test(navigator.userAgent);

function cachCai(): CachCai {
    if (dangMoNhuApp()) return 'da-cai';
    if (suKien) return 'nut';
    return laIos() ? 'ios' : 'menu';
}

/** Cách cài app trên máy này, và hàm mở hộp cài của trình duyệt (chỉ khi cách là "nut"). */
export function useCaiApp(): { cach: CachCai; cai: () => Promise<void> } {
    const [cach, setCach] = useState<CachCai>(cachCai);
    useEffect(() => {
        const f = () => setCach(cachCai());
        nguoiNghe.add(f);
        return () => {
            nguoiNghe.delete(f);
        };
    }, []);
    const cai = async () => {
        const e = suKien;
        if (!e) return;
        await e.prompt();
        const { outcome } = await e.userChoice;
        if (outcome === 'accepted') suKien = null;
        bao();
    };
    return { cach, cai };
}
