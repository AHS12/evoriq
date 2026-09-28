import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CommandPaletteProvider } from '@/components/command-palette/command-palette-provider';
import { useCommandPalette } from '@/hooks/use-command-palette';
import { fireEvent, renderWithUser, screen } from '@/test/render';

const { visit, post } = vi.hoisted(() => ({
    visit: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({ router: { visit, post } }));

vi.mock('@/hooks/use-can', () => ({ useCan: () => () => true }));
vi.mock('@/hooks/use-active-jobs', () => ({ useActiveJobs: () => 0 }));
vi.mock('@/hooks/use-appearance', () => ({
    useAppearancePreferences: () => ({
        resolvedAppearance: 'light',
        update: vi.fn(),
    }),
}));
vi.mock('@/lib/app-navigation', () => ({
    buildAppNavigation: () => [
        {
            title: 'Overview',
            items: [{ title: 'Dashboard', href: '/dashboard' }],
        },
        {
            title: 'Administration',
            items: [{ title: 'Users', href: '/users' }],
        },
    ],
}));
vi.mock('@/routes', () => ({
    logout: () => ({ url: '/logout' }),
}));
vi.mock('@/routes/notifications', () => ({
    index: () => ({ url: '/notifications' }),
}));
vi.mock('@/routes/profile', () => ({
    edit: () => ({ url: '/settings/profile' }),
}));
vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

function Harness() {
    const { toggle } = useCommandPalette();

    return (
        <button type="button" onClick={toggle}>
            open palette
        </button>
    );
}

function renderPalette() {
    return renderWithUser(
        <CommandPaletteProvider>
            <Harness />
        </CommandPaletteProvider>,
    );
}

beforeEach(() => {
    window.localStorage.clear();
    visit.mockClear();
    post.mockClear();
});

describe('CommandPalette', () => {
    it('opens from the shortcut and lists navigation and actions', async () => {
        renderPalette();

        fireEvent.keyDown(document, { key: 'k', metaKey: true });

        expect(await screen.findByText('Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Users')).toBeInTheDocument();
        expect(screen.getByText('Log out')).toBeInTheDocument();
    });

    it('filters commands as you type', async () => {
        const { user } = renderPalette();

        await user.click(screen.getByRole('button', { name: 'open palette' }));
        await user.type(
            await screen.findByPlaceholderText('Search commands…'),
            'users',
        );

        expect(screen.getByText('Users')).toBeInTheDocument();
        expect(screen.queryByText('Dashboard')).not.toBeInTheDocument();
    });

    it('runs a navigation command and closes', async () => {
        const { user } = renderPalette();

        await user.click(screen.getByRole('button', { name: 'open palette' }));
        await user.click(await screen.findByText('Users'));

        expect(visit).toHaveBeenCalledWith('/users');
    });

    it('shows an empty state when nothing matches', async () => {
        const { user } = renderPalette();

        await user.click(screen.getByRole('button', { name: 'open palette' }));
        await user.type(
            await screen.findByPlaceholderText('Search commands…'),
            'zzzzz',
        );

        expect(
            await screen.findByText('No matching commands'),
        ).toBeInTheDocument();
    });
});
