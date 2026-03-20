<script>
    import { get } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';

    let { projectSlug = '' } = $props();

    let device = $state('desktop');
    let iframeUrl = $state('');
    let iframeRef = $state(null);
    let isLandscape = $state(false);
    let showGrid = $state(false);
    let zoom = $state(100);
    let pages = $state([]);
    let selectedPage = $state('');

    const devices = [
        { id: 'desktop', label: 'Desktop', icon: '🖥️', width: '100%', height: '100%' },
        { id: 'laptop', label: 'Laptop', icon: '💻', width: '1366px', height: '768px' },
        { id: 'tablet', label: 'Tablet', icon: '📱', width: '768px', height: '1024px' },
        { id: 'mobile', label: 'Mobile', icon: '📲', width: '375px', height: '812px' },
        { id: 'mobile-sm', label: 'Mobile SM', icon: '📱', width: '320px', height: '568px' },
    ];

    let currentDevice = $derived(devices.find(d => d.id === device) || devices[0]);
    let frameWidth = $derived(currentDevice.width);
    let frameHeight = $derived(device === 'desktop' ? '100%' : currentDevice.height);

    async function loadPages() {
        try {
            const res = await get(`/api/t/${projectSlug}/pages`);
            pages = res.data || [];
            if (pages.length > 0 && !selectedPage) {
                selectedPage = pages[0].id;
                loadPreview();
            }
        } catch {}
    }

    function loadPreview() {
        if (selectedPage) {
            iframeUrl = `/api/t/${projectSlug}/pages/${selectedPage}/preview?t=${Date.now()}`;
        }
    }

    function refresh() {
        if (iframeRef) {
            iframeUrl = iframeUrl.replace(/t=\d+/, `t=${Date.now()}`);
        }
    }

    function openExternal() {
        if (iframeUrl) window.open(iframeUrl, '_blank');
    }

    $effect(() => { loadPages(); });
</script>

<div class="flex flex-col h-full overflow-hidden">
    <!-- Toolbar -->
    <div class="h-12 bg-[var(--color-secondary)] border-b border-[var(--color-border)] flex items-center justify-between px-4">
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold">Preview</span>

            <!-- Page selector -->
            {#if pages.length > 0}
                <select class="h-7 rounded border border-[var(--color-border)] bg-[var(--color-card)] px-2 text-xs"
                    bind:value={selectedPage} onchange={loadPreview}>
                    {#each pages as page}
                        <option value={page.id}>{page.name}</option>
                    {/each}
                </select>
            {/if}
        </div>

        <!-- Device switcher -->
        <div class="flex items-center gap-1 bg-[var(--color-card)] rounded-[var(--radius)] border border-[var(--color-border)] px-1 py-0.5">
            {#each devices as d}
                <button class="px-2 py-1 rounded text-xs cursor-pointer transition-colors
                    {device === d.id ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)] hover:text-[var(--color-foreground)]'}"
                    onclick={() => device = d.id} title={d.label}>
                    {d.icon}
                </button>
            {/each}

            {#if device !== 'desktop'}
                <span class="text-[var(--color-border)] mx-0.5">|</span>
                <button class="px-1.5 py-1 rounded text-xs cursor-pointer {isLandscape ? 'bg-[var(--color-accent)] text-white' : 'text-[var(--color-muted)]'}"
                    onclick={() => isLandscape = !isLandscape} title="Paysage">↻</button>
            {/if}
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1 text-xs text-[var(--color-muted)]">
                <button class="px-1 cursor-pointer hover:text-[var(--color-foreground)]" onclick={() => zoom = Math.max(25, zoom - 25)}>−</button>
                <span class="w-10 text-center">{zoom}%</span>
                <button class="px-1 cursor-pointer hover:text-[var(--color-foreground)]" onclick={() => zoom = Math.min(200, zoom + 25)}>+</button>
            </div>
            <label class="flex items-center gap-1 text-xs text-[var(--color-muted)] cursor-pointer">
                <input type="checkbox" bind:checked={showGrid} class="w-3 h-3" /> Grid
            </label>
            <Button size="sm" variant="secondary" onclick={refresh}>↻</Button>
            <Button size="sm" variant="secondary" onclick={openExternal}>↗</Button>
        </div>
    </div>

    <!-- Preview area -->
    <div class="flex-1 overflow-auto flex items-start justify-center p-4" style="background: repeating-conic-gradient(var(--color-border) 0% 25%, transparent 0% 50%) 0 0 / 16px 16px;">
        <div class="transition-all duration-300 relative"
             style="width: {device === 'desktop' ? '100%' : isLandscape ? frameHeight : frameWidth};
                    height: {device === 'desktop' ? '100%' : isLandscape ? frameWidth : frameHeight};
                    max-width: 100%;
                    transform: scale({zoom / 100}); transform-origin: top center;">

            {#if device !== 'desktop'}
                <!-- Device frame -->
                <div class="absolute inset-0 border-4 border-[var(--color-foreground)]/20 rounded-2xl pointer-events-none z-10"></div>
                <!-- Notch -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-24 h-5 bg-[var(--color-foreground)]/20 rounded-b-xl z-10"></div>
            {/if}

            {#if iframeUrl}
                <iframe
                    bind:this={iframeRef}
                    src={iframeUrl}
                    class="w-full h-full bg-white {device !== 'desktop' ? 'rounded-2xl' : ''}"
                    style="border: none; {showGrid ? 'background-image: linear-gradient(rgba(0,0,0,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.05) 1px, transparent 1px); background-size: 20px 20px;' : ''}"
                    title="Preview"
                ></iframe>
            {:else}
                <div class="w-full h-full flex flex-col items-center justify-center bg-[var(--color-card)] {device !== 'desktop' ? 'rounded-2xl' : ''}">
                    <span class="text-5xl mb-4 opacity-20">👁️</span>
                    <p class="text-[var(--color-muted)]">Aucune page à prévisualiser</p>
                    <p class="text-xs text-[var(--color-muted-foreground)] mt-1">Créez une page dans l'éditeur</p>
                </div>
            {/if}
        </div>
    </div>

    <!-- Status bar -->
    <div class="h-7 bg-[var(--color-secondary)] border-t border-[var(--color-border)] flex items-center justify-between px-4 text-[10px] text-[var(--color-muted)]">
        <div class="flex items-center gap-3">
            <span>{currentDevice.label}</span>
            {#if device !== 'desktop'}
                <span>{isLandscape ? `${currentDevice.height} × ${currentDevice.width}` : `${currentDevice.width} × ${currentDevice.height}`}</span>
            {/if}
        </div>
        <span>Zoom: {zoom}%</span>
    </div>
</div>
