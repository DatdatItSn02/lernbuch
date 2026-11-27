import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { datNgonNgu } from './i18n';
import { guiLaiKetQua } from './lib/baiTap';
import { batDauHangCho } from './lib/hangCho';
import { registerServiceWorker } from './registerServiceWorker';

// Ngôn ngữ giao diện máy chủ đã đặt ở <html lang> theo tài khoản; đặt trước khi vẽ để mọi chữ đúng ngôn ngữ
datNgonNgu(document.documentElement.lang);

const root = document.getElementById('app');

if (root) {
    createRoot(root).render(
        <StrictMode>
            <App />
        </StrictMode>,
    );
}

registerServiceWorker();
// Lượt chấm và kết quả bài tập còn nằm trên máy từ lần trước (mất mạng, đóng tab): gửi ngay; có mạng lại thì gửi tiếp
batDauHangCho();
void guiLaiKetQua();
window.addEventListener('online', () => void guiLaiKetQua());
