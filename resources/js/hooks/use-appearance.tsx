import { useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

export function initializeTheme() {
    document.documentElement.classList.add('dark');
}

export function useAppearance() {
    const [appearance] = useState<Appearance>('dark');

    const updateAppearance = (_mode: Appearance) => {
        document.documentElement.classList.add('dark');
    };

    return { appearance, updateAppearance };
}
