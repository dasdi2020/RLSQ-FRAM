<script>
    import { get, put, post, del } from '$lib/api/client.js';
    import MonacoWrapper from '$lib/components/editor/MonacoWrapper.svelte';
    import FileExplorer from '$lib/components/editor/FileExplorer.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';

    let { projectSlug = '' } = $props();

    let files = $state([]);
    let openTabs = $state([]);
    let activeTab = $state(null);
    let fileContent = $state('');
    let fileLanguage = $state('plaintext');
    let loading = $state(true);
    let saving = $state(false);
    let showNewFile = $state(false);
    let newFileName = $state('');
    let newFileType = $state('file');
    let newFilePath = $state('');

    // Monaco theme
    let darkMode = $state(localStorage.getItem('theme') !== 'light');
    let monacoTheme = $derived(darkMode ? 'vs-dark' : 'vs');

    async function loadFiles() {
        loading = true;
        try {
            const res = await get(`/api/p/${projectSlug}/files`);
            files = res.data || [];
        } catch (e) { console.error(e); }
        loading = false;
    }

    async function openFile(file) {
        if (file.type === 'directory') return;

        // Vérifier si déjà ouvert
        const existing = openTabs.find(t => t.path === file.path);
        if (existing) {
            activeTab = existing;
            fileContent = existing.content;
            fileLanguage = existing.language;
            return;
        }

        try {
            const res = await get(`/api/p/${projectSlug}/files/read?path=${encodeURIComponent(file.path)}`);
            const tab = { path: file.path, name: file.name, content: res.content, language: res.language || 'plaintext', modified: false };
            openTabs = [...openTabs, tab];
            activeTab = tab;
            fileContent = tab.content;
            fileLanguage = tab.language;
        } catch (e) { console.error(e); }
    }

    function switchTab(tab) {
        // Save current content to tab
        if (activeTab) {
            activeTab.content = fileContent;
        }
        activeTab = tab;
        fileContent = tab.content;
        fileLanguage = tab.language;
    }

    function closeTab(tab) {
        openTabs = openTabs.filter(t => t.path !== tab.path);
        if (activeTab?.path === tab.path) {
            activeTab = openTabs[openTabs.length - 1] || null;
            if (activeTab) {
                fileContent = activeTab.content;
                fileLanguage = activeTab.language;
            } else {
                fileContent = '';
                fileLanguage = 'plaintext';
            }
        }
    }

    function onContentChange(newContent) {
        if (activeTab) {
            activeTab.content = newContent;
            activeTab.modified = true;
            openTabs = [...openTabs]; // trigger reactivity
        }
    }

    async function saveFile() {
        if (!activeTab) return;
        saving = true;
        try {
            await put(`/api/p/${projectSlug}/files/write`, { path: activeTab.path, content: fileContent });
            activeTab.modified = false;
            openTabs = [...openTabs];
        } catch (e) { console.error(e); }
        saving = false;
    }

    async function saveAll() {
        for (const tab of openTabs.filter(t => t.modified)) {
            await put(`/api/p/${projectSlug}/files/write`, { path: tab.path, content: tab.content });
            tab.modified = false;
        }
        openTabs = [...openTabs];
    }

    async function createFile() {
        if (!newFileName) return;
        const path = newFilePath ? `${newFilePath}/${newFileName}` : newFileName;
        try {
            await post(`/api/p/${projectSlug}/files/create`, { path, type: newFileType, content: '' });
            newFileName = ''; newFilePath = ''; showNewFile = false;
            await loadFiles();
        } catch (e) { console.error(e); }
    }

    async function deleteFile(file) {
        if (!confirm(`Supprimer ${file.name} ?`)) return;
        try {
            await del(`/api/p/${projectSlug}/files/delete`, { path: file.path });
            closeTab({ path: file.path });
            await loadFiles();
        } catch (e) { console.error(e); }
    }

    // Keyboard shortcuts
    function onKeyDown(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveFile();
        }
    }

    $effect(() => { loadFiles(); });
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="flex h-full overflow-hidden" onkeydown={onKeyDown}>
    <!-- File explorer -->
    <div class="w-56 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col">
        <div class="p-2 border-b border-[var(--color-border)] flex items-center justify-between">
            <span class="text-[11px] font-semibold text-[var(--color-muted)] uppercase tracking-wide">Fichiers</span>
            <div class="flex gap-0.5">
                <button class="w-6 h-6 rounded flex items-center justify-center text-xs cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={() => { newFileType = 'file'; showNewFile = true; }} title="Nouveau fichier">+📄</button>
                <button class="w-6 h-6 rounded flex items-center justify-center text-xs cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={() => { newFileType = 'directory'; showNewFile = true; }} title="Nouveau dossier">+📁</button>
                <button class="w-6 h-6 rounded flex items-center justify-center text-xs cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                    onclick={loadFiles} title="Rafraîchir">↻</button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-1">
            {#if loading}
                <p class="text-xs text-[var(--color-muted)] p-2">Chargement...</p>
            {:else}
                <FileExplorer {files} onSelect={openFile} selectedPath={activeTab?.path || ''} />
            {/if}
        </div>
    </div>

    <!-- Editor area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Tabs bar -->
        <div class="h-9 bg-[var(--color-secondary)] border-b border-[var(--color-border)] flex items-center overflow-x-auto">
            {#each openTabs as tab}
                <button class="flex items-center gap-1.5 px-3 h-full text-xs border-r border-[var(--color-border)] cursor-pointer whitespace-nowrap transition-colors
                    {activeTab?.path === tab.path ? 'bg-[var(--color-card)] text-[var(--color-foreground)]' : 'text-[var(--color-muted)] hover:bg-[var(--color-border)]/50'}"
                    onclick={() => switchTab(tab)}>
                    <span>{tab.name}</span>
                    {#if tab.modified}<span class="w-1.5 h-1.5 rounded-full bg-[var(--color-warning)]"></span>{/if}
                    <span class="ml-1 hover:text-[var(--color-destructive)]" onclick={(e) => { e.stopPropagation(); closeTab(tab); }}>✕</span>
                </button>
            {/each}

            {#if openTabs.length > 0}
                <div class="ml-auto flex items-center gap-1 px-2">
                    <button class="px-2 py-1 text-[10px] rounded cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                        onclick={saveFile} disabled={saving}>
                        {saving ? '...' : '💾 Ctrl+S'}
                    </button>
                    <button class="px-2 py-1 text-[10px] rounded cursor-pointer hover:bg-[var(--color-border)] text-[var(--color-muted)]"
                        onclick={saveAll}>💾 Tout</button>
                </div>
            {/if}
        </div>

        <!-- Monaco editor -->
        <div class="flex-1">
            {#if activeTab}
                <MonacoWrapper bind:value={fileContent} language={fileLanguage} theme={monacoTheme} onChange={onContentChange} />
            {:else}
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <span class="text-6xl mb-4 opacity-20">💻</span>
                    <p class="text-[var(--color-muted)] text-lg mb-1">Éditeur de code</p>
                    <p class="text-sm text-[var(--color-muted-foreground)]">Ouvrez un fichier depuis l'explorateur</p>
                    <div class="flex gap-4 mt-4 text-xs text-[var(--color-muted-foreground)]">
                        <span>PHP</span><span>JavaScript</span><span>HTML</span><span>CSS</span><span>JSON</span><span>SQL</span>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Status bar -->
        <div class="h-6 bg-[var(--color-primary)] text-white flex items-center justify-between px-3 text-[10px]">
            <div class="flex items-center gap-3">
                {#if activeTab}
                    <span>{activeTab.path}</span>
                    <span>{fileLanguage}</span>
                {:else}
                    <span>RLSQ-FRAM Code Editor</span>
                {/if}
            </div>
            <div class="flex items-center gap-3">
                <span>UTF-8</span>
                <span>Spaces: 4</span>
            </div>
        </div>
    </div>
</div>

<!-- New file dialog -->
<Dialog bind:open={showNewFile}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">{newFileType === 'directory' ? 'Nouveau dossier' : 'Nouveau fichier'}</h3>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Chemin parent (optionnel)</label>
            <Input placeholder="css" bind:value={newFilePath} />
        </div>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label>
            <Input placeholder={newFileType === 'directory' ? 'components' : 'script.js'} bind:value={newFileName} />
        </div>
        <div class="flex gap-2 justify-end">
            <Button variant="secondary" onclick={() => showNewFile = false}>Annuler</Button>
            <Button onclick={createFile}>Créer</Button>
        </div>
    </div>
</Dialog>
