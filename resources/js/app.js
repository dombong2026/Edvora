const storageKey = 'edvora.theme';
const sidebarKey = 'edvora.sidebar-collapsed';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function safeGet(key) {
    try { return localStorage.getItem(key); } catch { return null; }
}
function safeSet(key, value) {
    try { localStorage.setItem(key, value); } catch {}
}
function readChoice() {
    const value = safeGet(storageKey);
    return ['light', 'dark', 'system'].includes(value) ? value : 'system';
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
function syncSidebarButton() {
    const mobile = window.matchMedia('(max-width: 1023px)').matches;
    const opened = document.documentElement.dataset.sidebarOpen === 'true';
    const collapsed = document.documentElement.dataset.sidebarCollapsed === 'true';
    document.querySelectorAll('[data-sidebar-toggle]').forEach(button => {
        button.setAttribute('aria-expanded', String(mobile ? opened : !collapsed));
        button.setAttribute('aria-label', mobile
            ? (opened ? 'Hide navigation' : 'Show navigation')
            : (collapsed ? 'Show navigation' : 'Hide navigation'));
    });
}
function closeMobileNav() {
    document.documentElement.dataset.sidebarOpen = 'false';
    syncSidebarButton();
}
document.addEventListener('DOMContentLoaded', () => {
    applyTheme(readChoice());
    document.documentElement.dataset.sidebarCollapsed = safeGet(sidebarKey) === 'true' ? 'true' : 'false';
    document.documentElement.dataset.sidebarOpen = 'false';
    syncSidebarButton();
    document.querySelectorAll('[data-theme-option]').forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.dataset.themeOption;
            if (!['light', 'dark', 'system'].includes(choice)) return;
            safeSet(storageKey, choice);
            applyTheme(choice);
        });
    });
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 1023px)').matches) {
                document.documentElement.dataset.sidebarOpen =
                    document.documentElement.dataset.sidebarOpen === 'true' ? 'false' : 'true';
            } else {
                const collapsed = document.documentElement.dataset.sidebarCollapsed !== 'true';
                document.documentElement.dataset.sidebarCollapsed = String(collapsed);
                safeSet(sidebarKey, String(collapsed));
            }
            syncSidebarButton();
        });
    });
    document.querySelectorAll('[data-sidebar-overlay]').forEach((button) => {
        button.addEventListener('click', closeMobileNav);
    });
    document.querySelectorAll('#edvora-sidebar a').forEach((link) => {
        link.addEventListener('click', closeMobileNav);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileNav();
            document.querySelectorAll('[data-account-menu][open]').forEach(el => el.removeAttribute('open'));
        }
    });
    document.querySelectorAll('[data-select-member]').forEach((link) => {
        link.addEventListener('click', () => {
            const selector = document.getElementById('assign-user');
            if (!selector) return;
            selector.value = link.dataset.selectMember;
        });
    });
    const search = document.querySelector('[data-workspace-search]');
    if (search) {
        const cards = [...document.querySelectorAll('[data-workspace-card]')];
        const empty = document.querySelector('[data-workspace-empty]');
        search.addEventListener('input', () => {
            const term = search.value.trim().toLocaleLowerCase();
            let visible = 0;
            cards.forEach((card) => {
                const matches = card.dataset.workspaceName.includes(term);
                card.hidden = !matches;
                if (matches) visible++;
            });
            if (empty) empty.hidden = visible !== 0 || cards.length === 0;
        });
    }
    window.matchMedia('(max-width: 1023px)').addEventListener('change', () => {
        closeMobileNav();
    });
});
media.addEventListener('change', () => {
    if (readChoice() === 'system') applyTheme('system');
});
window.addEventListener('storage', (event) => {
    if (event.key === storageKey) applyTheme(readChoice());
    if (event.key === sidebarKey) {
        document.documentElement.dataset.sidebarCollapsed = safeGet(sidebarKey) === 'true' ? 'true' : 'false';
        syncSidebarButton();
    }
});
