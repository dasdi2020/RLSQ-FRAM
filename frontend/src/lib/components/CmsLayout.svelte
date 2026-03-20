<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { getI18n } from '$lib/stores/i18n.svelte.js';
    import { getProject } from '$lib/stores/project.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';

    let { children, projectSlug = '' } = $props();
    const auth = getAuth();
    const i18n = getI18n();
    const project = getProject();
    let t = $derived(i18n.t);

    let currentPath = $state(window.location.hash.replace('#', '') || '');
    let showLangMenu = $state(false);
    let showUserMenu = $state(false);

    // Dark/Light
    let darkMode = $state(localStorage.getItem('theme') !== 'light');
    $effect(() => { document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light'); localStorage.setItem('theme', darkMode ? 'dark' : 'light'); });

    // Auth + load project
    $effect(() => { if (!auth.isAuthenticated) push('/login'); });
    $effect(() => { if (projectSlug) project.load(projectSlug); });
    $effect(() => { const h = () => { currentPath = window.location.hash.replace('#', ''); }; window.addEventListener('hashchange', h); return () => window.removeEventListener('hashchange', h); });

    function isActive(path) { return currentPath.includes(path); }
    function closeMenus() { showLangMenu = false; showUserMenu = false; }

    const base = $derived(`/p/${projectSlug}`);

    const cmsNav = [
        { path: '', icon: '📊', label: 'Dashboard', exact: true },
        { path: '/pages', icon: '📄', label: 'Pages' },
        { path: '/menus', icon: '☰', label: 'Menus' },
        { path: '/database', icon: '🗄️', label: 'Base de données' },
        { path: '/erd', icon: '🔀', label: 'ERD' },
        { path: '/forms', icon: '📝', label: 'Formulaires' },
        { path: '/plugins', icon: '🧩', label: 'Plugins' },
        { path: '/media', icon: '🖼️', label: 'Médias' },
    ];

    const cmsSystem = [
        { path: '/code', icon: '💻', label: 'Éditeur de code' },
        { path: '/login-design', icon: '🔐', label: 'Page de login' },
        { path: '/settings', icon: '⚙️', label: 'Paramètres' },
        { path: '/preview', icon: '👁️', label: 'Preview' },
        { path: '/deploy', icon: '🚀', label: 'Déployer' },
    ];

    function navIsActive(navPath) {
        const full = base + navPath;
        if (navPath === '') return currentPath === base || currentPath === base + '/';
        return currentPath.startsWith(full);
    }
</script>

<!-- svelte-ignore a11y_click_events_have_key_events -->
<!-- svelte-ignore a11y_no_static_element_interactions -->
{#if auth.isAuthenticated}
<div class="min-h-screen flex" onclick={closeMenus}>
    <aside class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <!-- Project header -->
        <div class="p-3 border-b border-[var(--color-border)]">
            <a href="#/dashboard" class="flex items-center gap-2 px-2 py-1.5 rounded-[var(--radius)] hover:bg-[var(--color-border)] text-xs text-[var(--color-muted)] mb-2">
                ← Retour aux projets
            </a>
            <div class="px-2 flex items-center justify-between">
                <div>
                    <h1 class="text-sm font-bold truncate">{project.name || projectSlug}</h1>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium">
                            {project.type === 'website' ? '🌐 Site' : '💻 App'}
                        </span>
                        <span class="text-[10px] text-[var(--color-muted)] font-mono">{projectSlug}</span>
                    </div>
                </div>
                <div class="flex gap-0.5">
                    <div class="relative">
                        <button class="w-7 h-7 rounded flex items-center justify-center text-xs cursor-pointer hover:bg-[var(--color-border)]"
                            onclick={(e) => { e.stopPropagation(); showLangMenu = !showLangMenu; }}>
                            {i18n.lang === 'fr' ? '🇫🇷' : i18n.lang === 'en' ? '🇬🇧' : '🇪🇸'}
                        </button>
                        {#if showLangMenu}
                            <div class="absolute top-full right-0 mt-1 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] shadow-lg z-50 overflow-hidden">
                                {#each i18n.availableLanguages as l}
                                    <button class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-[var(--color-border)] cursor-pointer w-full text-left
                                        {i18n.lang === l.code ? 'text-[var(--color-primary)]' : ''}"
                                        onclick={(e) => { e.stopPropagation(); i18n.lang = l.code; showLangMenu = false; }}>
                                        {l.flag} {l.label}
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                    <button class="w-7 h-7 rounded flex items-center justify-center cursor-pointer hover:bg-[var(--color-border)]" onclick={() => darkMode = !darkMode}>
                        {darkMode ? '☀️' : '🌙'}
                    </button>
                </div>
            </div>
        </div>

        <!-- CMS Nav -->
        <nav class="flex-1 p-2 space-y-0.5 overflow-y-auto">
            <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1 mt-1">Contenu</p>
            {#each cmsNav as item}
                <a href="#{base}{item.path}" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                    {navIsActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                    <span class="w-5 text-center text-base">{item.icon}</span> {item.label}
                </a>
            {/each}

            <div class="pt-2 mt-2 border-t border-[var(--color-border)]">
                <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">Développement</p>
                {#each cmsSystem as item}
                    <a href="#{base}{item.path}" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm transition-colors
                        {navIsActive(item.path) ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                        <span class="w-5 text-center text-base">{item.icon}</span> {item.label}
                    </a>
                {/each}
            </div>

            <div class="pt-2 mt-2 border-t border-[var(--color-border)]">
                <p class="px-3 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">API</p>
                <a href="/api/docs" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">📘</span> Swagger UI
                </a>
                <a href="/graphiql" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">⚛️</span> GraphQL
                </a>
            </div>
        </nav>

        <!-- User -->
        <div class="p-2 border-t border-[var(--color-border)] relative">
            <button class="flex items-center gap-3 px-3 py-2 w-full rounded-[var(--radius)] hover:bg-[var(--color-border)] cursor-pointer text-left"
                onclick={(e) => { e.stopPropagation(); showUserMenu = !showUserMenu; }}>
                <div class="w-7 h-7 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-[10px] font-bold">
                    {auth.user?.first_name?.[0]}{auth.user?.last_name?.[0]}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium truncate">{auth.user?.first_name} {auth.user?.last_name}</p>
                    <p class="text-[10px] text-[var(--color-muted)] truncate">{auth.user?.email}</p>
                </div>
            </button>
            {#if showUserMenu}
                <div class="absolute bottom-full left-2 right-2 mb-1 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] shadow-lg overflow-hidden z-50">
                    <a href="#/profile" class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-[var(--color-border)] cursor-pointer" onclick={() => showUserMenu = false}>👤 {t.profile}</a>
                    <button class="flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-[var(--color-border)] cursor-pointer w-full text-left text-[var(--color-destructive)]"
                        onclick={() => { auth.logout(); push('/login'); }}>🚪 {t.logout}</button>
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
    }
    :global([data-theme="dark"]) {
        --color-primary: #ff3e00; --color-secondary: #1a1a2e; --color-accent: #6cb2eb;
        --color-background: #0f0f1a; --color-foreground: #e0e0e0; --color-card: #1a1a2e;
        --color-border: #2a2a3e; --color-muted: #888; --color-muted-foreground: #666;
    }
</style>
