<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { getI18n } from '$lib/stores/i18n.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';

    let { children } = $props();
    const auth = getAuth();
    const i18n = getI18n();
    let currentPath = $state(window.location.hash.replace('#', '') || '/dashboard');

    // Dark/Light mode
    let darkMode = $state(localStorage.getItem('theme') !== 'light');
    $effect(() => {
        document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    });

    // Language menu
    let showLangMenu = $state(false);
    let showUserMenu = $state(false);

    // Auth guard
    $effect(() => { if (!auth.isAuthenticated) push('/login'); });

    // Track hash
    $effect(() => {
        const onHash = () => { currentPath = window.location.hash.replace('#', '') || '/dashboard'; };
        window.addEventListener('hashchange', onHash);
        return () => window.removeEventListener('hashchange', onHash);
    });

    // Close menus on outside click
    function closeMenus() { showLangMenu = false; showUserMenu = false; }

    function handleLogout() { auth.logout(); push('/login'); }
    function isActive(path) {
        if (path === '/dashboard') return currentPath === '/dashboard';
        return currentPath.startsWith(path);
    }

    function cycleLang() {
        const langs = ['fr', 'en', 'es'];
        const idx = langs.indexOf(i18n.lang);
        i18n.lang = langs[(idx + 1) % langs.length];
    }

    // Computed
    let t = $derived(i18n.t);

    const navItems = [
        { path: '/dashboard', icon: '📊', key: 'dashboard' },
        { path: '/database', icon: '🗄️', key: 'database' },
        { path: '/erd', icon: '🔀', key: 'erd' },
        { path: '/plugins', icon: '🧩', key: 'modules' },
        { path: '/forms', icon: '📝', key: 'forms' },
        { path: '/pages', icon: '📄', key: 'pages' },
        { path: '/embeds', icon: '🔗', key: 'embeds' },
    ];
    const systemItems = [
        { path: '/users', icon: '👥', key: 'users' },
        { path: '/audit', icon: '📋', key: 'audit' },
        { path: '/notifications', icon: '🔔', key: 'notifications' },
        { path: '/settings', icon: '⚙️', key: 'settings' },
    ];
</script>

<!-- svelte-ignore a11y_click_events_have_key_events -->
<!-- svelte-ignore a11y_no_static_element_interactions -->
{#if auth.isAuthenticated}
<div class="min-h-screen flex" onclick={closeMenus}>
    <aside class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-[var(--color-border)] flex items-center justify-between">
            <a href="#/dashboard" class="block">
                <h1 class="text-lg font-bold"><span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span></h1>
                <p class="text-[10px] text-[var(--color-muted)] mt-0.5">{t.platform}</p>
            </a>
            <div class="flex items-center gap-0.5">
                <!-- Language selector -->
                <div class="relative">
                    <button class="w-8 h-8 rounded flex items-center justify-center text-xs cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)] font-bold"
                        onclick={(e) => { e.stopPropagation(); showLangMenu = !showLangMenu; showUserMenu = false; }}>
                        {i18n.lang === 'fr' ? '🇫🇷' : i18n.lang === 'en' ? '🇬🇧' : '🇪🇸'}
                    </button>
                    {#if showLangMenu}
                        <div class="absolute top-full right-0 mt-1 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] shadow-lg z-50 overflow-hidden">
                            {#each i18n.availableLanguages as l}
                                <button class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-[var(--color-border)] cursor-pointer w-full text-left
                                    {i18n.lang === l.code ? 'text-[var(--color-primary)] font-medium' : 'text-[var(--color-foreground)]'}"
                                    onclick={(e) => { e.stopPropagation(); i18n.lang = l.code; showLangMenu = false; }}>
                                    <span>{l.flag}</span> {l.label}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
                <!-- Dark/Light -->
                <button class="w-8 h-8 rounded flex items-center justify-center cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={() => darkMode = !darkMode}>
                    {darkMode ? '☀️' : '🌙'}
                </button>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 p-2 space-y-0.5 overflow-y-auto">
            {#each navItems as item}
                <a href="#{item.path}" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                    {isActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                    <span class="w-5 text-center text-base">{item.icon}</span> {t[item.key]}
                </a>
            {/each}

            <div class="pt-2 mt-2 border-t border-[var(--color-border)]">
                <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">{t.system}</p>
                {#each systemItems as item}
                    <a href="#{item.path}" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                        {isActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                        <span class="w-5 text-center text-base">{item.icon}</span> {t[item.key]}
                    </a>
                {/each}
            </div>

            <div class="pt-2 mt-2 border-t border-[var(--color-border)]">
                <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">{t.tools}</p>
                <a href="/api/docs" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">📘</span> Swagger UI
                </a>
                <a href="/graphiql" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">⚛️</span> GraphiQL
                </a>
            </div>
        </nav>

        <!-- User -->
        <div class="p-2 border-t border-[var(--color-border)] relative">
            <button class="flex items-center gap-3 px-3 py-2.5 w-full rounded-[var(--radius)] hover:bg-[var(--color-border)] cursor-pointer text-left"
                onclick={(e) => { e.stopPropagation(); showUserMenu = !showUserMenu; showLangMenu = false; }}>
                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-xs font-bold">
                    {auth.user?.first_name?.[0] || '?'}{auth.user?.last_name?.[0] || ''}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{auth.user?.first_name} {auth.user?.last_name}</p>
                    <p class="text-[11px] text-[var(--color-muted)] truncate">{auth.user?.email}</p>
                </div>
            </button>

            {#if showUserMenu}
                <div class="absolute bottom-full left-2 right-2 mb-1 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] shadow-lg overflow-hidden z-50">
                    <a href="#/profile" class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-[var(--color-border)] cursor-pointer" onclick={() => showUserMenu = false}>
                        <span>👤</span> {t.profile}
                    </a>
                    <a href="#/users" class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-[var(--color-border)] cursor-pointer" onclick={() => showUserMenu = false}>
                        <span>👥</span> {t.users}
                    </a>
                    <button class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-[var(--color-border)] cursor-pointer w-full text-left text-[var(--color-destructive)]" onclick={handleLogout}>
                        <span>🚪</span> {t.logout}
                    </button>
                </div>
            {/if}
        </div>
    </aside>

    <main class="flex-1 overflow-auto flex flex-col">
        {@render children()}
    </main>
</div>
{/if}

<style>
    :global([data-theme="light"]) {
        --color-primary: #e53e00; --color-secondary: #f5f5f7; --color-accent: #2563eb;
        --color-background: #ffffff; --color-foreground: #1a1a2e; --color-card: #ffffff;
        --color-border: #e2e8f0; --color-muted: #64748b; --color-muted-foreground: #94a3b8;
        --color-destructive: #dc2626; --color-success: #16a34a; --color-warning: #d97706;
    }
    :global([data-theme="dark"]) {
        --color-primary: #ff3e00; --color-secondary: #1a1a2e; --color-accent: #6cb2eb;
        --color-background: #0f0f1a; --color-foreground: #e0e0e0; --color-card: #1a1a2e;
        --color-border: #2a2a3e; --color-muted: #888; --color-muted-foreground: #666;
        --color-destructive: #e74c3c; --color-success: #2ecc71; --color-warning: #f39c12;
    }
</style>
