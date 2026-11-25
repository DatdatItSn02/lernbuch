import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import App from './App';

describe('App', () => {
    it('hiện tên ứng dụng và giữ đúng chữ có dấu', () => {
        render(<App />);
        expect(screen.getByRole('heading', { name: 'Lernbuch' })).toBeTruthy();
        expect(screen.getByText(/App học đang được xây dựng/)).toBeTruthy();
    });
});
