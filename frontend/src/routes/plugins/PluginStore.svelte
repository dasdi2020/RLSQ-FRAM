<script>
    import { get, post, put } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';
    import AppLayout from '$lib/components/AppLayout.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let plugins = $state([]);
    let loading = $state(true);
    let selectedPlugin = $state(null);
    let showSettings = $state(false);
    let settingsData = $state({});
    let actionLoading = $state(null);

    const iconMap = {
        'book-open': '📖', 'calendar-check': '📋', 'calendar': '📅',
        'door-open': '🚪', 'puzzle': '🧩', 'users': '👥',
    };

    async function loadPlugins() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/plugins`);
            plugins = res.data || [];
        } catch (e) { console.error(e); }
        loading = false;
    }

    async function installPlugin(slug) {
        actionLoading = slug;
        try {
            await post(`/api/t/${tenantSlug}/plugins/${slug}/install`, {});
            await loadPlugins();
        } catch (e) { console.error(e); }
        actionLoading = null;
    }

    async function uninstallPlugin(slug) {
        if (!confirm('Désinstaller ce plugin ? Les données seront supprimées.')) return;
        actionLoading = slug;
        try {
            await post(`/api/t/${tenantSlug}/plugins/${slug}/uninstall`, {});
            await loadPlugins();
        } catch (e) { console.error(e); }
        actionLoading = null;
    }

    async function togglePlugin(slug, isActive) {
        actionLoading = slug;
        const action = isActive ? 'deactivate' : 'activate';
        try {
            await post(`/api/t/${tenantSlug}/plugins/${slug}/${action}`, {});
            await loadPlugins();
        } catch (e) { console.error(e); }
        actionLoading = null;
    }

    function openSettings(plugin) {
        selectedPlugin = plugin;
        settingsData = { ...(plugin.settings || {}) };
        showSettings = true;
    }

    async function saveSettings() {
        if (!selectedPlugin) return;
        await put(`/api/t/${tenantSlug}/plugins/${selectedPlugin.slug}/settings`, settingsData);
        showSettings = false;
        await loadPlugins();
    }

    $effect(() => { loadPlugins(); });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Modules</h2></header>
<div class="p-6 flex-1 overflow-auto">
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-[var(--color-muted)]">Installez et configurez les modules pour votre organisation</p>
        </div>
    </div>

    {#if loading}
        <p class="text-[var(--color-muted)]">Chargement...</p>
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {#each plugins as plugin}
                <Card class="p-0 overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{iconMap[plugin.icon] || '🧩'}</span>
                                <div>
                                    <h3 class="font-semibold text-base">{plugin.name}</h3>
                                    <span class="text-xs text-[var(--color-muted)]">v{plugin.version} — {plugin.author}</span>
                                </div>
                            </div>
                            {#if plugin.is_active}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-success)]/15 text-[var(--color-success)]">Actif</span>
                            {:else if plugin.is_installed}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-warning)]/15 text-[var(--color-warning)]">Inactif</span>
                            {:else}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-border)] text-[var(--color-muted)]">Non installé</span>
                            {/if}
                        </div>
                        <p class="text-sm text-[var(--color-muted)] mt-3 leading-relaxed">{plugin.description}</p>

                        {#if plugin.menu_items?.length > 0}
                            <div class="flex gap-2 mt-3 flex-wrap">
                                {#each plugin.menu_items as item}
                                    <span class="text-xs px-2 py-1 rounded bg-[var(--color-secondary)] border border-[var(--color-border)]">
                                        {iconMap[item.icon] || ''} {item.label}
                                    </span>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    <div class="flex border-t border-[var(--color-border)] bg-[var(--color-secondary)]/50">
                        {#if !plugin.is_installed}
                            <Button variant="ghost" size="sm" class="flex-1 rounded-none"
                                disabled={actionLoading === plugin.slug}
                                onclick={() => installPlugin(plugin.slug)}>
                                {actionLoading === plugin.slug ? 'Installation...' : 'Installer'}
                            </Button>
                        {:else}
                            <Button variant="ghost" size="sm" class="flex-1 rounded-none"
                                disabled={actionLoading === plugin.slug}
                                onclick={() => togglePlugin(plugin.slug, plugin.is_active)}>
                                {plugin.is_active ? 'Désactiver' : 'Activer'}
                            </Button>
                            {#if plugin.settings_schema?.fields?.length > 0}
                                <Button variant="ghost" size="sm" class="flex-1 rounded-none border-l border-[var(--color-border)]"
                                    onclick={() => openSettings(plugin)}>
                                    Configurer
                                </Button>
                            {/if}
                            <Button variant="ghost" size="sm" class="rounded-none border-l border-[var(--color-border)] text-[var(--color-destructive)]"
                                disabled={actionLoading === plugin.slug}
                                onclick={() => uninstallPlugin(plugin.slug)}>
                                Supprimer
                            </Button>
                        {/if}
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<!-- Settings Dialog -->
<Dialog bind:open={showSettings} class="max-w-md">
    <div class="p-6 space-y-4">
        {#if selectedPlugin}
        <h3 class="text-lg font-semibold">Configuration — {selectedPlugin.name}</h3>

        {#each selectedPlugin.settings_schema?.fields || [] as field}
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">{field.label}</label>
                {#if field.type === 'boolean'}
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" bind:checked={settingsData[field.name]} />
                        <span class="text-sm">{settingsData[field.name] ? 'Activé' : 'Désactivé'}</span>
                    </label>
                {:else if field.type === 'integer'}
                    <input type="number" class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm"
                        bind:value={settingsData[field.name]} />
                {:else}
                    <input type="text" class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm"
                        bind:value={settingsData[field.name]} />
                {/if}
            </div>
        {/each}

        <div class="flex gap-2 justify-end pt-2">
            <Button variant="secondary" onclick={() => showSettings = false}>Annuler</Button>
            <Button onclick={saveSettings}>Sauvegarder</Button>
        </div>
        {/if}
    </div>
</Dialog>
</div>
</AppLayout>
