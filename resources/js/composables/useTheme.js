import { onMounted, ref } from 'vue';

const THEME_STORAGE_KEY = 'chat-theme';

const isDark = ref(
    typeof document !== 'undefined'
        ? document.documentElement.classList.contains('dark')
        : false,
);

function applyTheme(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem(THEME_STORAGE_KEY, dark ? 'dark' : 'light');
}

function readStoredTheme() {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);

    if (stored === 'dark') {
        return true;
    }

    if (stored === 'light') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

let themeInitialized = false;

function ensureThemeInitialized() {
    if (themeInitialized) {
        return;
    }

    themeInitialized = true;
    applyTheme(readStoredTheme());
}

export function useTheme() {
    onMounted(ensureThemeInitialized);

    function toggleTheme() {
        applyTheme(!isDark.value);
    }

    function setTheme(dark) {
        applyTheme(dark);
    }

    return {
        isDark,
        toggleTheme,
        setTheme,
    };
}
