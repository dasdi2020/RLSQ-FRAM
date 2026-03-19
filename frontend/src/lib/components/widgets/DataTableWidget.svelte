<script>
    let { title = '', rows = [], columns = [] } = $props();
</script>

<div class="p-4">
    <h3 class="text-sm font-semibold mb-3 text-[var(--color-muted)]">{title}</h3>
    {#if rows.length === 0}
        <p class="text-sm text-[var(--color-muted-foreground)] py-4 text-center">Aucune donnée</p>
    {:else}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        {#each columns as col}
                            <th class="text-left py-2 px-3 text-xs text-[var(--color-muted)] uppercase tracking-wide border-b border-[var(--color-border)]">{col}</th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each rows as row}
                        <tr class="hover:bg-[var(--color-border)]/20">
                            {#each columns as col}
                                <td class="py-2 px-3 border-b border-[var(--color-border)]/50">{row[col] ?? '-'}</td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}
</div>
