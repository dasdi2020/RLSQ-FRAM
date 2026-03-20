<script>
    import { get, post, put, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let pages = $state([]);
    let selectedPage = $state(null);
    let showCreateDialog = $state(false);
    let showComponentDialog = $state(false);
    let showPreview = $state(false);
    let previewDevice = $state('desktop');
    let newPageName = $state('');
    let newComponent = $state({ type: 'text', content: '', width: 12, props: {} });
    let editingComponent = $state(null);
    let loading = $state(true);

    const componentTypes = [
        { value: 'heading', label: 'Titre', icon: 'H', desc: 'Titre H1-H6' },
        { value: 'text', label: 'Texte', icon: '¶', desc: 'Paragraphe' },
        { value: 'image', label: 'Image', icon: '🖼', desc: 'Image avec alt' },
        { value: 'button', label: 'Bouton', icon: '▶', desc: 'Bouton lien' },
        { value: 'card', label: 'Carte', icon: '▬', desc: 'Carte avec contenu' },
        { value: 'divider', label: 'Séparateur', icon: '—', desc: 'Ligne horizontale' },
        { value: 'spacer', label: 'Espace', icon: '↕', desc: 'Espace vertical' },
        { value: 'html', label: 'HTML', icon: '<>', desc: 'Code HTML libre' },
        { value: 'form', label: 'Formulaire', icon: '📝', desc: 'Formulaire dynamique' },
        { value: 'datatable', label: 'Table', icon: '📊', desc: 'Table de données' },
        { value: 'iframe', label: 'Iframe', icon: '🔲', desc: 'Contenu externe' },
        { value: 'richtext', label: 'Éditeur', icon: '✎', desc: 'Texte riche HTML' },
    ];

    const deviceWidths = { desktop: '100%', tablet: '768px', mobile: '375px' };

    async function loadPages() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/pages`);
            pages = res.data || [];
        } catch (e) { console.error(e); }
        loading = false;
    }

    async function createPage() {
        if (!newPageName) return;
        const res = await post(`/api/t/${tenantSlug}/pages`, { name: newPageName });
        newPageName = '';
        showCreateDialog = false;
        await loadPages();
        selectedPage = res.data;
    }

    async function deletePage(id) {
        if (!confirm('Supprimer cette page ?')) return;
        await del(`/api/t/${tenantSlug}/pages/${id}`);
        selectedPage = null;
        await loadPages();
    }

    async function duplicatePage(id) {
        await post(`/api/t/${tenantSlug}/pages/${id}/duplicate`);
        await loadPages();
    }

    async function togglePublish() {
        if (!selectedPage) return;
        await put(`/api/t/${tenantSlug}/pages/${selectedPage.id}`, { is_published: selectedPage.is_published == 1 ? 0 : 1 });
        await reloadSelectedPage();
    }

    async function addComponent() {
        if (!selectedPage) return;
        await post(`/api/t/${tenantSlug}/pages/${selectedPage.id}/components`, newComponent);
        newComponent = { type: 'text', content: '', width: 12, props: {} };
        showComponentDialog = false;
        await reloadSelectedPage();
    }

    async function deleteComponent(compId) {
        await del(`/api/t/${tenantSlug}/pages/components/${compId}`);
        await reloadSelectedPage();
    }

    async function updateComponentInline(compId, data) {
        await put(`/api/t/${tenantSlug}/pages/components/${compId}`, data);
        await reloadSelectedPage();
    }

    async function reloadSelectedPage() {
        if (!selectedPage) return;
        const res = await get(`/api/t/${tenantSlug}/pages/${selectedPage.id}`);
        selectedPage = res.data;
        await loadPages();
    }

    function openPreview() {
        if (selectedPage) {
            showPreview = true;
        }
    }

    function getComponentIcon(type) {
        return componentTypes.find(t => t.value === type)?.icon ?? '?';
    }

    function renderComponentPreview(comp) {
        const props = comp.props || {};
        switch (comp.type) {
            case 'heading': return `<h${props.level||2} style="margin:0">${comp.content || 'Titre'}</h${props.level||2}>`;
            case 'text': return `<p style="margin:0;color:#aaa">${comp.content || 'Texte...'}</p>`;
            case 'image': return `<div style="background:#2a2a3e;padding:20px;text-align:center;border-radius:4px">🖼 ${props.alt || 'Image'}</div>`;
            case 'button': return `<button style="background:var(--color-primary);color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer">${comp.content || 'Bouton'}</button>`;
            case 'divider': return '<hr style="border-color:var(--color-border)">';
            case 'spacer': return `<div style="height:${props.height||32}px"></div>`;
            case 'card': return `<div style="border:1px solid var(--color-border);border-radius:8px;padding:12px">${comp.content || 'Carte'}</div>`;
            case 'html': return `<div style="background:#1a1a2e;padding:8px;border-radius:4px;font-family:monospace;font-size:11px;color:#888">${(comp.content||'').substring(0,80)}...</div>`;
            default: return `<div style="color:#888">[${comp.type}]</div>`;
        }
    }

    $effect(() => { loadPages(); });
</script>

<div class="flex h-full gap-0">
    <!-- Sidebar : pages -->
    <div class="w-64 flex-shrink-0 flex flex-col gap-2 border-r border-[var(--color-border)] pr-4">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide">Pages</h3>
            <Button size="sm" onclick={() => showCreateDialog = true}>+</Button>
        </div>
        {#each pages as page}
            <button class="w-full text-left p-2.5 rounded-[var(--radius)] border text-sm transition-colors cursor-pointer
                {selectedPage?.id === page.id ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-accent)]'}"
                onclick={() => { get(`/api/t/${tenantSlug}/pages/${page.id}`).then(r => selectedPage = r.data); }}>
                <div class="flex items-center justify-between">
                    <span class="font-medium">{page.name}</span>
                    {#if page.is_published == 1}<span class="w-2 h-2 rounded-full bg-[var(--color-success)]"></span>{/if}
                </div>
                <div class="text-xs text-[var(--color-muted)] mt-0.5">{page.route_path} — {page.component_count ?? 0} comp.</div>
            </button>
        {/each}
    </div>

    <!-- Centre : canvas -->
    <div class="flex-1 px-4 overflow-auto">
        {#if !selectedPage}
            <div class="flex items-center justify-center h-64 text-[var(--color-muted)]">Sélectionnez ou créez une page.</div>
        {:else}
            <!-- Toolbar -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold">{selectedPage.name}</h2>
                    <p class="text-xs text-[var(--color-muted)]">{selectedPage.route_path} — {selectedPage.is_published == 1 ? '🟢 Publié' : '⚫ Brouillon'}</p>
                </div>
                <div class="flex gap-2">
                    <Button size="sm" variant="secondary" onclick={openPreview}>Aperçu</Button>
                    <Button size="sm" variant="secondary" onclick={() => duplicatePage(selectedPage.id)}>Dupliquer</Button>
                    <Button size="sm" variant={selectedPage.is_published == 1 ? 'secondary' : 'default'} onclick={togglePublish}>
                        {selectedPage.is_published == 1 ? 'Dépublier' : 'Publier'}
                    </Button>
                    <Button size="sm" onclick={() => showComponentDialog = true}>+ Composant</Button>
                    <Button size="sm" variant="destructive" onclick={() => deletePage(selectedPage.id)}>Suppr.</Button>
                </div>
            </div>

            <!-- Components grid -->
            <div class="grid grid-cols-12 gap-3 min-h-[300px]">
                {#each selectedPage.components || [] as comp}
                    <div class="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] overflow-hidden hover:border-[var(--color-accent)] transition-colors group"
                         style="grid-column: span {Math.min(comp.width || 12, 12)}">
                        <!-- Component header -->
                        <div class="flex items-center justify-between px-3 py-1.5 bg-[var(--color-secondary)] border-b border-[var(--color-border)] text-xs">
                            <span class="flex items-center gap-1.5 text-[var(--color-muted)]">
                                <span>{getComponentIcon(comp.type)}</span>
                                <span class="uppercase tracking-wide">{comp.type}</span>
                                <span class="text-[var(--color-muted-foreground)]">w:{comp.width}</span>
                            </span>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100">
                                <button class="text-[var(--color-destructive)] cursor-pointer" onclick={() => deleteComponent(comp.id)}>✕</button>
                            </div>
                        </div>
                        <!-- Component preview -->
                        <div class="p-3">
                            {@html renderComponentPreview(comp)}
                        </div>
                    </div>
                {/each}

                {#if (selectedPage.components?.length ?? 0) === 0}
                    <div class="col-span-12 flex items-center justify-center h-32 border-2 border-dashed border-[var(--color-border)] rounded-[var(--radius)] text-[var(--color-muted)]">
                        Cliquez sur "+ Composant" pour commencer
                    </div>
                {/if}
            </div>
        {/if}
    </div>
</div>

<!-- Create Page Dialog -->
{#if showCreateDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showCreateDialog = false}>
        <Card class="w-full max-w-md" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouvelle page</h3>
                <Input placeholder="Nom de la page" bind:value={newPageName} />
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showCreateDialog = false}>Annuler</Button>
                    <Button onclick={createPage}>Créer</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Add Component Dialog -->
{#if showComponentDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showComponentDialog = false}>
        <Card class="w-full max-w-2xl max-h-[85vh] overflow-y-auto" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Ajouter un composant</h3>

                <!-- Type picker -->
                <div class="grid grid-cols-4 gap-2">
                    {#each componentTypes as ct}
                        <button class="p-3 rounded-[var(--radius)] border text-center cursor-pointer transition-colors
                            {newComponent.type === ct.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                            onclick={() => newComponent.type = ct.value}>
                            <div class="text-xl">{ct.icon}</div>
                            <div class="text-xs font-medium mt-1">{ct.label}</div>
                            <div class="text-xs text-[var(--color-muted)]">{ct.desc}</div>
                        </button>
                    {/each}
                </div>

                <!-- Content -->
                {#if ['heading', 'text', 'button', 'card', 'html', 'richtext'].includes(newComponent.type)}
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Contenu</label>
                        {#if newComponent.type === 'html' || newComponent.type === 'richtext'}
                            <textarea class="flex w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm font-mono min-h-[100px]"
                                bind:value={newComponent.content}></textarea>
                        {:else}
                            <Input bind:value={newComponent.content} placeholder="Contenu du composant" />
                        {/if}
                    </div>
                {/if}

                <!-- Props spécifiques -->
                {#if newComponent.type === 'heading'}
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Niveau</label>
                        <div class="flex gap-2">
                            {#each [1,2,3,4,5,6] as level}
                                <button class="w-10 h-10 rounded border cursor-pointer text-sm font-bold
                                    {(newComponent.props.level||2) === level ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)]'}"
                                    onclick={() => newComponent.props = {...newComponent.props, level}}>H{level}</button>
                            {/each}
                        </div>
                    </div>
                {/if}

                {#if newComponent.type === 'image'}
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-sm text-[var(--color-muted)] mb-1 block">URL image</label><Input bind:value={newComponent.props.src} placeholder="https://..." /></div>
                        <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Texte alt</label><Input bind:value={newComponent.props.alt} placeholder="Description" /></div>
                    </div>
                {/if}

                {#if newComponent.type === 'button'}
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">URL du lien</label><Input bind:value={newComponent.props.url} placeholder="/page" /></div>
                {/if}

                {#if newComponent.type === 'iframe'}
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">URL</label><Input bind:value={newComponent.props.src} placeholder="https://..." /></div>
                {/if}

                <!-- Width -->
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Largeur ({newComponent.width}/12)</label>
                    <input type="range" min="1" max="12" bind:value={newComponent.width} class="w-full" />
                </div>

                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showComponentDialog = false}>Annuler</Button>
                    <Button onclick={addComponent}>Ajouter</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Preview -->
{#if showPreview && selectedPage}
    <div class="fixed inset-0 bg-black/80 flex flex-col z-50">
        <div class="h-12 flex items-center justify-between px-4 bg-[var(--color-secondary)] border-b border-[var(--color-border)]">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold">Aperçu : {selectedPage.name}</span>
                <div class="flex gap-1 ml-4">
                    {#each ['desktop', 'tablet', 'mobile'] as device}
                        <button class="px-3 py-1 rounded text-xs cursor-pointer {previewDevice === device ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:text-white'}"
                            onclick={() => previewDevice = device}>
                            {device === 'desktop' ? '🖥' : device === 'tablet' ? '📱' : '📲'} {device}
                        </button>
                    {/each}
                </div>
            </div>
            <button class="text-[var(--color-muted)] hover:text-white cursor-pointer text-lg" onclick={() => showPreview = false}>✕</button>
        </div>
        <div class="flex-1 flex items-start justify-center p-4 overflow-auto">
            <iframe
                src="/api/t/{tenantSlug}/pages/{selectedPage.id}/preview"
                style="width:{deviceWidths[previewDevice]};height:100%;border:1px solid var(--color-border);border-radius:8px;background:#fff;"
                title="Preview"
            ></iframe>
        </div>
    </div>
{/if}
