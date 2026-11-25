import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter, useLocation } from 'react-router';
import { AppRoutes } from '../app/App';
import { StoreProvider, initialState } from '../app/state/store';

type State = ReturnType<typeof initialState>;

/** Ghi đường dẫn hiện tại để test kiểm chuyển trang. */
function Where() {
    const { pathname, search } = useLocation();
    return <output data-testid="duong-dan">{pathname + search}</output>;
}

export function renderApp(path: string, state: State = initialState()) {
    return render(
        <StoreProvider initial={state}>
            <MemoryRouter initialEntries={[path]}>
                <AppRoutes />
                <Where />
            </MemoryRouter>
        </StoreProvider>,
    );
}

export function renderWith(node: ReactNode, path = '/', state: State = initialState()) {
    return render(
        <StoreProvider initial={state}>
            <MemoryRouter initialEntries={[path]}>
                {node}
                <Where />
            </MemoryRouter>
        </StoreProvider>,
    );
}
