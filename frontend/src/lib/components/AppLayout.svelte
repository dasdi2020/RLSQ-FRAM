<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';

    let { children } = $props();
    const auth = getAuth();
    let currentPath = $state(window.location.hash.replace('#', '') || '/dashboard');

    // Dark/Light mode
    let darkMode = $state(localStorage.getItem('theme') !== 'light');
    $effect(() => {
        document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    });

    // Language
    let lang = $state(localStorage.getItem('lang') || 'fr');
    $effect(() => { localStorage.setItem('lang', lang); });

    const t = $derived(lang === 'en' ? {
        dashboard: 'Dashboard', database: 'Database', modules: 'Modules', forms: 'Forms', pages: 'Pages',
        embeds: 'Embeds', audit: 'Audit', notifications: 'Notifications', settings: 'Settings',
        system: 'System', tools: 'Tools', logout: 'Logout', profile: 'Profile', users: 'Users',
        platform: 'Admin Platform', erd: 'ERD Editor',
    } : {
        dashboard: 'Dashboard', database: 'Base de données', modules: 'Modules', forms: 'Formulaires', pages: 'Pages',
        embeds: 'Embeds', audit: 'Audit', notifications: 'Notifications', settings: 'Paramètres',
        system: 'Système', tools: 'Outils', logout: 'Déconnexion', profile: 'Profil', users: 'Utilisateurs',
        platform: 'Plateforme d\'administration', erd: 'Éditeur ERD',
    });

    // Auth guard
    $effect(() => { if (!auth.isAuthenticated) push('/login'); });

    // Track hash
    $effect(() => {
        const onHash = () => { currentPath = window.location.hash.replace('#', '') || '/dashboard'; };
        window.addEventListener('hashchange', onHash);
        return () => window.removeEventListener('hashchange', onHash);
    });

    function handleLogout() { auth.logout(); push('/login'); }
    function isActive(path) {
        if (path === '/dashboard') return currentPath === '/dashboard';
        return currentPath.startsWith(path);
    }

    // User menu
    let showUserMenu = $state(false);

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

{#if auth.isAuthenticated}
<div class="min-h-screen flex layout-root" class:light-mode={!darkMode}>
    <!-- Sidebar -->
    <aside class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <div class="p-4 border-b border-[var(--color-border)] flex items-center justify-between">
            <a href="#/dashboard" class="block">
                <h1 class="text-lg font-bold">
                    <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
                </h1>
                <p class="text-[10px] text-[var(--color-muted)] mt-0.5">{t.platform}</p>
            </a>
            <div class="flex items-center gap-1">
                <!-- Language toggle -->
                <button class="w-7 h-7 rounded flex items-center justify-center text-xs font-bold cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={() => lang = lang === 'fr' ? 'en' : 'fr'} title="Language">
                    {lang.toUpperCase()}
                </button>
                <!-- Dark/Light toggle -->
                <button class="w-7 h-7 rounded flex items-center justify-center cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={() => darkMode = !darkMode} title={darkMode ? 'Light mode' : 'Dark mode'}>
                    {darkMode ? '☀️' : '🌙'}
                </button>
            </div>
        </div>

        <nav class="flex-1 p-2 space-y-0.5 overflow-y-auto">
            {#each navItems as item}
                <a href="#{item.path}"
                   class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                    {isActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                    <span class="w-5 text-center text-base">{item.icon}</span>
                    {t[item.key]}
                </a>
            {/each}

            <div class="pt-2 mt-2 border-t border-[var(--color-border)]">
                <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">{t.system}</p>
                {#each systemItems as item}
                    <a href="#{item.path}" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                        {isActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                        <span class="w-5 text-center text-base">{item.icon}</span>
                        {t[item.key]}
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

        <!-- User footer -->
        <div class="p-2 border-t border-[var(--color-border)] relative">
            <button class="flex items-center gap-3 px-3 py-2.5 w-full rounded-[var(--radius)] hover:bg-[var(--color-border)] cursor-pointer text-left"
                onclick={() => showUserMenu = !showUserMenu}>
                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-xs font-bold">
                    {auth.user?.first_name?.[0] || '?'}{auth.user?.last_name?.[0] || ''}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{auth.user?.first_name} {auth.user?.last_name}</p>
                    <p class="text-[11px] text-[var(--color-muted)] truncate">{auth.user?.email}</p>
                </div>
                <span class="text-[var(--color-muted)] text-xs">{showUserMenu ? '▼' : '▲'}</span>
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

    <!-- Main content -->
    <main class="flex-1 overflow-auto flex flex-col">
        {@render children()}
    </main>
</div>
{/if}

<style>
    :global([data-theme="light"]) {
        --color-primary: #e53e00;
        --color-secondary: #f5f5f7;
        --color-accent: #2563eb;
        --color-background: #ffffff;
        --color-foreground: #1a1a2e;
        --color-card: #ffffff;
        --color-border: #e2e8f0;
        --color-muted: #64748b;
        --color-muted-foreground: #94a3b8;
        --color-destructive: #dc2626;
        --color-success: #16a34a;
        --color-warning: #d97706;
    }
    :global([data-theme="dark"]) {
        --color-primary: #ff3e00;
        --color-secondary: #1a1a2e;
        --color-accent: #6cb2eb;
        --color-background: #0f0f1a;
        --color-foreground: #e0e0e0;
        --color-card: #1a1a2e;
        --color-border: #2a2a3e;
        --color-muted: #888;
        --color-muted-foreground: #666;
        --color-destructive: #e74c3c;
        --color-success: #2ecc71;
        --color-warning: #f39c12;
    }
</style>
