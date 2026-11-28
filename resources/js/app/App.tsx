import { lazy, Suspense, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router';
import { Sheet, Shell } from './components/Shell';
import { DangTai } from './components/TaiNoiDung';
import { CaiDat } from './screens/CaiDat';
import { CapDo } from './screens/CapDo';
import { ChuDe } from './screens/ChuDe';
import { ChuDeIndex } from './screens/ChuDeIndex';
import { Hoc } from './screens/Hoc';
import { HomNay } from './screens/HomNay';
import { KhongThay } from './screens/KhongThay';
import { BaiTap, SoNhieu } from './screens/luyenTap/BaiTap';
import { DongTuBang } from './screens/luyenTap/DongTu';
import { ChiaDongTu, DienCau, LamBai } from './screens/luyenTap/LamBai';
import { BaiNguPhap, NguPhap } from './screens/luyenTap/NguPhap';
import { SoTay } from './screens/SoTay';
import { TheTu } from './screens/TheTu';
import { TraTu } from './screens/TraTu';
import { VietCau } from './screens/luyenTap/VietCau';
import { StoreProvider } from './state/store';
import { TaiKhoanProvider } from './state/taiKhoan';

// Phần AI tải riêng (gói nhỏ hơn cho người không dùng AI)
const HoiAi = lazy(() => import('./screens/ai/HoiAi'));
const CuocTroChuyen = lazy(() => import('./screens/ai/CuocTroChuyen'));
const XemBaiHocAi = lazy(() => import('./screens/ai/XemBaiHocAi'));

function Cho({ children }: { children: ReactNode }) {
    return (
        <Suspense
            fallback={
                <Sheet className="px-5 py-6 md:px-8">
                    <DangTai />
                </Sheet>
            }
        >
            {children}
        </Suspense>
    );
}

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

/** Mỗi lần mở một lượt làm bài là một lượt mới (kể cả bấm lại cùng đường dẫn). */
function KhoaTheoDuong({ children }: { children: (key: string) => ReactNode }) {
    const { key } = useLocation();
    return children(key);
}

/** Các màn hình của app học; phiên học (/hoc) và lượt làm bài chiếm cả màn hình, không có thanh điều hướng. */
export function AppRoutes() {
    return (
        <Routes>
            <Route path="hoc" element={<HocRoute />} />
            <Route path="bai-tap/lam" element={<KhoaTheoDuong>{(k) => <LamBai key={k} />}</KhoaTheoDuong>} />
            <Route path="bai-tap/dien-cau" element={<KhoaTheoDuong>{(k) => <DienCau key={k} />}</KhoaTheoDuong>} />
            <Route path="bai-tap/chia-dong-tu" element={<KhoaTheoDuong>{(k) => <ChiaDongTu key={k} />}</KhoaTheoDuong>} />
            <Route element={<ShellLayout />}>
                <Route index element={<Navigate to="/hom-nay" replace />} />
                <Route path="hom-nay" element={<HomNay />} />
                <Route path="chu-de" element={<ChuDeIndex key="chu-de" />} />
                <Route path="cap-do" element={<CapDo />} />
                <Route path="giao-tiep" element={<ChuDeIndex key="giao-tiep" kieu="giao-tiep" />} />
                <Route path="chu-de/:ma" element={<ChuDe />} />
                <Route path="tu/:ma" element={<TheTu />} />
                <Route path="ngu-phap" element={<NguPhap />} />
                <Route path="ngu-phap/:slug" element={<BaiNguPhap />} />
                <Route path="bai-tap" element={<BaiTap />} />
                <Route path="so-nhieu" element={<SoNhieu />} />
                <Route path="dong-tu" element={<DongTuBang />} />
                <Route path="tra-tu" element={<TraTu />} />
                <Route path="so-tay" element={<SoTay />} />
                <Route path="cai-dat" element={<CaiDat />} />
                <Route path="bai-tap/viet-cau" element={<KhoaTheoDuong>{(k) => <VietCau key={k} />}</KhoaTheoDuong>} />
                <Route path="hoi-ai" element={<Cho><HoiAi /></Cho>} />
                <Route path="hoi-ai/tro-chuyen/:ma" element={<Cho><CuocTroChuyen /></Cho>} />
                <Route path="hoi-ai/bai-hoc/:id" element={<Cho><XemBaiHocAi /></Cho>} />
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
