<script>
    let { files = [], onSelect = null, selectedPath = '', onContextMenu = null } = $props();

    let expandedDirs = $state({});

    function toggleDir(path) {
        expandedDirs[path] = !expandedDirs[path];
        expandedDirs = { ...expandedDirs };
    }

    function selectFile(file) {
        if (file.type === 'directory') {
            toggleDir(file.path);
        } else if (onSelect) {
            onSelect(file);
        }
    }

    function getFileIcon(file) {
        if (file.type === 'directory') return expandedDirs[file.path] ? '📂' : '📁';
        const ext = file.extension || '';
        return { php: '🐘', js: '🟨', ts: '🔷', html: '🌐', css: '🎨', json: '📋', md: '📝', sql: '🗄', svg: '🎨', yml: '⚙', yaml: '⚙', svelte: '🔥' }[ext] || '📄';
    }
</script>

<div class="text-xs select-none">
    {#each files as file}
        <button
            class="flex items-center gap-1.5 w-full text-left px-2 py-1 hover:bg-[var(--color-border)]/50 cursor-pointer rounded transition-colors
                {selectedPath === file.path ? 'bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'text-[var(--color-foreground)]'}"
            onclick={() => selectFile(file)}
            oncontextmenu={(e) => { e.preventDefault(); if (onContextMenu) onContextMenu(e, file); }}>
            <span class="text-sm">{getFileIcon(file)}</span>
            <span class="truncate">{file.name}</span>
            {#if file.type === 'file' && file.size !== undefined}
                <span class="ml-auto text-[var(--color-muted-foreground)] text-[10px]">{file.size > 1024 ? Math.round(file.size/1024) + 'K' : file.size + 'B'}</span>
            {/if}
        </button>
        {#if file.type === 'directory' && expandedDirs[file.path] && file.children}
            <div class="ml-3 border-l border-[var(--color-border)]/30 pl-1">
                <svelte:self files={file.children} {onSelect} {selectedPath} {onContextMenu} />
            </div>
        {/if}
    {/each}
</div>
