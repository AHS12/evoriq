import { describe, expect, it, vi } from 'vitest';
import { SettingField } from '@/components/setting/setting-field';
import type { SettingField as SettingFieldType } from '@/types';
import { renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

function booleanField(value: boolean): SettingFieldType {
    return {
        key: 'backup_enabled',
        label: 'Enable backups',
        description: 'Take scheduled backups of files and the database',
        type: 'boolean',
        group: 'backups',
        value,
        is_secret: false,
        has_value: true,
    };
}

describe('SettingField (boolean)', () => {
    it('renders a switch reflecting the value and a hidden form value', () => {
        const { container } = renderWithUser(
            <SettingField field={booleanField(true)} />,
        );

        expect(screen.getByRole('switch')).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(
            container.querySelector(
                'input[type="hidden"][name="backup_enabled"]',
            ),
        ).toHaveValue('1');
    });

    it('flips the submitted value and status when toggled', async () => {
        const { user, container } = renderWithUser(
            <SettingField field={booleanField(true)} />,
        );

        await user.click(screen.getByRole('switch'));

        expect(screen.getByText('Disabled')).toBeInTheDocument();
        expect(
            container.querySelector(
                'input[type="hidden"][name="backup_enabled"]',
            ),
        ).toHaveValue('0');
    });
});
