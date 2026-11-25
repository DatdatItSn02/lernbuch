import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router';
import { Shell } from './components/Shell';
import { CaiDat } from './screens/CaiDat';
import { ChuDe } from './screens/ChuDe';
import { ChuDeIndex } from './screens/ChuDeIndex';
import { Hoc } from './screens/Hoc';
import { HomNay } from './screens/HomNay';
import { KhongThay } from './screens/KhongThay';
import { SapCo } from './screens/SapCo';
import { TheTu } from './screens/TheTu';
import { StoreProvider } from './state/store';

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
                <Route path="chu-de" element={<ChuDeIndex />} />
                <Route path="chu-de/:ma" element={<ChuDe />} />
                <Route path="tu/:ma" element={<TheTu />} />
                <Route path="ngu-phap" element={<SapCo title="Ngữ pháp" what="218 bài ngữ pháp A1–B2 giải thích bằng tiếng Việt, kèm bài tập." />} />
                <Route path="tra-tu" element={<SapCo title="Tra từ" what="Tra từ bằng tiếng Đức, Việt, Anh hoặc Trung, gõ không cần dấu." />} />
                <Route path="cai-dat" element={<CaiDat />} />
                <Route path="*" element={<KhongThay />} />
            </Route>
        </Routes>
    );
}

export default function App() {
    return (
        <StoreProvider>
            <BrowserRouter basename="/app">
                <AppRoutes />
            </BrowserRouter>
        </StoreProvider>
    );
}
