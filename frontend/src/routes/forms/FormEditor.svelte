<script>
    import { get, post, put, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let forms = $state([]);
    let selectedForm = $state(null);
    let showCreateDialog = $state(false);
    let showFieldDialog = $state(false);
    let showPreview = $state(false);
    let newFormName = $state('');
    let newField = $state({ name: '', label: '', type: 'text', is_required: false, width: 12, placeholder: '', help_text: '', validation: {} });
    let loading = $state(true);
    let previewData = $state(null);

    const fieldTypes = [
        { value: 'text', label: 'Texte', icon: 'Aa' },
        { value: 'textarea', label: 'Texte long', icon: '¶' },
        { value: 'email', label: 'Email', icon: '@' },
        { value: 'number', label: 'Nombre', icon: '#' },
        { value: 'phone', label: 'Téléphone', icon: '📞' },
        { value: 'date', label: 'Date', icon: '📅' },
        { value: 'select', label: 'Liste', icon: '▼' },
        { value: 'checkbox', label: 'Case', icon: '☑' },
        { value: 'radio', label: 'Choix', icon: '◉' },
        { value: 'file', label: 'Fichier', icon: '📎' },
        { value: 'richtext', label: 'Éditeur', icon: '✎' },
        { value: 'hidden', label: 'Caché', icon: '👁' },
    ];

    async function loadForms() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/forms`);
            forms = res.data || [];
        } catch (e) { console.error(e); }
        loading = false;
    }

    async function createForm() {
        if (!newFormName) return;
        try {
            const res = await post(`/api/t/${tenantSlug}/forms`, { name: newFormName });
            newFormName = '';
            showCreateDialog = false;
            await loadForms();
            selectedForm = res.data;
        } catch (e) { console.error(e); }
    }

    async function deleteForm(id) {
        if (!confirm('Supprimer ce formulaire et toutes ses soumissions ?')) return;
        await del(`/api/t/${tenantSlug}/forms/${id}`);
        selectedForm = null;
        await loadForms();
    }

    async function togglePublish() {
        if (!selectedForm) return;
        const newState = selectedForm.is_published == 1 ? 0 : 1;
        await put(`/api/t/${tenantSlug}/forms/${selectedForm.id}`, { is_published: newState });
        await loadForms();
        selectedForm = forms.find(f => f.id === selectedForm.id);
    }

    async function addField() {
        if (!selectedForm || !newField.name) return;
        await post(`/api/t/${tenantSlug}/forms/${selectedForm.id}/fields`, newField);
        newField = { name: '', label: '', type: 'text', is_required: false, width: 12, placeholder: '', help_text: '', validation: {} };
        showFieldDialog = false;
        await loadForms();
        selectedForm = forms.find(f => f.id === selectedForm.id);
    }

    async function deleteField(fieldId) {
        await del(`/api/t/${tenantSlug}/forms/fields/${fieldId}`);
        await loadForms();
        selectedForm = forms.find(f => f.id === selectedForm.id);
    }

    async function toggleFieldRequired(field) {
        await put(`/api/t/${tenantSlug}/forms/fields/${field.id}`, { is_required: !field.is_required });
        await loadForms();
        selectedForm = forms.find(f => f.id === selectedForm.id);
    }

    async function toggleFieldVisible(field) {
        await put(`/api/t/${tenantSlug}/forms/fields/${field.id}`, { is_visible: !field.is_visible });
        await loadForms();
        selectedForm = forms.find(f => f.id === selectedForm.id);
    }

    async function loadPreview() {
        if (!selectedForm) return;
        try {
            const res = await get(`/api/t/${tenantSlug}/forms/${selectedForm.slug}/render`);
            previewData = res.data;
            showPreview = true;
        } catch (e) { console.error(e); }
    }

    $effect(() => { loadForms(); });
</script>

<div class="flex h-full gap-4">
    <!-- Sidebar -->
    <div class="w-72 flex-shrink-0 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide">Formulaires</h3>
            <Button size="sm" onclick={() => showCreateDialog = true}>+ Formulaire</Button>
        </div>

        {#if loading}
            <p class="text-sm text-[var(--color-muted)]">Chargement...</p>
        {:else}
            {#each forms as form}
                <button
                    class="w-full text-left p-3 rounded-[var(--radius)] border transition-colors cursor-pointer
                        {selectedForm?.id === form.id ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-accent)]'}"
                    onclick={() => selectedForm = form}
                >
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-sm">{form.name}</span>
                        {#if form.is_published == 1}
                            <span class="w-2 h-2 rounded-full bg-[var(--color-success)]"></span>
                        {:else}
                            <span class="w-2 h-2 rounded-full bg-[var(--color-muted)]"></span>
                        {/if}
                    </div>
                    <div class="text-xs text-[var(--color-muted)] mt-0.5">{form.fields?.length ?? 0} champs — {form.submission_count ?? 0} soumissions</div>
                </button>
            {/each}
        {/if}
    </div>

    <!-- Main -->
    <div class="flex-1">
        {#if !selectedForm}
            <div class="flex items-center justify-center h-64">
                <p class="text-[var(--color-muted)]">Sélectionnez ou créez un formulaire.</p>
            </div>
        {:else}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold">{selectedForm.name}</h2>
                        <p class="text-sm text-[var(--color-muted)]">Slug : {selectedForm.slug} — {selectedForm.is_published == 1 ? '🟢 Publié' : '⚫ Brouillon'}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" variant="secondary" onclick={loadPreview}>Aperçu</Button>
                        <Button size="sm" variant={selectedForm.is_published == 1 ? 'secondary' : 'default'} onclick={togglePublish}>
                            {selectedForm.is_published == 1 ? 'Dépublier' : 'Publier'}
                        </Button>
                        <Button size="sm" onclick={() => showFieldDialog = true}>+ Champ</Button>
                        <Button size="sm" variant="destructive" onclick={() => deleteForm(selectedForm.id)}>Supprimer</Button>
                    </div>
                </div>

                <!-- Fields list -->
                <Card>
                    {#if (selectedForm.fields?.length ?? 0) === 0}
                        <div class="p-8 text-center text-[var(--color-muted)]">
                            Aucun champ. Cliquez sur "+ Champ" pour commencer.
                        </div>
                    {:else}
                        <div class="divide-y divide-[var(--color-border)]">
                            {#each selectedForm.fields ?? [] as field, i}
                                <div class="flex items-center gap-4 p-3 hover:bg-[var(--color-border)]/20 group">
                                    <div class="w-8 text-center text-lg opacity-40">
                                        {fieldTypes.find(t => t.value === field.type)?.icon ?? '?'}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm">{field.label}</span>
                                            <span class="text-xs font-mono text-[var(--color-muted)]">{field.name}</span>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-[var(--color-muted)]">
                                            <span class="px-1.5 py-0.5 rounded bg-[var(--color-secondary)] border border-[var(--color-border)]">{field.type}</span>
                                            <span>Largeur: {field.width}/12</span>
                                            {#if field.placeholder}<span>"{field.placeholder}"</span>{/if}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button class="text-xs px-2 py-1 rounded cursor-pointer transition-colors
                                            {field.is_required == 1 ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)]' : 'bg-[var(--color-border)] text-[var(--color-muted)]'}"
                                            onclick={() => toggleFieldRequired(field)}>
                                            Requis
                                        </button>
                                        <button class="text-xs px-2 py-1 rounded cursor-pointer transition-colors
                                            {field.is_visible == 1 ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]' : 'bg-[var(--color-border)] text-[var(--color-muted)]'}"
                                            onclick={() => toggleFieldVisible(field)}>
                                            Visible
                                        </button>
                                        <button class="text-xs text-[var(--color-destructive)] opacity-0 group-hover:opacity-100 cursor-pointer" onclick={() => deleteField(field.id)}>✕</button>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </Card>
            </div>
        {/if}
    </div>
</div>

<!-- Create Form Dialog -->
{#if showCreateDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showCreateDialog = false}>
        <Card class="w-full max-w-md" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouveau formulaire</h3>
                <Input placeholder="Nom du formulaire" bind:value={newFormName} />
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showCreateDialog = false}>Annuler</Button>
                    <Button onclick={createForm}>Créer</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Add Field Dialog -->
{#if showFieldDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showFieldDialog = false}>
        <Card class="w-full max-w-lg max-h-[80vh] overflow-y-auto" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Ajouter un champ</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom technique</label>
                        <Input placeholder="email" bind:value={newField.name} />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Label</label>
                        <Input placeholder="Adresse email" bind:value={newField.label} />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Type</label>
                    <div class="grid grid-cols-4 gap-2">
                        {#each fieldTypes as ft}
                            <button class="p-2 rounded-[var(--radius)] border text-xs text-center cursor-pointer transition-colors
                                {newField.type === ft.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                                onclick={() => newField.type = ft.value}>
                                <div class="text-base">{ft.icon}</div>
                                <div>{ft.label}</div>
                            </button>
                        {/each}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Placeholder</label>
                        <Input placeholder="Texte d'aide" bind:value={newField.placeholder} />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Largeur (1-12)</label>
                        <Input type="number" min="1" max="12" bind:value={newField.width} />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Texte d'aide</label>
                    <Input placeholder="Aide contextuelle" bind:value={newField.help_text} />
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newField.is_required} /> Obligatoire
                    </label>
                </div>
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showFieldDialog = false}>Annuler</Button>
                    <Button onclick={addField}>Ajouter</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Preview Dialog -->
{#if showPreview && previewData}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showPreview = false}>
        <Card class="w-full max-w-lg max-h-[80vh] overflow-y-auto" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Aperçu : {previewData.name}</h3>
                    <button class="text-[var(--color-muted)] cursor-pointer" onclick={() => showPreview = false}>✕</button>
                </div>
                {#if previewData.description}
                    <p class="text-sm text-[var(--color-muted)]">{previewData.description}</p>
                {/if}
                <div class="grid grid-cols-12 gap-3">
                    {#each previewData.fields || [] as field}
                        <div class="col-span-{field.width}" style="grid-column: span {field.width};">
                            <label class="text-sm font-medium mb-1 block">
                                {field.label}
                                {#if field.is_required}<span class="text-[var(--color-primary)]">*</span>{/if}
                            </label>
                            {#if field.type === 'textarea' || field.type === 'richtext'}
                                <textarea class="flex w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm min-h-[80px]"
                                    placeholder={field.placeholder || ''}></textarea>
                            {:else if field.type === 'select'}
                                <select class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm">
                                    <option value="">{field.placeholder || 'Sélectionner...'}</option>
                                </select>
                            {:else if field.type === 'checkbox'}
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" /> <span class="text-sm">{field.placeholder || field.label}</span>
                                </label>
                            {:else}
                                <input type={field.type === 'phone' ? 'tel' : field.type}
                                    class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm"
                                    placeholder={field.placeholder || ''} />
                            {/if}
                            {#if field.help_text}
                                <p class="text-xs text-[var(--color-muted)] mt-1">{field.help_text}</p>
                            {/if}
                        </div>
                    {/each}
                </div>
                <Button class="w-full">Soumettre (aperçu)</Button>
            </div>
        </Card>
    </div>
{/if}
