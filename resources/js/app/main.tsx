import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { datNgonNgu } from './i18n';
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
