<script>
    import { get, post, put, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import AppLayout from '$lib/components/AppLayout.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let tables = $state([]);
    let selectedTable = $state(null);
    let showCreateDialog = $state(false);
    let showColumnDialog = $state(false);
    let newTableName = $state('');
    let newTableDisplay = $state('');
    let newColumn = $state({ name: '', display_name: '', type: 'string', length: 255, is_nullable: false, is_unique: false });
    let loading = $state(true);

    const columnTypes = [
        { value: 'string', label: 'Texte court', icon: 'Aa' },
        { value: 'text', label: 'Texte long', icon: '¶' },
        { value: 'integer', label: 'Entier', icon: '#' },
        { value: 'float', label: 'Décimal', icon: '#.' },
        { value: 'boolean', label: 'Booléen', icon: '✓' },
        { value: 'datetime', label: 'Date/Heure', icon: '📅' },
        { value: 'date', label: 'Date', icon: '📅' },
        { value: 'email', label: 'Email', icon: '@' },
        { value: 'url', label: 'URL', icon: '🔗' },
        { value: 'phone', label: 'Téléphone', icon: '📞' },
        { value: 'json', label: 'JSON', icon: '{}' },
        { value: 'file', label: 'Fichier', icon: '📎' },
    ];

    async function loadTables() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/schema/tables`);
            tables = res.data || [];
        } catch (e) {
            console.error('Erreur:', e);
        }
        loading = false;
    }

    async function createTable() {
        if (!newTableName) return;
        try {
            await post(`/api/t/${tenantSlug}/schema/tables`, {
                name: newTableName,
                display_name: newTableDisplay || newTableName,
            });
            newTableName = '';
            newTableDisplay = '';
            showCreateDialog = false;
            await loadTables();
        } catch (e) {
            console.error('Erreur:', e);
        }
    }

    async function deleteTable(id) {
        if (!confirm('Supprimer cette table et toutes ses données ?')) return;
        await del(`/api/t/${tenantSlug}/schema/tables/${id}`);
        selectedTable = null;
        await loadTables();
    }

    async function addColumn() {
        if (!selectedTable || !newColumn.name) return;
        await post(`/api/t/${tenantSlug}/schema/tables/${selectedTable.id}/columns`, newColumn);
        newColumn = { name: '', display_name: '', type: 'string', length: 255, is_nullable: false, is_unique: false };
        showColumnDialog = false;
        await loadTables();
        selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    async function deleteColumn(colId) {
        await del(`/api/t/${tenantSlug}/schema/columns/${colId}`);
        await loadTables();
        selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    function selectTable(table) {
        selectedTable = table;
    }

    function isSystemCol(name) {
        return ['id', 'created_at', 'updated_at'].includes(name);
    }

    $effect(() => { loadTables(); });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Base de données</h2></header>
<div class="p-6 flex-1 overflow-auto">
<div class="flex h-full gap-4">
    <!-- Sidebar: liste des tables -->
    <div class="w-72 flex-shrink-0 flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide">Tables</h3>
            <Button size="sm" onclick={() => showCreateDialog = true}>+ Table</Button>
        </div>

        {#if loading}
            <p class="text-sm text-[var(--color-muted)]">Chargement...</p>
        {:else if tables.length === 0}
            <Card class="p-4 text-center">
                <p class="text-sm text-[var(--color-muted)]">Aucune table.</p>
                <Button size="sm" class="mt-2" onclick={() => showCreateDialog = true}>Créer une table</Button>
            </Card>
        {:else}
            {#each tables as table}
                <button
                    class="w-full text-left p-3 rounded-[var(--radius)] border transition-colors cursor-pointer
                        {selectedTable?.id === table.id
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10'
                            : 'border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-accent)]'}"
                    onclick={() => selectTable(table)}
                >
                    <div class="font-medium text-sm">{table.display_name}</div>
                    <div class="text-xs text-[var(--color-muted)] mt-0.5">{table.name} — {table.columns?.length ?? 0} colonnes</div>
                </button>
            {/each}
        {/if}
    </div>

    <!-- Main: détail de la table sélectionnée -->
    <div class="flex-1">
        {#if !selectedTable}
            <div class="flex items-center justify-center h-64">
                <p class="text-[var(--color-muted)]">Sélectionnez une table ou créez-en une nouvelle.</p>
            </div>
        {:else}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold">{selectedTable.display_name}</h2>
                        <p class="text-sm text-[var(--color-muted)]">Table : {selectedTable.name}</p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" onclick={() => showColumnDialog = true}>+ Colonne</Button>
                        <Button size="sm" variant="destructive" onclick={() => deleteTable(selectedTable.id)}>Supprimer</Button>
                    </div>
                </div>

                <!-- Colonnes -->
                <Card>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[var(--color-border)]">
                                <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Nom</th>
                                <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Affichage</th>
                                <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Type</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase">PK</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase">Nullable</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase">Unique</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each selectedTable.columns ?? [] as col}
                                <tr class="border-b border-[var(--color-border)] hover:bg-[var(--color-border)]/30">
                                    <td class="p-3 font-mono text-[var(--color-accent)]">{col.name}</td>
                                    <td class="p-3">{col.display_name}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-xs bg-[var(--color-secondary)] border border-[var(--color-border)]">
                                            {col.type}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">{col.is_primary == 1 ? '🔑' : ''}</td>
                                    <td class="p-3 text-center">{col.is_nullable == 1 ? '✓' : ''}</td>
                                    <td class="p-3 text-center">{col.is_unique == 1 ? '✓' : ''}</td>
                                    <td class="p-3 text-right">
                                        {#if !isSystemCol(col.name)}
                                            <button class="text-[var(--color-destructive)] text-xs hover:underline cursor-pointer" onclick={() => deleteColumn(col.id)}>Supprimer</button>
                                        {:else}
                                            <span class="text-xs text-[var(--color-muted)]">système</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </Card>

                <!-- Relations -->
                {#if selectedTable.relations?.length > 0}
                    <Card class="p-4">
                        <h3 class="text-sm font-semibold mb-2">Relations</h3>
                        {#each selectedTable.relations as rel}
                            <div class="text-sm py-1">
                                <span class="text-[var(--color-accent)]">{rel.source_table_name}</span>
                                <span class="text-[var(--color-muted)] mx-1">→ {rel.type} →</span>
                                <span class="text-[var(--color-accent)]">{rel.target_table_name}</span>
                                <span class="text-xs text-[var(--color-muted)] ml-2">(on delete: {rel.on_delete})</span>
                            </div>
                        {/each}
                    </Card>
                {/if}
            </div>
        {/if}
    </div>
</div>

<!-- Dialog: Créer une table -->
{#if showCreateDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showCreateDialog = false}>
        <Card class="w-full max-w-md" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouvelle table</h3>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom technique</label>
                    <Input placeholder="articles" bind:value={newTableName} />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom d'affichage</label>
                    <Input placeholder="Articles" bind:value={newTableDisplay} />
                </div>
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showCreateDialog = false}>Annuler</Button>
                    <Button onclick={createTable}>Créer</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}

<!-- Dialog: Ajouter une colonne -->
{#if showColumnDialog}
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" onclick={() => showColumnDialog = false}>
        <Card class="w-full max-w-lg" onclick={(e) => e.stopPropagation()}>
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouvelle colonne</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom technique</label>
                        <Input placeholder="title" bind:value={newColumn.name} />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom d'affichage</label>
                        <Input placeholder="Titre" bind:value={newColumn.display_name} />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Type</label>
                    <div class="grid grid-cols-4 gap-2">
                        {#each columnTypes as ct}
                            <button
                                class="p-2 rounded-[var(--radius)] border text-xs text-center cursor-pointer transition-colors
                                    {newColumn.type === ct.value
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                                        : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                                onclick={() => newColumn.type = ct.value}
                            >
                                <div class="text-base">{ct.icon}</div>
                                <div>{ct.label}</div>
                            </button>
                        {/each}
                    </div>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newColumn.is_nullable} /> Nullable
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newColumn.is_unique} /> Unique
                    </label>
                </div>
                <div class="flex gap-2 justify-end">
                    <Button variant="secondary" onclick={() => showColumnDialog = false}>Annuler</Button>
                    <Button onclick={addColumn}>Ajouter</Button>
                </div>
            </div>
        </Card>
    </div>
{/if}
</div>
</AppLayout>
