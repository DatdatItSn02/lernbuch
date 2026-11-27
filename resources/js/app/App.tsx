import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router';
import { Shell } from './components/Shell';
import { CaiDat } from './screens/CaiDat';
import { CapDo } from './screens/CapDo';
import { ChuDe } from './screens/ChuDe';
import { ChuDeIndex } from './screens/ChuDeIndex';
import { Hoc } from './screens/Hoc';
import { HomNay } from './screens/HomNay';
import { KhongThay } from './screens/KhongThay';
import { SapCo } from './screens/SapCo';
import { SoTay } from './screens/SoTay';
import { TheTu } from './screens/TheTu';
import { TraTu } from './screens/TraTu';
import { StoreProvider } from './state/store';
import { TaiKhoanProvider } from './state/taiKhoan';
import { t } from './i18n';

function ShellLayout() {
    return (
        <Shell>
            <Outlet />
        </Shell>
    );
}

/** Mỗi lần mở /hoc (kể cả bấm "Học tiếp" ngay trong trang học) là một phiên mới. */
function HocRoute() {
    const { key } = useLocation();
    return <Hoc key={key} />;
}

/** Các màn hình của app học; phiên học (/hoc) chiếm cả màn hình, không có thanh điều hướng. */
export function AppRoutes() {
    return (
        <Routes>
            <Route path="hoc" element={<HocRoute />} />
            <Route element={<ShellLayout />}>
                <Route index element={<Navigate to="/hom-nay" replace />} />
                <Route path="hom-nay" element={<HomNay />} />
                <Route path="chu-de" element={<ChuDeIndex key="chu-de" />} />
                <Route path="cap-do" element={<CapDo />} />
                <Route path="giao-tiep" element={<ChuDeIndex key="giao-tiep" kieu="giao-tiep" />} />
                <Route path="chu-de/:ma" element={<ChuDe />} />
                <Route path="tu/:ma" element={<TheTu />} />
                <Route path="ngu-phap" element={<SapCo title={t('khung.nguPhap')} what={t('sapCo.nguPhap')} />} />
                <Route path="tra-tu" element={<TraTu />} />
                <Route path="so-tay" element={<SoTay />} />
                <Route path="cai-dat" element={<CaiDat />} />
                <Route path="*" element={<KhongThay />} />
            </Route>
        </Routes>
    );
}

export default function App() {
    return (
        <StoreProvider>
            <TaiKhoanProvider>
                <BrowserRouter basename="/app">
                    <AppRoutes />
                </BrowserRouter>
            </TaiKhoanProvider>
        </StoreProvider>
    );
}
