const storageKey = 'edvora.theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function readChoice() {
    try {
        const value = localStorage.getItem(storageKey);
        return ['light', 'dark', 'system'].includes(value) ? value : 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(choice) {
    document.documentElement.classList.toggle(
        'dark',
        choice === 'dark' || (choice === 'system' && media.matches),
    );

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        const active = button.dataset.themeOption === choice;
        button.setAttribute('aria-pressed', String(active));
        button.classList.toggle('bg-blue-600', active);
        button.classList.toggle('text-white', active);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(readChoice());

    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.dataset.themeOption;
            if (!['light', 'dark', 'system'].includes(choice)) return;
            try { localStorage.setItem(storageKey, choice); } catch {}
            applyTheme(choice);
        });
    });
});

media.addEventListener('change', () => {
    if (readChoice() === 'system') applyTheme('system');
});

window.addEventListener('storage', (event) => {
    if (event.key === storageKey) applyTheme(readChoice());
});
