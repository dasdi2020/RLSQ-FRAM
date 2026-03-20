<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';

    let { children } = $props();
    const auth = getAuth();
    let currentPath = $state(window.location.hash.replace('#', '') || '/dashboard');

    // Redirect si pas authentifié
    $effect(() => {
        if (!auth.isAuthenticated) {
            push('/login');
        }
    });

    // Track hash changes for active state
    $effect(() => {
        const onHash = () => { currentPath = window.location.hash.replace('#', '') || '/dashboard'; };
        window.addEventListener('hashchange', onHash);
        return () => window.removeEventListener('hashchange', onHash);
    });

    function handleLogout() {
        auth.logout();
        push('/login');
    }

    const navItems = [
        { path: '/dashboard', label: 'Dashboard', icon: '📊' },
        { path: '/database', label: 'Base de données', icon: '🗄️' },
        { path: '/plugins', label: 'Modules', icon: '🧩' },
        { path: '/forms', label: 'Formulaires', icon: '📝' },
        { path: '/pages', label: 'Pages', icon: '📄' },
        { path: '/embeds', label: 'Embeds', icon: '🔗' },
    ];

    const systemItems = [
        { path: '/audit', label: 'Audit', icon: '📋' },
        { path: '/notifications', label: 'Notifications', icon: '🔔' },
        { path: '/settings', label: 'Paramètres', icon: '⚙️' },
    ];

    function isActive(path) {
        if (path === '/dashboard') return currentPath === '/dashboard';
        return currentPath.startsWith(path);
    }
</script>

{#if auth.isAuthenticated}
<div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <div class="p-5 border-b border-[var(--color-border)]">
            <a href="#/dashboard" class="block">
                <h1 class="text-xl font-bold">
                    <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
                </h1>
                <p class="text-xs text-[var(--color-muted)] mt-1">Plateforme d'administration</p>
            </a>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            {#each navItems as item}
                <a href="#{item.path}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-sm transition-colors
                    {isActive(item.path)
                        ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium'
                        : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                    <span class="w-5 text-center">{item.icon}</span>
                    {item.label}
                </a>
            {/each}

            <div class="pt-3 mt-3 border-t border-[var(--color-border)]">
                <p class="px-3 text-xs text-[var(--color-muted-foreground)] uppercase tracking-wide mb-2">Système</p>
                {#each systemItems as item}
                    <a href="#{item.path}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-sm transition-colors
                        {isActive(item.path)
                            ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium'
                            : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]'}">
                        <span class="w-5 text-center">{item.icon}</span>
                        {item.label}
                    </a>
                {/each}
            </div>

            <div class="pt-3 mt-3 border-t border-[var(--color-border)]">
                <p class="px-3 text-xs text-[var(--color-muted-foreground)] uppercase tracking-wide mb-2">Outils</p>
                <a href="/api/docs" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">📘</span> Swagger UI
                </a>
                <a href="/graphiql" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)]">
                    <span class="w-5 text-center">⚛️</span> GraphiQL
                </a>
            </div>
        </nav>

        <!-- User footer -->
        <div class="p-3 border-t border-[var(--color-border)]">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-bold">
                    {auth.user?.first_name?.[0] || '?'}{auth.user?.last_name?.[0] || ''}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{auth.user?.first_name} {auth.user?.last_name}</p>
                    <p class="text-xs text-[var(--color-muted)] truncate">{auth.user?.email}</p>
                </div>
            </div>
            <Button variant="ghost" size="sm" class="w-full mt-1" onclick={handleLogout}>
                Déconnexion
            </Button>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 overflow-auto flex flex-col">
        {@render children()}
    </main>
</div>
{/if}
