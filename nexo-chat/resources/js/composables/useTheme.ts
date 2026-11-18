import { ref } from 'vue';

type Theme = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'nexo-theme';
const theme = ref<Theme>(read());

function read(): Theme {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return v === 'light' || v === 'dark' ? v : 'system';
    } catch {
        return 'system';
    }
}

function apply(t: Theme): void {
    const dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

export function useTheme() {
    function setTheme(t: Theme): void {
        theme.value = t;
        try {
            if (t === 'system') localStorage.removeItem(STORAGE_KEY);
            else localStorage.setItem(STORAGE_KEY, t);
        } catch {
            /* armazenamento indisponível */
        }
        apply(t);
    }

    function toggle(): void {
        const isDark = document.documentElement.classList.contains('dark');
        setTheme(isDark ? 'light' : 'dark');
    }

    return { theme, setTheme, toggle };
}
