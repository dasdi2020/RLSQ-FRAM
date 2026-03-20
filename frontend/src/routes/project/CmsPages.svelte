<script>
    import { get, post, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';

    let { projectSlug = '' } = $props();
    // Pour l'instant on utilise le tenant slug = project slug (simplifié)
    let tenantSlug = $derived(projectSlug);

    let pages = $state([]);
    let loading = $state(true);
    let showCreate = $state(false);
    let newPageName = $state('');

    async function loadPages() {
        loading = true;
        try { const r = await get(`/api/t/${tenantSlug}/pages`); pages = r.data || []; } catch {}
        loading = false;
    }

    async function createPage() {
        if (!newPageName) return;
        await post(`/api/t/${tenantSlug}/pages`, { name: newPageName });
        newPageName = ''; showCreate = false;
        await loadPages();
    }

    async function deletePage(id) {
        if (!confirm('Supprimer cette page ?')) return;
        await del(`/api/t/${tenantSlug}/pages/${id}`);
        await loadPages();
    }

    $effect(() => { if (tenantSlug) loadPages(); });
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">Pages</h2>
    <Button size="sm" onclick={() => showCreate = true}>+ Nouvelle page</Button>
</header>

<div class="p-6">
    {#if loading}
        <p class="text-[var(--color-muted)]">Chargement...</p>
    {:else if pages.length === 0}
        <Card class="p-8 text-center">
            <span class="text-5xl block mb-4">📄</span>
            <p class="text-lg font-medium mb-2">Aucune page</p>
            <p class="text-sm text-[var(--color-muted)] mb-4">Créez votre première page pour commencer à construire le site.</p>
            <Button onclick={() => showCreate = true}>+ Créer une page</Button>
        </Card>
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each pages as page}
                <Card class="overflow-hidden hover:border-[var(--color-primary)] transition-colors">
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold">{page.name}</h3>
                                <p class="text-xs text-[var(--color-muted)] font-mono mt-0.5">{page.route_path || '/' + page.slug}</p>
                            </div>
                            {#if page.is_published == 1}
                                <span class="w-2 h-2 rounded-full bg-[var(--color-success)] mt-1"></span>
                            {:else}
                                <span class="w-2 h-2 rounded-full bg-[var(--color-muted)] mt-1"></span>
                            {/if}
                        </div>
                        <p class="text-xs text-[var(--color-muted)] mt-2">{page.component_count || 0} composants</p>
                    </div>
                    <div class="flex border-t border-[var(--color-border)] bg-[var(--color-secondary)]/50 text-xs">
                        <a href="#/p/{projectSlug}/pages/{page.id}" class="flex-1 py-2 text-center hover:bg-[var(--color-border)]/50 cursor-pointer text-[var(--color-primary)]">Éditer</a>
                        <button class="py-2 px-3 hover:bg-[var(--color-border)]/50 cursor-pointer border-l border-[var(--color-border)] text-[var(--color-destructive)]" onclick={() => deletePage(page.id)}>✕</button>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<Dialog bind:open={showCreate}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Nouvelle page</h3>
        <Input placeholder="Nom de la page" bind:value={newPageName} />
        <div class="flex gap-2 justify-end">
            <Button variant="secondary" onclick={() => showCreate = false}>Annuler</Button>
            <Button onclick={createPage}>Créer</Button>
        </div>
    </div>
</Dialog>
