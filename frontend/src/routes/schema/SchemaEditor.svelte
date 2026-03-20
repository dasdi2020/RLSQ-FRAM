<script>
    import { get, post, put, del } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';
    import AppLayout from '$lib/components/AppLayout.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let tables = $state([]);
    let selectedTable = $state(null);
    let showCreateDialog = $state(false);
    let showColumnDialog = $state(false);
    let newTableName = $state('');
    let newTableDisplay = $state('');
    let loading = $state(true);

    // Column creation — multi-step (Strapi style)
    let columnStep = $state('category'); // 'category' → 'type' → 'config'
    let columnCategory = $state('');
    let newColumn = $state({ name: '', display_name: '', type: 'string', length: 255, is_nullable: false, is_unique: false, is_indexed: false, default_value: '' });

    // Relations
    let showRelationDialog = $state(false);
    let newRelation = $state({ type: 'one_to_many', target_table_id: '', source_column: '', target_column: '', on_delete: 'cascade' });

    const fieldCategories = [
        { id: 'text', label: 'Texte', icon: 'Aa', desc: 'Champs texte, email, URL...', color: '#6cb2eb' },
        { id: 'number', label: 'Nombre', icon: '#', desc: 'Entier, décimal, pourcentage...', color: '#2ecc71' },
        { id: 'date', label: 'Date & Heure', icon: '📅', desc: 'Date, heure, timestamp...', color: '#f39c12' },
        { id: 'boolean', label: 'Booléen', icon: '☑', desc: 'Vrai/Faux, oui/non, toggle', color: '#9b59b6' },
        { id: 'media', label: 'Média', icon: '📎', desc: 'Fichier, image, vidéo...', color: '#e74c3c' },
        { id: 'relation', label: 'Relation', icon: '🔗', desc: 'Lien vers une autre table', color: '#ff3e00' },
        { id: 'advanced', label: 'Avancé', icon: '⚙', desc: 'JSON, UUID, slug, enum...', color: '#1abc9c' },
    ];

    const fieldTypesByCategory = {
        text: [
            { value: 'string', label: 'Texte court', icon: 'Aa', desc: 'Titre, nom, etc. (max 255 car.)' },
            { value: 'text', label: 'Texte long', icon: '¶', desc: 'Description, commentaire (illimité)' },
            { value: 'richtext', label: 'Texte riche', icon: '✎', desc: 'Contenu HTML avec éditeur WYSIWYG' },
            { value: 'email', label: 'Email', icon: '✉', desc: 'Adresse email avec validation' },
            { value: 'url', label: 'URL', icon: '🌐', desc: 'Lien web avec validation' },
            { value: 'phone', label: 'Téléphone', icon: '📞', desc: 'Numéro de téléphone' },
            { value: 'password', label: 'Mot de passe', icon: '🔒', desc: 'Champ masqué et hashé' },
            { value: 'slug', label: 'Slug', icon: '🏷', desc: 'Identifiant URL-friendly auto-généré' },
            { value: 'color', label: 'Couleur', icon: '🎨', desc: 'Sélecteur de couleur hexadécimal' },
        ],
        number: [
            { value: 'integer', label: 'Entier', icon: '#', desc: 'Nombre entier (1, 42, -7)' },
            { value: 'float', label: 'Décimal', icon: '#.', desc: 'Nombre à virgule (3.14, 19.99)' },
            { value: 'decimal', label: 'Monétaire', icon: '$', desc: 'Précision financière (2 décimales)' },
            { value: 'percentage', label: 'Pourcentage', icon: '%', desc: 'Valeur entre 0 et 100' },
            { value: 'rating', label: 'Note', icon: '★', desc: 'Notation étoiles (1-5)' },
        ],
        date: [
            { value: 'date', label: 'Date', icon: '📅', desc: 'Jour, mois, année (2026-03-20)' },
            { value: 'datetime', label: 'Date & Heure', icon: '🕐', desc: 'Date + heure (2026-03-20 14:30)' },
            { value: 'time', label: 'Heure', icon: '⏰', desc: 'Heure uniquement (14:30:00)' },
            { value: 'timestamp', label: 'Timestamp', icon: '⏱', desc: 'Horodatage Unix' },
        ],
        boolean: [
            { value: 'boolean', label: 'Booléen', icon: '☑', desc: 'Vrai ou Faux (checkbox)' },
            { value: 'toggle', label: 'Toggle', icon: '🔘', desc: 'Interrupteur on/off' },
        ],
        media: [
            { value: 'file', label: 'Fichier', icon: '📎', desc: 'Upload de fichier quelconque' },
            { value: 'image', label: 'Image', icon: '🖼', desc: 'Image avec preview (jpg, png, svg)' },
            { value: 'video', label: 'Vidéo', icon: '🎬', desc: 'Fichier vidéo ou URL YouTube' },
            { value: 'document', label: 'Document', icon: '📄', desc: 'PDF, Word, Excel...' },
        ],
        relation: [
            { value: 'one_to_one', label: 'Un à Un', icon: '1↔1', desc: 'Un enregistrement lié à un seul autre' },
            { value: 'one_to_many', label: 'Un à Plusieurs', icon: '1→N', desc: 'Un enregistrement a plusieurs enfants' },
            { value: 'many_to_one', label: 'Plusieurs à Un', icon: 'N→1', desc: 'Plusieurs enregistrements liés à un parent' },
            { value: 'many_to_many', label: 'Plusieurs à Plusieurs', icon: 'N↔N', desc: 'Relation croisée avec table pivot' },
        ],
        advanced: [
            { value: 'json', label: 'JSON', icon: '{}', desc: 'Données structurées libres' },
            { value: 'enum', label: 'Énumération', icon: '☰', desc: 'Liste de valeurs prédéfinies' },
            { value: 'uuid', label: 'UUID', icon: '🆔', desc: 'Identifiant unique universel auto-généré' },
            { value: 'ip', label: 'Adresse IP', icon: '🌍', desc: 'IPv4 ou IPv6' },
            { value: 'markdown', label: 'Markdown', icon: 'MD', desc: 'Texte formaté en Markdown' },
        ],
    };

    const relationTypes = [
        { value: 'one_to_one', label: 'Un à Un (1:1)', icon: '1 ↔ 1', desc: 'Chaque enregistrement est lié à exactement un autre', example: 'User ↔ Profile' },
        { value: 'one_to_many', label: 'Un à Plusieurs (1:N)', icon: '1 → N', desc: 'Un parent a plusieurs enfants', example: 'Category → Articles' },
        { value: 'many_to_one', label: 'Plusieurs à Un (N:1)', icon: 'N → 1', desc: 'Plusieurs enregistrements pointent vers un parent', example: 'Articles → Author' },
        { value: 'many_to_many', label: 'Plusieurs à Plusieurs (N:N)', icon: 'N ↔ N', desc: 'Relation croisée via table pivot', example: 'Articles ↔ Tags' },
    ];

    async function loadTables() {
        loading = true;
        try { const res = await get(`/api/t/${tenantSlug}/schema/tables`); tables = res.data || []; } catch (e) { console.error(e); }
        loading = false;
    }

    async function createTable() {
        if (!newTableName) return;
        try {
            await post(`/api/t/${tenantSlug}/schema/tables`, { name: newTableName, display_name: newTableDisplay || newTableName });
            newTableName = ''; newTableDisplay = ''; showCreateDialog = false;
            await loadTables();
        } catch (e) { console.error(e); }
    }

    async function deleteTable(id) {
        if (!confirm('Supprimer cette table et toutes ses données ?')) return;
        await del(`/api/t/${tenantSlug}/schema/tables/${id}`);
        selectedTable = null; await loadTables();
    }

    function openColumnDialog() {
        columnStep = 'category'; columnCategory = '';
        newColumn = { name: '', display_name: '', type: 'string', length: 255, is_nullable: false, is_unique: false, is_indexed: false, default_value: '' };
        showColumnDialog = true;
    }

    function selectCategory(catId) {
        columnCategory = catId;
        if (catId === 'relation') {
            showColumnDialog = false;
            newRelation = { type: 'one_to_many', target_table_id: '', source_column: '', target_column: '', on_delete: 'cascade' };
            showRelationDialog = true;
        } else {
            columnStep = 'type';
        }
    }

    function selectType(type) {
        newColumn.type = type;
        columnStep = 'config';
    }

    async function addColumn() {
        if (!selectedTable || !newColumn.name) return;
        await post(`/api/t/${tenantSlug}/schema/tables/${selectedTable.id}/columns`, newColumn);
        showColumnDialog = false; await loadTables();
        selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    async function addRelation() {
        if (!selectedTable || !newRelation.target_table_id) return;
        await post(`/api/t/${tenantSlug}/schema/relations`, {
            source_table_id: selectedTable.id,
            ...newRelation,
        });
        showRelationDialog = false; await loadTables();
        selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    async function deleteColumn(colId) {
        await del(`/api/t/${tenantSlug}/schema/columns/${colId}`);
        await loadTables(); selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    async function deleteRelation(relId) {
        await del(`/api/t/${tenantSlug}/schema/relations/${relId}`);
        await loadTables(); selectedTable = tables.find(t => t.id === selectedTable.id);
    }

    function isSystemCol(name) { return ['id', 'created_at', 'updated_at'].includes(name); }

    function getTypeInfo(type) {
        for (const cat of Object.values(fieldTypesByCategory)) {
            const found = cat.find(t => t.value === type);
            if (found) return found;
        }
        return { icon: '?', label: type };
    }

    $effect(() => { loadTables(); });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Base de données</h2></header>
<div class="p-6 flex-1 overflow-auto">
<div class="flex h-full gap-4">
    <!-- Sidebar: tables -->
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
                <button class="w-full text-left p-3 rounded-[var(--radius)] border transition-colors cursor-pointer
                    {selectedTable?.id === table.id ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-accent)]'}"
                    onclick={() => { selectedTable = table; }}>
                    <div class="font-medium text-sm">{table.display_name}</div>
                    <div class="text-xs text-[var(--color-muted)] mt-0.5">{table.name} — {table.columns?.length ?? 0} col. — {table.relations?.length ?? 0} rel.</div>
                </button>
            {/each}
        {/if}
    </div>

    <!-- Main -->
    <div class="flex-1 min-w-0">
        {#if !selectedTable}
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <span class="text-5xl mb-4 opacity-30">🗄️</span>
                <p class="text-[var(--color-muted)]">Sélectionnez une table ou créez-en une nouvelle.</p>
            </div>
        {:else}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold">{selectedTable.display_name}</h2>
                        <p class="text-sm text-[var(--color-muted)]">Table : <code class="font-mono bg-[var(--color-secondary)] px-1.5 py-0.5 rounded text-xs">{selectedTable.name}</code></p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" onclick={openColumnDialog}>+ Champ</Button>
                        <Button size="sm" variant="secondary" onclick={() => { newRelation = { type: 'one_to_many', target_table_id: '', on_delete: 'cascade' }; showRelationDialog = true; }}>+ Relation</Button>
                        <Button size="sm" variant="destructive" onclick={() => deleteTable(selectedTable.id)}>Supprimer</Button>
                    </div>
                </div>

                <!-- Colonnes -->
                <Card>
                    <div class="px-4 py-3 border-b border-[var(--color-border)] flex items-center justify-between">
                        <h3 class="text-sm font-semibold">Champs ({selectedTable.columns?.length ?? 0})</h3>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[var(--color-border)] bg-[var(--color-secondary)]/30">
                                <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Champ</th>
                                <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Type</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase font-medium w-16">PK</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase font-medium w-16">Null</th>
                                <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase font-medium w-16">Uniq</th>
                                <th class="p-3 w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each selectedTable.columns ?? [] as col}
                                <tr class="border-b border-[var(--color-border)]/50 hover:bg-[var(--color-border)]/20 group">
                                    <td class="p-3">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded-md bg-[var(--color-secondary)] border border-[var(--color-border)] flex items-center justify-center text-xs">{getTypeInfo(col.type).icon}</span>
                                            <div>
                                                <span class="font-mono text-[var(--color-accent)] text-sm">{col.name}</span>
                                                {#if col.display_name !== col.name}
                                                    <span class="text-xs text-[var(--color-muted)] ml-1">({col.display_name})</span>
                                                {/if}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded-md text-xs font-medium bg-[var(--color-secondary)] border border-[var(--color-border)]">{getTypeInfo(col.type).label}</span>
                                    </td>
                                    <td class="p-3 text-center">{col.is_primary == 1 ? '🔑' : ''}</td>
                                    <td class="p-3 text-center">{col.is_nullable == 1 ? '✓' : '—'}</td>
                                    <td class="p-3 text-center">{col.is_unique == 1 ? '✓' : '—'}</td>
                                    <td class="p-3 text-right">
                                        {#if !isSystemCol(col.name)}
                                            <button class="text-[var(--color-destructive)] text-xs opacity-0 group-hover:opacity-100 cursor-pointer hover:underline" onclick={() => deleteColumn(col.id)}>Supprimer</button>
                                        {:else}
                                            <span class="text-xs text-[var(--color-muted-foreground)]">auto</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </Card>

                <!-- Relations -->
                <Card>
                    <div class="px-4 py-3 border-b border-[var(--color-border)] flex items-center justify-between">
                        <h3 class="text-sm font-semibold">Relations ({selectedTable.relations?.length ?? 0})</h3>
                    </div>
                    {#if (selectedTable.relations?.length ?? 0) === 0}
                        <div class="p-6 text-center text-sm text-[var(--color-muted)]">Aucune relation.</div>
                    {:else}
                        <div class="divide-y divide-[var(--color-border)]/50">
                            {#each selectedTable.relations as rel}
                                <div class="flex items-center justify-between p-3 hover:bg-[var(--color-border)]/20 group">
                                    <div class="flex items-center gap-3">
                                        <span class="w-8 h-8 rounded-md bg-[var(--color-primary)]/10 border border-[var(--color-primary)]/20 flex items-center justify-center text-xs font-bold text-[var(--color-primary)]">
                                            {rel.type === 'one_to_one' ? '1:1' : rel.type === 'one_to_many' ? '1:N' : rel.type === 'many_to_one' ? 'N:1' : 'N:N'}
                                        </span>
                                        <div>
                                            <div class="text-sm">
                                                <span class="font-mono text-[var(--color-accent)]">{rel.source_table_name}</span>
                                                <span class="text-[var(--color-muted)] mx-2">→</span>
                                                <span class="font-mono text-[var(--color-accent)]">{rel.target_table_name}</span>
                                            </div>
                                            <div class="text-xs text-[var(--color-muted)]">{rel.type.replace(/_/g, ' ')} — on delete: {rel.on_delete}</div>
                                        </div>
                                    </div>
                                    <button class="text-[var(--color-destructive)] text-xs opacity-0 group-hover:opacity-100 cursor-pointer hover:underline" onclick={() => deleteRelation(rel.id)}>Supprimer</button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </Card>
            </div>
        {/if}
    </div>
</div>

<!-- ==================== DIALOGS ==================== -->

<!-- Dialog: Créer une table -->
<Dialog bind:open={showCreateDialog}>
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
            <Button onclick={createTable}>Créer la table</Button>
        </div>
    </div>
</Dialog>

<!-- Dialog: Ajouter un champ (multi-step Strapi style) -->
<Dialog bind:open={showColumnDialog} class="max-w-2xl">
    <div class="flex flex-col" style="min-height: 480px;">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-[var(--color-border)] flex items-center justify-between">
            <div class="flex items-center gap-3">
                {#if columnStep !== 'category'}
                    <button class="text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer" onclick={() => columnStep = columnStep === 'config' ? 'type' : 'category'}>
                        &#8592;
                    </button>
                {/if}
                <h3 class="text-lg font-semibold">
                    {columnStep === 'category' ? 'Choisir une catégorie' : columnStep === 'type' ? 'Choisir le type' : 'Configurer le champ'}
                </h3>
            </div>
            <!-- Steps indicator -->
            <div class="flex items-center gap-1.5">
                {#each ['category', 'type', 'config'] as step, i}
                    <div class="w-2.5 h-2.5 rounded-full transition-colors {columnStep === step ? 'bg-[var(--color-primary)]' : i < ['category','type','config'].indexOf(columnStep) ? 'bg-[var(--color-primary)]/40' : 'bg-[var(--color-border)]'}"></div>
                {/each}
            </div>
        </div>

        <!-- Step 1: Catégorie -->
        {#if columnStep === 'category'}
            <div class="p-6 flex-1">
                <p class="text-sm text-[var(--color-muted)] mb-4">Quel type de données voulez-vous stocker ?</p>
                <div class="grid grid-cols-2 gap-3">
                    {#each fieldCategories as cat}
                        <button class="flex items-start gap-3 p-4 rounded-[var(--radius)] border border-[var(--color-border)] hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 cursor-pointer transition-all text-left group"
                            onclick={() => selectCategory(cat.id)}>
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg flex-shrink-0" style="background: {cat.color}15; color: {cat.color};">
                                {cat.icon}
                            </div>
                            <div>
                                <div class="font-medium text-sm group-hover:text-[var(--color-primary)]">{cat.label}</div>
                                <div class="text-xs text-[var(--color-muted)] mt-0.5">{cat.desc}</div>
                            </div>
                        </button>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Step 2: Type -->
        {#if columnStep === 'type'}
            <div class="p-6 flex-1">
                <p class="text-sm text-[var(--color-muted)] mb-4">
                    <span class="capitalize font-medium text-[var(--color-foreground)]">{fieldCategories.find(c => c.id === columnCategory)?.label}</span> — choisissez le type exact :
                </p>
                <div class="grid grid-cols-1 gap-2">
                    {#each fieldTypesByCategory[columnCategory] || [] as ft}
                        <button class="flex items-center gap-3 p-3 rounded-[var(--radius)] border border-[var(--color-border)] hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 cursor-pointer transition-all text-left"
                            onclick={() => selectType(ft.value)}>
                            <div class="w-9 h-9 rounded-lg bg-[var(--color-secondary)] border border-[var(--color-border)] flex items-center justify-center text-sm flex-shrink-0 font-bold">
                                {ft.icon}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm">{ft.label}</div>
                                <div class="text-xs text-[var(--color-muted)]">{ft.desc}</div>
                            </div>
                            <span class="text-[var(--color-muted)] text-xs">→</span>
                        </button>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Step 3: Configuration -->
        {#if columnStep === 'config'}
            <div class="p-6 flex-1 space-y-4">
                <div class="flex items-center gap-2 px-3 py-2 rounded-[var(--radius)] bg-[var(--color-secondary)] border border-[var(--color-border)]">
                    <span class="text-base">{getTypeInfo(newColumn.type).icon}</span>
                    <span class="font-medium text-sm">{getTypeInfo(newColumn.type).label}</span>
                    <span class="text-xs text-[var(--color-muted)] ml-auto">{newColumn.type}</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom technique <span class="text-[var(--color-destructive)]">*</span></label>
                        <Input placeholder="title" bind:value={newColumn.name} />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom d'affichage</label>
                        <Input placeholder="Titre" bind:value={newColumn.display_name} />
                    </div>
                </div>

                {#if ['string', 'email', 'url', 'phone', 'slug', 'color', 'password', 'ip'].includes(newColumn.type)}
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Longueur max</label>
                        <Input type="number" bind:value={newColumn.length} placeholder="255" />
                    </div>
                {/if}

                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Valeur par défaut</label>
                    <Input placeholder="(vide)" bind:value={newColumn.default_value} />
                </div>

                <div class="flex flex-wrap gap-x-6 gap-y-2 pt-1">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newColumn.is_nullable} class="accent-[var(--color-primary)]" /> Nullable
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newColumn.is_unique} class="accent-[var(--color-primary)]" /> Unique
                    </label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" bind:checked={newColumn.is_indexed} class="accent-[var(--color-primary)]" /> Indexé
                    </label>
                </div>

                <div class="flex gap-2 justify-end pt-2 border-t border-[var(--color-border)]">
                    <Button variant="secondary" onclick={() => showColumnDialog = false}>Annuler</Button>
                    <Button onclick={addColumn} disabled={!newColumn.name}>Ajouter le champ</Button>
                </div>
            </div>
        {/if}
    </div>
</Dialog>

<!-- Dialog: Ajouter une relation -->
<Dialog bind:open={showRelationDialog} class="max-w-xl">
    <div class="p-6 space-y-5">
        <h3 class="text-lg font-semibold">Nouvelle relation</h3>

        <!-- Relation type selector -->
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-2 block">Type de relation</label>
            <div class="grid grid-cols-2 gap-2">
                {#each relationTypes as rt}
                    <button class="p-3 rounded-[var(--radius)] border text-left cursor-pointer transition-all
                        {newRelation.type === rt.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                        onclick={() => newRelation.type = rt.value}>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-[var(--color-primary)] bg-[var(--color-primary)]/10 px-2 py-0.5 rounded">{rt.icon}</span>
                            <span class="font-medium text-sm">{rt.label}</span>
                        </div>
                        <p class="text-xs text-[var(--color-muted)] mt-1">{rt.desc}</p>
                        <p class="text-xs text-[var(--color-accent)] mt-0.5 italic">{rt.example}</p>
                    </button>
                {/each}
            </div>
        </div>

        <!-- Target table -->
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Table cible</label>
            <select class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm"
                bind:value={newRelation.target_table_id}>
                <option value="">Sélectionner une table...</option>
                {#each tables.filter(t => t.id !== selectedTable?.id) as t}
                    <option value={t.id}>{t.display_name} ({t.name})</option>
                {/each}
            </select>
        </div>

        <!-- On Delete -->
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">À la suppression</label>
            <div class="flex gap-2">
                {#each ['cascade', 'set_null', 'restrict'] as action}
                    <button class="px-3 py-1.5 rounded-[var(--radius)] border text-xs font-medium cursor-pointer transition-colors
                        {newRelation.on_delete === action ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-[var(--color-border)] text-[var(--color-muted)]'}"
                        onclick={() => newRelation.on_delete = action}>
                        {action === 'cascade' ? '🗑 Cascade' : action === 'set_null' ? '∅ Set null' : '🚫 Restreindre'}
                    </button>
                {/each}
            </div>
        </div>

        <!-- Visual preview -->
        {#if newRelation.target_table_id}
            <div class="p-4 rounded-[var(--radius)] bg-[var(--color-secondary)] border border-[var(--color-border)]">
                <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide mb-2">Aperçu</p>
                <div class="flex items-center justify-center gap-4 text-sm">
                    <div class="px-3 py-2 rounded bg-[var(--color-card)] border border-[var(--color-border)] font-mono">{selectedTable?.name}</div>
                    <div class="text-[var(--color-primary)] font-bold">
                        {newRelation.type === 'one_to_one' ? '1 ↔ 1' : newRelation.type === 'one_to_many' ? '1 → N' : newRelation.type === 'many_to_one' ? 'N → 1' : 'N ↔ N'}
                    </div>
                    <div class="px-3 py-2 rounded bg-[var(--color-card)] border border-[var(--color-border)] font-mono">{tables.find(t => t.id == newRelation.target_table_id)?.name ?? '?'}</div>
                </div>
            </div>
        {/if}

        <div class="flex gap-2 justify-end pt-2 border-t border-[var(--color-border)]">
            <Button variant="secondary" onclick={() => showRelationDialog = false}>Annuler</Button>
            <Button onclick={addRelation} disabled={!newRelation.target_table_id}>Créer la relation</Button>
        </div>
    </div>
</Dialog>
</div>
</AppLayout>
