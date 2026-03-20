<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    const auth = getAuth();

    // Redirect si pas authentifié
    $effect(() => {
        if (!auth.isAuthenticated) {
            push('/login');
        }
    });

    function handleLogout() {
        auth.logout();
        push('/login');
    }
</script>

{#if auth.isAuthenticated}
<div class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <div class="p-5 border-b border-[var(--color-border)]">
            <h1 class="text-xl font-bold">
                <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
            </h1>
            <p class="text-xs text-[var(--color-muted)] mt-1">Plateforme d'administration</p>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            <a href="#/dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <a href="#/tenants" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                Organisations
            </a>
            <a href="#/database" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                Base de données
            </a>
            <a href="#/plugins" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>
                Modules
            </a>
            <a href="#/forms" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <span class="w-4 text-center">📝</span> Formulaires
            </a>
            <a href="#/pages" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <span class="w-4 text-center">📄</span> Pages
            </a>
            <a href="#/embeds" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                <span class="w-4 text-center">🔗</span> Embeds
            </a>

            <div class="pt-3 mt-3 border-t border-[var(--color-border)]">
                <p class="px-3 text-xs text-[var(--color-muted-foreground)] uppercase tracking-wide mb-2">Système</p>
                <a href="#/audit" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                    <span class="w-4 text-center">📋</span> Audit
                </a>
                <a href="#/notifications" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                    <span class="w-4 text-center">🔔</span> Notifications
                </a>
                <a href="#/settings" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                    <span class="w-4 text-center">⚙️</span> Paramètres
                </a>
            </div>
        </nav>

        <div class="p-3 border-t border-[var(--color-border)]">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-bold">
                    {auth.user?.first_name?.[0]}{auth.user?.last_name?.[0]}
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

    <!-- Main -->
    <main class="flex-1 overflow-auto">
        <header class="h-14 border-b border-[var(--color-border)] flex items-center px-6">
            <h2 class="text-lg font-semibold">Dashboard</h2>
        </header>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card class="p-5">
                    <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Organisations</p>
                    <p class="text-3xl font-bold mt-2">0</p>
                </Card>
                <Card class="p-5">
                    <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Modules actifs</p>
                    <p class="text-3xl font-bold mt-2 text-[var(--color-success)]">0</p>
                </Card>
                <Card class="p-5">
                    <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Utilisateurs</p>
                    <p class="text-3xl font-bold mt-2 text-[var(--color-accent)]">1</p>
                </Card>
                <Card class="p-5">
                    <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Environnement</p>
                    <p class="text-3xl font-bold mt-2 text-[var(--color-warning)]">dev</p>
                </Card>
            </div>

            <Card class="p-6">
                <h3 class="text-lg font-semibold mb-4">Bienvenue, {auth.user?.first_name} !</h3>
                <p class="text-[var(--color-muted)]">
                    La plateforme RLSQ-FRAM est prête. Commencez par créer une organisation ou activer des modules.
                </p>
            </Card>
        </div>
    </main>
</div>
{/if}
