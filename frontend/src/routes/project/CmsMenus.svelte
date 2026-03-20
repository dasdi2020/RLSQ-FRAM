<script>
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';

    let { projectSlug = '' } = $props();

    let menus = $state([
        { id: 1, name: 'Header', position: 'header', items: [
            { id: 1, label: 'Accueil', url: '/', order: 0 },
            { id: 2, label: 'À propos', url: '/about', order: 1 },
            { id: 3, label: 'Contact', url: '/contact', order: 2 },
        ]},
        { id: 2, name: 'Footer', position: 'footer', items: [
            { id: 4, label: 'Mentions légales', url: '/legal', order: 0 },
            { id: 5, label: 'Politique de confidentialité', url: '/privacy', order: 1 },
        ]},
    ]);

    let selectedMenu = $state(null);
    let showAddItem = $state(false);
    let showCreateMenu = $state(false);
    let newMenuName = $state('');
    let newMenuPosition = $state('header');
    let newItemLabel = $state('');
    let newItemUrl = $state('');

    function selectMenu(menu) { selectedMenu = menu; }

    function addMenuItem() {
        if (!selectedMenu || !newItemLabel) return;
        selectedMenu.items = [...selectedMenu.items, { id: Date.now(), label: newItemLabel, url: newItemUrl || '#', order: selectedMenu.items.length }];
        newItemLabel = ''; newItemUrl = ''; showAddItem = false;
    }

    function removeMenuItem(itemId) {
        if (!selectedMenu) return;
        selectedMenu.items = selectedMenu.items.filter(i => i.id !== itemId);
    }

    function createMenu() {
        if (!newMenuName) return;
        menus = [...menus, { id: Date.now(), name: newMenuName, position: newMenuPosition, items: [] }];
        newMenuName = ''; newMenuPosition = 'header'; showCreateMenu = false;
    }

    function moveItem(index, direction) {
        if (!selectedMenu) return;
        const items = [...selectedMenu.items];
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= items.length) return;
        [items[index], items[newIndex]] = [items[newIndex], items[index]];
        selectedMenu.items = items;
    }
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">Menus</h2>
    <Button size="sm" onclick={() => showCreateMenu = true}>+ Nouveau menu</Button>
</header>

<div class="p-6 flex gap-4">
    <!-- Menu list -->
    <div class="w-64 flex-shrink-0 space-y-2">
        {#each menus as menu}
            <button class="w-full text-left p-3 rounded-[var(--radius)] border cursor-pointer transition-colors
                {selectedMenu?.id === menu.id ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-accent)]'}"
                onclick={() => selectMenu(menu)}>
                <div class="flex items-center justify-between">
                    <span class="font-medium text-sm">{menu.name}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-secondary)] border border-[var(--color-border)]">{menu.position}</span>
                </div>
                <p class="text-xs text-[var(--color-muted)] mt-0.5">{menu.items.length} items</p>
            </button>
        {/each}
    </div>

    <!-- Menu editor -->
    <div class="flex-1">
        {#if !selectedMenu}
            <Card class="p-8 text-center">
                <span class="text-5xl block mb-4">☰</span>
                <p class="text-[var(--color-muted)]">Sélectionnez un menu pour l'éditer</p>
            </Card>
        {:else}
            <Card>
                <div class="px-4 py-3 border-b border-[var(--color-border)] flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold">{selectedMenu.name}</h3>
                        <span class="text-xs text-[var(--color-muted)]">Position : {selectedMenu.position}</span>
                    </div>
                    <Button size="sm" onclick={() => showAddItem = true}>+ Item</Button>
                </div>

                {#if selectedMenu.items.length === 0}
                    <div class="p-8 text-center text-[var(--color-muted)] text-sm">Aucun élément. Cliquez sur "+ Item".</div>
                {:else}
                    <div class="divide-y divide-[var(--color-border)]/50">
                        {#each selectedMenu.items as item, index}
                            <div class="flex items-center gap-3 p-3 hover:bg-[var(--color-border)]/20 group">
                                <div class="flex flex-col gap-0.5">
                                    <button class="text-[10px] text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer" onclick={() => moveItem(index, -1)}>▲</button>
                                    <button class="text-[10px] text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer" onclick={() => moveItem(index, 1)}>▼</button>
                                </div>
                                <span class="w-6 h-6 rounded bg-[var(--color-secondary)] border border-[var(--color-border)] flex items-center justify-center text-[10px] text-[var(--color-muted)]">☰</span>
                                <div class="flex-1">
                                    <span class="font-medium text-sm">{item.label}</span>
                                    <span class="text-xs text-[var(--color-muted)] ml-2 font-mono">{item.url}</span>
                                </div>
                                <button class="text-xs text-[var(--color-destructive)] opacity-0 group-hover:opacity-100 cursor-pointer" onclick={() => removeMenuItem(item.id)}>✕</button>
                            </div>
                        {/each}
                    </div>
                {/if}
            </Card>
        {/if}
    </div>
</div>

<!-- Add menu item -->
<Dialog bind:open={showAddItem}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Ajouter un élément</h3>
        <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Label</label><Input placeholder="Accueil" bind:value={newItemLabel} /></div>
        <div><label class="text-sm text-[var(--color-muted)] mb-1 block">URL</label><Input placeholder="/" bind:value={newItemUrl} /></div>
        <div class="flex gap-2 justify-end"><Button variant="secondary" onclick={() => showAddItem = false}>Annuler</Button><Button onclick={addMenuItem}>Ajouter</Button></div>
    </div>
</Dialog>

<!-- Create menu -->
<Dialog bind:open={showCreateMenu}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Nouveau menu</h3>
        <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label><Input placeholder="Header" bind:value={newMenuName} /></div>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Position</label>
            <div class="flex gap-2">
                {#each ['header', 'footer', 'sidebar'] as pos}
                    <button class="px-3 py-1.5 rounded border text-sm cursor-pointer {newMenuPosition === pos ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-[var(--color-border)] text-[var(--color-muted)]'}" onclick={() => newMenuPosition = pos}>{pos}</button>
                {/each}
            </div>
        </div>
        <div class="flex gap-2 justify-end"><Button variant="secondary" onclick={() => showCreateMenu = false}>Annuler</Button><Button onclick={createMenu}>Créer</Button></div>
    </div>
</Dialog>
