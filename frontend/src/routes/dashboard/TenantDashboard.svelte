<script>
    import { get } from '$lib/api/client.js';
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { push } from 'svelte-spa-router';
    import Button from '$lib/components/ui/Button.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import WidgetRenderer from '$lib/components/widgets/WidgetRenderer.svelte';

    let { params = {} } = $props();
    let tenantSlug = $derived(params.slug || 'federation-quebec');

    const auth = getAuth();

    let dashboard = $state(null);
    let widgetData = $state({});
    let userInfo = $state(null);
    let loading = $state(true);
    let activePlugins = $state([]);

    const dashboardLabels = {
        federation: { title: 'Dashboard Organisation', icon: '🏛️', color: 'var(--color-primary)' },
        club: { title: 'Dashboard Club', icon: '🏢', color: 'var(--color-accent)' },
        member: { title: 'Espace Membre', icon: '👤', color: 'var(--color-success)' },
    };

    async function loadDashboard() {
        loading = true;
        try {
            // Charger le profil tenant
            const roleRes = await get(`/api/t/${tenantSlug}/auth/role`);
            userInfo = roleRes;

            // Charger le dashboard par défaut
            const dashRes = await get(`/api/t/${tenantSlug}/dashboards/my`);
            dashboard = dashRes.data;

            // Charger les données de chaque widget
            if (dashboard?.widgets) {
                for (const w of dashboard.widgets) {
                    try {
                        const dataRes = await get(`/api/t/${tenantSlug}/dashboards/data/${w.id}`);
                        widgetData[w.id] = dataRes.data;
                    } catch { widgetData[w.id] = null; }
                }
            }

            // Charger les plugins actifs
            try {
                const plugRes = await get(`/api/t/${tenantSlug}/plugins`);
                activePlugins = (plugRes.data || []).filter(p => p.is_active);
            } catch {}
        } catch (e) { console.error(e); }
        loading = false;
    }

    $effect(() => {
        if (auth.isAuthenticated) {
            loadDashboard();
        } else {
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
    <aside class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <div class="p-5 border-b border-[var(--color-border)]">
            <h1 class="text-xl font-bold">
                <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
            </h1>
            <p class="text-xs text-[var(--color-muted)] mt-1 truncate">{tenantSlug}</p>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="#/t/{tenantSlug}" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] bg-[var(--color-primary)]/10 text-[var(--color-primary)] text-sm font-medium">
                <span>📊</span> Dashboard
            </a>

            {#if userInfo?.role === 'ROLE_FEDERATION_ADMIN'}
                <a href="#/t/{tenantSlug}/members" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                    <span>👥</span> Membres
                </a>
                <a href="#/t/{tenantSlug}/clubs" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                    <span>🏢</span> Clubs
                </a>
            {/if}

            <!-- Plugin menu items -->
            {#each activePlugins as plugin}
                {#each plugin.menu_items || [] as item}
                    <a href="#/t/{tenantSlug}{item.path}" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                        <span>{item.icon === 'book-open' ? '📖' : item.icon === 'calendar-check' ? '📋' : item.icon === 'calendar' ? '📅' : item.icon === 'door-open' ? '🚪' : '📦'}</span>
                        {item.label}
                    </a>
                {/each}
            {/each}

            {#if userInfo?.role === 'ROLE_FEDERATION_ADMIN'}
                <div class="pt-3 mt-3 border-t border-[var(--color-border)]">
                    <p class="px-3 text-xs text-[var(--color-muted-foreground)] uppercase tracking-wide mb-2">Administration</p>
                    <a href="#/database" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                        <span>🗄️</span> Base de données
                    </a>
                    <a href="#/plugins" class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius)] text-[var(--color-muted)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-border)] text-sm">
                        <span>🧩</span> Modules
                    </a>
                </div>
            {/if}
        </nav>

        <div class="p-3 border-t border-[var(--color-border)]">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-sm font-bold">
                    {auth.user?.first_name?.[0]}{auth.user?.last_name?.[0]}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{auth.user?.first_name} {auth.user?.last_name}</p>
                    <p class="text-xs text-[var(--color-muted)] truncate">{userInfo?.role?.replace('ROLE_', '') || ''}</p>
                </div>
            </div>
            <Button variant="ghost" size="sm" class="w-full mt-1" onclick={handleLogout}>Déconnexion</Button>
        </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 overflow-auto">
        <header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <span class="text-xl">{dashboardLabels[dashboard?.type]?.icon || '📊'}</span>
                <h2 class="text-lg font-semibold">{dashboard?.name || 'Dashboard'}</h2>
            </div>
            {#if userInfo?.role}
                <span class="text-xs px-2 py-1 rounded-full border border-[var(--color-border)] text-[var(--color-muted)]">
                    {userInfo.role.replace('ROLE_', '')}
                </span>
            {/if}
        </header>

        <div class="p-6">
            {#if loading}
                <p class="text-[var(--color-muted)]">Chargement du dashboard...</p>
            {:else if dashboard?.widgets?.length > 0}
                <div class="grid grid-cols-4 gap-4 auto-rows-min">
                    {#each dashboard.widgets as widget}
                        <div class="col-span-{Math.min(widget.width || 1, 4)}" style="grid-column: span {Math.min(widget.width || 1, 4)}; grid-row: span {widget.height || 1};">
                            <WidgetRenderer {widget} data={widgetData[widget.id]} />
                        </div>
                    {/each}
                </div>
            {:else}
                <Card class="p-8 text-center">
                    <p class="text-[var(--color-muted)]">Aucun widget configuré pour ce dashboard.</p>
                </Card>
            {/if}
        </div>
    </main>
</div>
{/if}
