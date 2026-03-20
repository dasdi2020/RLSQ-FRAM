<script>
    import { get, post, del } from '$lib/api/client.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';
    import Input from '$lib/components/ui/Input.svelte';

    let { tenantSlug = 'federation-quebec' } = $props();

    let tables = $state([]);
    let loading = $state(true);
    let positions = $state({}); // tableId -> {x, y}
    let dragging = $state(null);
    let dragOffset = $state({ x: 0, y: 0 });
    let zoom = $state(1);
    let canvasOffset = $state({ x: 0, y: 0 });
    let showCreateDialog = $state(false);
    let newTableName = $state('');
    let svgRef = $state(null);

    async function loadTables() {
        loading = true;
        try {
            const res = await get(`/api/t/${tenantSlug}/schema/tables`);
            tables = res.data || [];
            // Auto-position tables in a grid if no saved positions
            tables.forEach((t, i) => {
                if (!positions[t.id]) {
                    const cols = 3;
                    positions[t.id] = { x: 40 + (i % cols) * 320, y: 40 + Math.floor(i / cols) * 280 };
                }
            });
        } catch (e) { console.error(e); }
        loading = false;
    }

    function startDrag(e, tableId) {
        if (e.button !== 0) return;
        const pos = positions[tableId] || { x: 0, y: 0 };
        dragOffset = { x: e.clientX - pos.x, y: e.clientY - pos.y };
        dragging = tableId;
    }

    function onMouseMove(e) {
        if (dragging !== null) {
            positions[dragging] = {
                x: Math.max(0, e.clientX - dragOffset.x),
                y: Math.max(0, e.clientY - dragOffset.y),
            };
            positions = { ...positions };
        }
    }

    function onMouseUp() { dragging = null; }

    function getRelationLines() {
        const lines = [];
        for (const table of tables) {
            for (const rel of (table.relations || [])) {
                const srcId = rel.source_table_id;
                const tgtId = rel.target_table_id;
                const srcPos = positions[srcId];
                const tgtPos = positions[tgtId];
                if (!srcPos || !tgtPos || srcId === tgtId) continue;

                // Avoid duplicates
                const key = [Math.min(srcId, tgtId), Math.max(srcId, tgtId), rel.type].join('-');
                if (lines.find(l => l.key === key)) continue;

                lines.push({
                    key,
                    x1: srcPos.x + 140, y1: srcPos.y + 20,
                    x2: tgtPos.x + 140, y2: tgtPos.y + 20,
                    type: rel.type,
                    label: rel.type.replace(/_/g, ' '),
                });
            }
        }
        return lines;
    }

    async function createTable() {
        if (!newTableName) return;
        await post(`/api/t/${tenantSlug}/schema/tables`, { name: newTableName, display_name: newTableName });
        newTableName = '';
        showCreateDialog = false;
        await loadTables();
    }

    async function deleteTable(id) {
        if (!confirm('Supprimer cette table ?')) return;
        await del(`/api/t/${tenantSlug}/schema/tables/${id}`);
        delete positions[id];
        await loadTables();
    }

    function getTypeIcon(type) {
        const icons = { string: 'Aa', text: '¶', integer: '#', float: '#.', boolean: '☑', datetime: '📅', date: '📅', email: '✉', json: '{}', file: '📎' };
        return icons[type] || '?';
    }

    $effect(() => { loadTables(); });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">Éditeur ERD</h2>
    <div class="flex items-center gap-2">
        <div class="flex items-center gap-1 bg-[var(--color-secondary)] rounded-[var(--radius)] border border-[var(--color-border)] px-1">
            <button class="w-7 h-7 flex items-center justify-center cursor-pointer text-[var(--color-muted)] hover:text-[var(--color-foreground)]" onclick={() => zoom = Math.max(0.3, zoom - 0.1)}>−</button>
            <span class="text-xs text-[var(--color-muted)] w-10 text-center">{Math.round(zoom * 100)}%</span>
            <button class="w-7 h-7 flex items-center justify-center cursor-pointer text-[var(--color-muted)] hover:text-[var(--color-foreground)]" onclick={() => zoom = Math.min(2, zoom + 0.1)}>+</button>
        </div>
        <Button size="sm" onclick={() => { zoom = 1; }}>Reset</Button>
        <Button size="sm" onclick={() => showCreateDialog = true}>+ Table</Button>
    </div>
</header>

<div class="flex-1 overflow-hidden relative bg-[var(--color-background)]"
     onmousemove={onMouseMove} onmouseup={onMouseUp}
     style="cursor: {dragging !== null ? 'grabbing' : 'default'};">

    {#if loading}
        <div class="flex items-center justify-center h-full text-[var(--color-muted)]">Chargement...</div>
    {:else}
        <!-- Grid background -->
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, var(--color-border) 1px, transparent 1px); background-size: {20 * zoom}px {20 * zoom}px;"></div>

        <!-- Canvas -->
        <div class="absolute inset-0" style="transform: scale({zoom}); transform-origin: 0 0;">
            <!-- SVG for relation lines -->
            <svg class="absolute inset-0 w-full h-full pointer-events-none" style="min-width: 3000px; min-height: 2000px;">
                <defs>
                    <marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--color-primary)" opacity="0.6" />
                    </marker>
                </defs>
                {#each getRelationLines() as line}
                    <!-- Curved connection line -->
                    {@const midX = (line.x1 + line.x2) / 2}
                    {@const midY = (line.y1 + line.y2) / 2}
                    <path
                        d="M {line.x1} {line.y1} C {midX} {line.y1}, {midX} {line.y2}, {line.x2} {line.y2}"
                        fill="none" stroke="var(--color-primary)" stroke-width="2" opacity="0.4" marker-end="url(#arrow)"
                    />
                    <!-- Label -->
                    <rect x={midX - 30} y={midY - 10} width="60" height="20" rx="4" fill="var(--color-card)" stroke="var(--color-border)" />
                    <text x={midX} y={midY + 4} text-anchor="middle" font-size="10" fill="var(--color-muted)" font-family="monospace">{line.label}</text>
                {/each}
            </svg>

            <!-- Table cards -->
            {#each tables as table}
                {@const pos = positions[table.id] || { x: 0, y: 0 }}
                <div class="absolute select-none" style="left: {pos.x}px; top: {pos.y}px; width: 280px;"
                     onmousedown={(e) => startDrag(e, table.id)}>
                    <div class="rounded-lg border-2 overflow-hidden shadow-lg transition-shadow
                        {dragging === table.id ? 'border-[var(--color-primary)] shadow-xl' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                        style="background: var(--color-card);">
                        <!-- Table header -->
                        <div class="px-3 py-2 flex items-center justify-between" style="background: var(--color-primary); color: white;">
                            <div class="flex items-center gap-2">
                                <span class="text-xs">📋</span>
                                <span class="font-bold text-sm">{table.display_name}</span>
                            </div>
                            <div class="flex gap-1">
                                <a href="#/database" class="w-5 h-5 rounded flex items-center justify-center text-xs hover:bg-white/20 cursor-pointer" title="Éditer">✏</a>
                                <button class="w-5 h-5 rounded flex items-center justify-center text-xs hover:bg-white/20 cursor-pointer" onclick={(e) => { e.stopPropagation(); deleteTable(table.id); }} title="Supprimer">✕</button>
                            </div>
                        </div>
                        <div class="text-[10px] px-3 py-1 text-[var(--color-muted)] border-b border-[var(--color-border)] font-mono">{table.name}</div>
                        <!-- Columns -->
                        <div class="max-h-[200px] overflow-y-auto">
                            {#each table.columns || [] as col}
                                <div class="flex items-center gap-2 px-3 py-1.5 text-xs border-b border-[var(--color-border)]/30 hover:bg-[var(--color-border)]/20">
                                    <span class="w-4 text-center opacity-50">{col.is_primary == 1 ? '🔑' : getTypeIcon(col.type)}</span>
                                    <span class="font-mono flex-1 truncate {col.is_primary == 1 ? 'text-[var(--color-primary)] font-bold' : ''}">{col.name}</span>
                                    <span class="text-[var(--color-muted)] text-[10px]">{col.type}</span>
                                    {#if col.is_nullable == 1}<span class="text-[9px] text-[var(--color-muted)]">NULL</span>{/if}
                                </div>
                            {/each}
                        </div>
                        <!-- Footer -->
                        <div class="px-3 py-1.5 text-[10px] text-[var(--color-muted)] flex justify-between">
                            <span>{table.columns?.length || 0} champs</span>
                            <span>{table.relations?.length || 0} relations</span>
                        </div>
                    </div>
                </div>
            {/each}

            {#if tables.length === 0}
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-6xl mb-4 opacity-20">🗄️</span>
                    <p class="text-[var(--color-muted)] text-lg">Aucune table</p>
                    <p class="text-sm text-[var(--color-muted-foreground)]">Cliquez sur "+ Table" pour créer votre première table</p>
                </div>
            {/if}
        </div>
    {/if}
</div>

<!-- Create table dialog -->
<Dialog bind:open={showCreateDialog}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Nouvelle table</h3>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom de la table</label>
            <Input placeholder="articles" bind:value={newTableName} />
        </div>
        <div class="flex gap-2 justify-end">
            <Button variant="secondary" onclick={() => showCreateDialog = false}>Annuler</Button>
            <Button onclick={createTable}>Créer</Button>
        </div>
    </div>
</Dialog>
</AppLayout>
