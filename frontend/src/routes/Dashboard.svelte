<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import { get } from '$lib/api/client.js';

    const auth = getAuth();
    let stats = $state({ tenants: 0 });

    async function loadStats() {
        try { const r = await get('/api/admin/tenants?per_page=1'); stats.tenants = r.total ?? 0; } catch {}
    }

    $effect(() => { if (auth.isAuthenticated) loadStats(); });
</script>

<AppLayout>
    <header class="h-14 border-b border-[var(--color-border)] flex items-center px-6">
        <h2 class="text-lg font-semibold">Dashboard</h2>
    </header>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <Card class="p-5">
                <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Organisations</p>
                <p class="text-3xl font-bold mt-2">{stats.tenants}</p>
            </Card>
            <Card class="p-5">
                <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide">Modules disponibles</p>
                <p class="text-3xl font-bold mt-2 text-[var(--color-success)]">5</p>
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
            <p class="text-[var(--color-muted)] mb-4">La plateforme est prête. Commencez par :</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="#/database" class="p-4 rounded-[var(--radius)] border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors block">
                    <span class="text-2xl">🗄️</span>
                    <h4 class="font-medium mt-2">Base de données</h4>
                    <p class="text-xs text-[var(--color-muted)] mt-1">Créez vos tables visuellement</p>
                </a>
                <a href="#/plugins" class="p-4 rounded-[var(--radius)] border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors block">
                    <span class="text-2xl">🧩</span>
                    <h4 class="font-medium mt-2">Modules</h4>
                    <p class="text-xs text-[var(--color-muted)] mt-1">Installez formations, paiements...</p>
                </a>
                <a href="#/forms" class="p-4 rounded-[var(--radius)] border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors block">
                    <span class="text-2xl">📝</span>
                    <h4 class="font-medium mt-2">Formulaires</h4>
                    <p class="text-xs text-[var(--color-muted)] mt-1">Construisez des formulaires</p>
                </a>
            </div>
        </Card>
    </div>
</AppLayout>
