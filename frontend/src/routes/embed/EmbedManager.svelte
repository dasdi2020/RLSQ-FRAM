<script>
    import { get, post, put, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let embeds = $state([]);
    let selectedEmbed = $state(null);
    let showCreateDialog = $state(false);
    let showSnippetDialog = $state(false);
    let snippetCode = $state('');
    let loading = $state(true);

    let newEmbed = $state({ name: '', module_slug: 'formations', allowed_domains: '', theme: { primary_color: '#ff3e00', background_color: '#ffffff' } });

    const modules = [
        { value: 'formations', label: 'Formations', icon: '📖' },
        { value: 'activities', label: 'Activités', icon: '📋' },
        { value: 'calendar', label: 'Calendrier', icon: '📅' },
        { value: 'room-booking', label: 'Location de salles', icon: '🚪' },
    ];

    async function loadEmbeds() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/embeds`);
            embeds = res.data || [];
        } catch (e) { console.error(e); }
        loading = false;
    }

    async function createEmbed() {
        if (!newEmbed.name) return;
        const data = {
            ...newEmbed,
            allowed_domains: newEmbed.allowed_domains ? newEmbed.allowed_domains.split(',').map(d => d.trim()) : ['*'],
        };
        try {
            const res = await post(`/api/t/${tenantSlug}/embeds`, data);
            showCreateDialog = false;
            newEmbed = { name: '', module_slug: 'formations', allowed_domains: '', theme: { primary_color: '#ff3e00', background_color: '#ffffff' } };
            await loadEmbeds();
            selectedEmbed = res.data;
        } catch (e) { console.error(e); }
    }

    async function deleteEmbed(id) {
        if (!confirm('Supprimer cet embed ?')) return;
        await del(`/api/t/${tenantSlug}/embeds/${id}`);
        selectedEmbed = null;
        await loadEmbeds();
    }

    async function toggleActive(embed) {
        await put(`/api/t/${tenantSlug}/embeds/${embed.id}`, { is_active: embed.is_active == 1 ? 0 : 1 });
        await loadEmbeds();
        if (selectedEmbed?.id === embed.id) {
            selectedEmbed = embeds.find(e => e.id === embed.id);
        }
    }

    async function regenerateToken(id) {
        if (!confirm('Régénérer le token ? Les anciens embeds cesseront de fonctionner.')) return;
        const res = await post(`/api/t/${tenantSlug}/embeds/${id}/regenerate-token`);
        await loadEmbeds();
        selectedEmbed = res.data;
    }

    async function showSnippet(id) {
        const res = await get(`/api/t/${tenantSlug}/embeds/${id}/snippet`);
        snippetCode = res.snippet || '';
        showSnippetDialog = true;
    }

    function copySnippet() {
        navigator.clipboard.writeText(snippetCode);
    }

    function getModuleIcon(slug) {
        return modules.find(m => m.value === slug)?.icon || '📦';
    }

    $effect(() => { loadEmbeds(); });
</script>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">Embeds</h2>
            <p class="text-sm text-[var(--color-muted)]">Intégrez vos modules dans des sites externes via iframe</p>
        </div>
        <Button onclick={() => showCreateDialog = true}>+ Nouvel embed</Button>
    </div>

    {#if loading}
        <p class="text-[var(--color-muted)]">Chargement...</p>
    {:else if embeds.length === 0}
        <Card class="p-8 text-center">
            <p class="text-4xl mb-4">🔗</p>
            <p class="text-[var(--color-muted)]">Aucun embed configuré.</p>
            <Button class="mt-4" onclick={() => showCreateDialog = true}>Créer un embed</Button>
        </Card>
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each embeds as embed}
                <Card class="overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">{getModuleIcon(embed.module_slug)}</span>
                                <div>
                                    <h3 class="font-semibold">{embed.name}</h3>
                                    <span class="text-xs text-[var(--color-muted)]">{embed.module_slug}</span>
                                </div>
                            </div>
                            {#if embed.is_active == 1}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-success)]/15 text-[var(--color-success)]">Actif</span>
                            {:else}
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-muted)]/15 text-[var(--color-muted)]">Inactif</span>
                            {/if}
                        </div>

                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between text-[var(--color-muted)]">
                                <span>Token</span>
                                <span class="font-mono text-xs">{embed.token?.substring(0, 12)}...</span>
                            </div>
                            <div class="flex justify-between text-[var(--color-muted)]">
                                <span>Domaines</span>
                                <span>{(embed.allowed_domains || []).join(', ') || '*'}</span>
                            </div>
                            <div class="flex justify-between text-[var(--color-muted)]">
                                <span>Vues</span>
                                <span class="font-semibold text-[var(--color-foreground)]">{embed.views_count || 0}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex border-t border-[var(--color-border)] bg-[var(--color-secondary)]/50 text-xs">
                        <button class="flex-1 py-2.5 text-center hover:bg-[var(--color-border)]/50 cursor-pointer text-[var(--color-accent)]" onclick={() => showSnippet(embed.id)}>
                            Code
                        </button>
                        <button class="flex-1 py-2.5 text-center hover:bg-[var(--color-border)]/50 cursor-pointer border-l border-[var(--color-border)]" onclick={() => toggleActive(embed)}>
                            {embed.is_active == 1 ? 'Désactiver' : 'Activer'}
                        </button>
                        <button class="flex-1 py-2.5 text-center hover:bg-[var(--color-border)]/50 cursor-pointer border-l border-[var(--color-border)]" onclick={() => regenerateToken(embed.id)}>
                            Regen token
                        </button>
                        <button class="flex-1 py-2.5 text-center hover:bg-[var(--color-border)]/50 cursor-pointer border-l border-[var(--color-border)] text-[var(--color-destructive)]" onclick={() => deleteEmbed(embed.id)}>
                            Suppr.
                        </button>
                    </div>
                </Card>
            {/each}
        </div>
    {/if}
</div>

<!-- Preview iframe -->
{#if selectedEmbed}
    <Card class="mt-6 overflow-hidden">
        <div class="p-4 border-b border-[var(--color-border)] flex items-center justify-between">
            <h3 class="font-semibold">Aperçu : {selectedEmbed.name}</h3>
            <button class="text-[var(--color-muted)] cursor-pointer" onclick={() => selectedEmbed = null}>✕</button>
        </div>
        <iframe
            src="/embed/{selectedEmbed.token}"
            class="w-full border-0 min-h-[400px]"
            title="Embed preview"
        ></iframe>
    </Card>
{/if}

<!-- Create Dialog -->
{#if showCreateDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showCreateDialog = false}>
        <Card class="w-full max-w-lg" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouvel embed</h3>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label>
                    <Input placeholder="Widget Formations" bind:value={newEmbed.name} />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Module</label>
                    <div class="grid grid-cols-2 gap-2">
                        {#each modules as m}
                            <button class="p-3 rounded-[var(--radius)] border text-left cursor-pointer transition-colors flex items-center gap-2
                                {newEmbed.module_slug === m.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                                onclick={() => newEmbed.module_slug = m.value}>
                                <span class="text-xl">{m.icon}</span>
                                <span class="text-sm font-medium">{m.label}</span>
                            </button>
                        {/each}
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Domaines autorisés (séparés par des virgules, * = tous)</label>
                    <Input placeholder="monsite.com, *.exemple.com" bind:value={newEmbed.allowed_domains} />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Couleur principale</label>
                        <input type="color" bind:value={newEmbed.theme.primary_color} class="w-full h-10 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] cursor-pointer" />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Fond</label>
                        <input type="color" bind:value={newEmbed.theme.background_color} class="w-full h-10 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] cursor-pointer" />
                    </div>
                </div>
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showCreateDialog = false}>Annuler</Button>
                    <Button onclick={createEmbed}>Créer</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Snippet Dialog -->
{#if showSnippetDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showSnippetDialog = false}>
        <Card class="w-full max-w-2xl" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Code d'intégration</h3>
                    <Button size="sm" onclick={copySnippet}>Copier</Button>
                </div>
                <p class="text-sm text-[var(--color-muted)]">Collez ce code dans votre site web pour afficher le widget :</p>
                <pre class="bg-[var(--color-secondary)] p-4 rounded-[var(--radius)] border border-[var(--color-border)] text-xs font-mono overflow-x-auto whitespace-pre-wrap text-[var(--color-accent)]">{snippetCode}</pre>
                <Button variant="secondary" class="w-full" onclick={() => showSnippetDialog = false}>Fermer</Button>
            </div>
        </Card>
    </div>
{/if}
