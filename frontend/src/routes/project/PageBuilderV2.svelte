<script>
    import { getBuilder } from '$lib/stores/builder.svelte.js';
    import { get, post, put } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { projectSlug = '', pageId = '' } = $props();
    const builder = getBuilder();

    let pageName = $state('');
    let saving = $state(false);
    let previewMode = $state(false);
    let previewDevice = $state('desktop');
    let dragOverId = $state(null);
    let dragOverIndex = $state(-1);

    const deviceWidths = { desktop: '100%', tablet: '768px', mobile: '375px' };

    // Block palette
    const blockCategories = [
        { id: 'layout', label: 'Mise en page', blocks: [
            { type: 'section', icon: '📦', label: 'Section' },
            { type: 'container', icon: '📐', label: 'Container' },
            { type: 'columns', icon: '▥', label: 'Colonnes' },
        ]},
        { id: 'content', label: 'Contenu', blocks: [
            { type: 'heading', icon: 'H', label: 'Titre' },
            { type: 'text', icon: '¶', label: 'Texte' },
            { type: 'image', icon: '🖼', label: 'Image' },
            { type: 'video', icon: '🎬', label: 'Vidéo' },
            { type: 'button', icon: '▶', label: 'Bouton' },
            { type: 'divider', icon: '—', label: 'Séparateur' },
            { type: 'spacer', icon: '↕', label: 'Espace' },
            { type: 'html', icon: '<>', label: 'HTML' },
        ]},
        { id: 'sections', label: 'Sections pré-faites', blocks: [
            { type: 'hero', icon: '🦸', label: 'Hero' },
            { type: 'navbar', icon: '🧭', label: 'Navbar' },
            { type: 'footer', icon: '🔻', label: 'Footer' },
            { type: 'card', icon: '🃏', label: 'Carte' },
            { type: 'testimonial', icon: '💬', label: 'Témoignage' },
            { type: 'pricing', icon: '💰', label: 'Tarifs' },
            { type: 'faq', icon: '❓', label: 'FAQ' },
            { type: 'gallery', icon: '🖼️', label: 'Galerie' },
        ]},
        { id: 'interactive', label: 'Interactif', blocks: [
            { type: 'form', icon: '📝', label: 'Formulaire' },
            { type: 'map', icon: '🗺️', label: 'Carte' },
            { type: 'countdown', icon: '⏱', label: 'Compte à rebours' },
            { type: 'social', icon: '📱', label: 'Réseaux sociaux' },
        ]},
    ];

    // Load page data
    async function loadPage() {
        if (!pageId) { builder.load([]); return; }
        try {
            const res = await get(`/api/t/${projectSlug}/pages/${pageId}`);
            if (res.data) {
                pageName = res.data.name || '';
                // Charger les composants comme des blocks
                const comps = res.data.components || [];
                const blocks = comps.map(c => ({
                    id: 'b_' + c.id, type: c.type, props: c.props || {}, styles: c.styles || {},
                    children: [], content: c.content || '',
                }));
                builder.load(blocks);
            }
        } catch { builder.load([]); }
    }

    async function savePage() {
        saving = true;
        try {
            const html = builder.toHTML();
            // Save blocks as JSON and HTML
            await put(`/api/t/${projectSlug}/pages/${pageId}`, { name: pageName });
            // TODO: save blocks JSON to page
        } catch {}
        saving = false;
    }

    // Drag handlers
    function onDragStartPalette(e, type) {
        builder.setDragType(type);
        e.dataTransfer.setData('text/plain', type);
        e.dataTransfer.effectAllowed = 'copy';
    }

    function onDragOver(e, parentId = null, index = 0) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        dragOverId = parentId;
        dragOverIndex = index;
    }

    function onDrop(e, parentId = null, index = -1) {
        e.preventDefault();
        const type = e.dataTransfer.getData('text/plain') || builder.dragType;
        if (type) {
            builder.addBlock(type, parentId, index);
        }
        builder.clearDragType();
        dragOverId = null;
        dragOverIndex = -1;
    }

    function onDragLeave() { dragOverId = null; dragOverIndex = -1; }

    function renderBlockPreview(block) {
        const p = block.props || {};
        const s = block.styles || {};
        const style = Object.entries(s).map(([k,v]) => `${k.replace(/([A-Z])/g,'-$1').toLowerCase()}:${v}`).join(';');
        switch (block.type) {
            case 'heading': return `<h${p.level||2} style="margin:0;${style}">${esc(p.text||'Titre')}</h${p.level||2}>`;
            case 'text': return `<p style="margin:0;color:var(--color-muted);${style}">${esc(p.text||'Texte...')}</p>`;
            case 'image': return p.src ? `<img src="${esc(p.src)}" alt="${esc(p.alt||'')}" style="max-width:100%;border-radius:4px" />` : `<div style="background:var(--color-border);padding:20px;text-align:center;border-radius:4px;color:var(--color-muted)">🖼 Image</div>`;
            case 'button': return `<span style="display:inline-block;padding:8px 16px;background:var(--color-primary);color:#fff;border-radius:4px;font-size:13px">${esc(p.text||'Bouton')}</span>`;
            case 'divider': return '<hr style="border-color:var(--color-border);margin:8px 0" />';
            case 'spacer': return `<div style="height:${p.height||40}px;background:repeating-linear-gradient(45deg,transparent,transparent 5px,var(--color-border) 5px,var(--color-border) 6px);opacity:0.3;border-radius:4px"></div>`;
            case 'hero': return `<div style="background:${p.bgColor||'#1a1a2e'};color:${p.textColor||'#fff'};padding:30px;text-align:center;border-radius:4px"><h2 style="margin:0">${esc(p.title||'')}</h2><p style="opacity:0.8;margin:4px 0">${esc(p.subtitle||'')}</p></div>`;
            case 'navbar': return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--color-card);border:1px solid var(--color-border);border-radius:4px"><b style="font-size:13px">${esc(p.brand||'')}</b><span style="font-size:11px;color:var(--color-muted)">${(p.links||[]).map(l=>l.text).join(' | ')}</span></div>`;
            case 'footer': return `<div style="text-align:center;padding:12px;color:var(--color-muted);font-size:12px;border-top:1px solid var(--color-border)">${esc(p.text||'')}</div>`;
            case 'card': return `<div style="border:1px solid var(--color-border);border-radius:8px;padding:16px"><h4 style="margin:0 0 4px">${esc(p.title||'')}</h4><p style="margin:0;font-size:12px;color:var(--color-muted)">${esc(p.description||'')}</p></div>`;
            case 'testimonial': return `<div style="border-left:3px solid var(--color-primary);padding:12px;font-style:italic"><p style="margin:0">"${esc(p.quote||'')}"</p><p style="margin:4px 0 0;font-size:12px;color:var(--color-muted)">— ${esc(p.author||'')}${p.role?', '+esc(p.role):''}</p></div>`;
            case 'pricing': return `<div style="border:1px solid var(--color-border);border-radius:8px;padding:16px;text-align:center"><h4 style="margin:0">${esc(p.title||'')}</h4><p style="font-size:24px;font-weight:bold;margin:8px 0">${esc(p.price||'')}$<span style="font-size:12px;color:var(--color-muted)">${esc(p.period||'')}</span></p></div>`;
            case 'section': case 'container': case 'columns': case 'column': return `<div style="font-size:10px;color:var(--color-muted);text-transform:uppercase;letter-spacing:1px">${block.type}</div>`;
            case 'html': return `<div style="background:var(--color-secondary);padding:8px;border-radius:4px;font-family:monospace;font-size:11px;color:var(--color-muted);max-height:60px;overflow:hidden">${esc((p.code||'').substring(0,100))}</div>`;
            case 'form': return `<div style="border:1px dashed var(--color-border);padding:16px;text-align:center;border-radius:4px;color:var(--color-muted)">📝 Formulaire${p.formSlug?': '+esc(p.formSlug):''}</div>`;
            default: return `<div style="padding:8px;color:var(--color-muted);font-size:12px">[${block.type}]</div>`;
        }
    }
    function esc(s) { return (s||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    $effect(() => { loadPage(); });
</script>

<div class="flex h-screen overflow-hidden" style="background: var(--color-background);">
    <!-- LEFT: Block Palette -->
    <div class="w-64 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col overflow-hidden">
        <div class="p-3 border-b border-[var(--color-border)] flex items-center justify-between">
            <a href="#/p/{projectSlug}/pages" class="text-xs text-[var(--color-muted)] hover:text-[var(--color-foreground)]">← Retour</a>
            <span class="text-xs font-medium">Blocs</span>
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-3">
            {#each blockCategories as cat}
                <div>
                    <p class="px-2 text-[10px] text-[var(--color-muted-foreground)] uppercase tracking-wider mb-1">{cat.label}</p>
                    <div class="grid grid-cols-3 gap-1">
                        {#each cat.blocks as block}
                            <div class="flex flex-col items-center gap-1 p-2 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] cursor-grab hover:border-[var(--color-primary)] hover:bg-[var(--color-primary)]/5 transition-colors text-center"
                                 draggable="true"
                                 ondragstart={(e) => onDragStartPalette(e, block.type)}>
                                <span class="text-lg">{block.icon}</span>
                                <span class="text-[10px] leading-tight text-[var(--color-muted)]">{block.label}</span>
                            </div>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>
    </div>

    <!-- CENTER: Canvas -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Toolbar -->
        <div class="h-12 border-b border-[var(--color-border)] bg-[var(--color-secondary)] flex items-center justify-between px-4">
            <div class="flex items-center gap-2">
                <button class="w-7 h-7 rounded flex items-center justify-center text-sm cursor-pointer hover:bg-[var(--color-border)] disabled:opacity-30"
                    disabled={!builder.canUndo} onclick={() => builder.undo()}>↩</button>
                <button class="w-7 h-7 rounded flex items-center justify-center text-sm cursor-pointer hover:bg-[var(--color-border)] disabled:opacity-30"
                    disabled={!builder.canRedo} onclick={() => builder.redo()}>↪</button>
                <span class="text-xs text-[var(--color-muted)] mx-2">|</span>
                <span class="text-xs text-[var(--color-muted)]">{builder.blocks.length} blocs</span>
            </div>

            <div class="flex items-center gap-1">
                {#each ['desktop', 'tablet', 'mobile'] as device}
                    <button class="px-2 py-1 rounded text-xs cursor-pointer {previewDevice === device ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:bg-[var(--color-border)]'}"
                        onclick={() => previewDevice = device}>
                        {device === 'desktop' ? '🖥' : device === 'tablet' ? '📱' : '📲'}
                    </button>
                {/each}
            </div>

            <div class="flex items-center gap-2">
                <Button size="sm" variant="secondary" onclick={() => previewMode = !previewMode}>
                    {previewMode ? '✏️ Éditer' : '👁 Preview'}
                </Button>
                <Button size="sm" onclick={savePage} disabled={saving}>
                    {saving ? '...' : '💾 Sauvegarder'}
                </Button>
            </div>
        </div>

        <!-- Canvas area -->
        <div class="flex-1 overflow-auto p-6 flex justify-center" style="background: var(--color-background);">
            <div style="width: {deviceWidths[previewDevice]}; max-width: 100%; transition: width 0.3s;">
                {#if previewMode}
                    <!-- Preview mode -->
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden min-h-[400px]">
                        {@html builder.toHTML()}
                    </div>
                {:else}
                    <!-- Edit mode -->
                    <div class="bg-[var(--color-card)] rounded-lg border-2 border-dashed border-[var(--color-border)] min-h-[400px] p-4"
                         ondragover={(e) => onDragOver(e, null, builder.blocks.length)}
                         ondrop={(e) => onDrop(e, null, builder.blocks.length)}
                         ondragleave={onDragLeave}>

                        {#if builder.blocks.length === 0}
                            <div class="flex flex-col items-center justify-center h-64 text-center"
                                 ondragover={(e) => onDragOver(e, null, 0)}
                                 ondrop={(e) => onDrop(e, null, 0)}>
                                <span class="text-5xl mb-4 opacity-20">📄</span>
                                <p class="text-[var(--color-muted)] mb-1">Glissez des blocs ici</p>
                                <p class="text-xs text-[var(--color-muted-foreground)]">ou cliquez sur un bloc dans la palette</p>
                            </div>
                        {:else}
                            {#each builder.blocks as block, i}
                                <!-- Drop zone before -->
                                <div class="h-1 rounded transition-all {dragOverId === null && dragOverIndex === i ? 'h-3 bg-[var(--color-primary)]/30' : ''}"
                                     ondragover={(e) => onDragOver(e, null, i)}
                                     ondrop={(e) => onDrop(e, null, i)}></div>

                                <!-- Block -->
                                <div class="group relative rounded-[var(--radius)] border-2 transition-all mb-1 cursor-pointer
                                    {builder.selectedId === block.id ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/20' : 'border-transparent hover:border-[var(--color-accent)]/50'}"
                                    onclick={() => builder.select(block.id)}>

                                    <!-- Block toolbar -->
                                    <div class="absolute -top-7 left-0 z-10 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-[var(--color-primary)] text-white font-medium">{block.type}</span>
                                        <button class="w-5 h-5 rounded bg-[var(--color-card)] border border-[var(--color-border)] flex items-center justify-center text-[10px] cursor-pointer hover:bg-[var(--color-border)]"
                                            onclick={() => builder.duplicateBlock(block.id)}>⧉</button>
                                        <button class="w-5 h-5 rounded bg-[var(--color-card)] border border-[var(--color-border)] flex items-center justify-center text-[10px] cursor-pointer hover:bg-[var(--color-destructive)] hover:text-white"
                                            onclick={() => builder.removeBlock(block.id)}>✕</button>
                                    </div>

                                    <!-- Block content preview -->
                                    <div class="p-2 min-h-[40px]">
                                        {@html renderBlockPreview(block)}
                                    </div>

                                    <!-- Children drop zone for containers -->
                                    {#if ['section', 'container', 'columns', 'column'].includes(block.type)}
                                        <div class="min-h-[40px] border-2 border-dashed border-[var(--color-border)]/30 rounded m-2 p-2"
                                             ondragover={(e) => { e.preventDefault(); e.stopPropagation(); onDragOver(e, block.id, (block.children||[]).length); }}
                                             ondrop={(e) => { e.stopPropagation(); onDrop(e, block.id, (block.children||[]).length); }}>
                                            {#if (block.children||[]).length === 0}
                                                <p class="text-xs text-[var(--color-muted-foreground)] text-center py-2">Déposez ici</p>
                                            {:else}
                                                {#each block.children as child}
                                                    <div class="rounded border border-transparent hover:border-[var(--color-accent)]/50 p-1 mb-1 cursor-pointer {builder.selectedId === child.id ? 'border-[var(--color-primary)]' : ''}"
                                                         onclick={() => builder.select(child.id)}>
                                                        {@html renderBlockPreview(child)}
                                                    </div>
                                                {/each}
                                            {/if}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- RIGHT: Properties Panel -->
    <div class="w-72 flex-shrink-0 bg-[var(--color-secondary)] border-l border-[var(--color-border)] flex flex-col overflow-hidden">
        {#if builder.selectedBlock}
            <div class="p-3 border-b border-[var(--color-border)]">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold capitalize">{builder.selectedBlock.type}</span>
                    <button class="text-xs text-[var(--color-muted)] cursor-pointer hover:text-[var(--color-foreground)]" onclick={() => builder.deselect()}>✕</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-3">
                <!-- Props -->
                {#each Object.entries(builder.selectedBlock.props || {}) as [key, value]}
                    {#if typeof value === 'string'}
                        <div>
                            <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">{key}</label>
                            {#if value.length > 60}
                                <textarea class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 py-1.5 text-xs min-h-[60px]"
                                    value={value} oninput={(e) => builder.updateProp(builder.selectedId, key, e.target.value)}></textarea>
                            {:else}
                                <input type="text" class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 py-1.5 text-xs h-8"
                                    value={value} oninput={(e) => builder.updateProp(builder.selectedId, key, e.target.value)} />
                            {/if}
                        </div>
                    {:else if typeof value === 'number'}
                        <div>
                            <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">{key}</label>
                            <input type="number" class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 py-1.5 text-xs h-8"
                                value={value} oninput={(e) => builder.updateProp(builder.selectedId, key, parseInt(e.target.value) || 0)} />
                        </div>
                    {:else if typeof value === 'boolean'}
                        <label class="flex items-center gap-2 text-xs cursor-pointer">
                            <input type="checkbox" checked={value} onchange={(e) => builder.updateProp(builder.selectedId, key, e.target.checked)} />
                            {key}
                        </label>
                    {/if}
                {/each}

                <!-- Styles -->
                <div class="pt-2 border-t border-[var(--color-border)]">
                    <p class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-2">Styles</p>
                    {#each Object.entries(builder.selectedBlock.styles || {}) as [key, value]}
                        <div class="mb-2">
                            <label class="text-[11px] text-[var(--color-muted)] mb-1 block">{key}</label>
                            <input type="text" class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 py-1.5 text-xs h-8"
                                value={value} oninput={(e) => builder.updateStyle(builder.selectedId, key, e.target.value)} />
                        </div>
                    {/each}
                </div>

                <!-- Actions -->
                <div class="pt-2 border-t border-[var(--color-border)] flex gap-1">
                    <button class="flex-1 py-1.5 text-xs rounded-[var(--radius)] border border-[var(--color-border)] hover:bg-[var(--color-border)] cursor-pointer"
                        onclick={() => builder.duplicateBlock(builder.selectedId)}>⧉ Dupliquer</button>
                    <button class="flex-1 py-1.5 text-xs rounded-[var(--radius)] border border-[var(--color-destructive)]/30 text-[var(--color-destructive)] hover:bg-[var(--color-destructive)]/10 cursor-pointer"
                        onclick={() => builder.removeBlock(builder.selectedId)}>✕ Supprimer</button>
                </div>
            </div>
        {:else}
            <div class="flex-1 flex flex-col items-center justify-center text-center p-4">
                <span class="text-3xl mb-3 opacity-20">🎨</span>
                <p class="text-sm text-[var(--color-muted)]">Sélectionnez un bloc pour modifier ses propriétés</p>
            </div>
        {/if}
    </div>
</div>

