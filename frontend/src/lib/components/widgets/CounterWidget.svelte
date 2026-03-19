<script>
    let { title = '', value = 0, color = 'var(--color-foreground)', icon = '' } = $props();
</script>

<div class="p-5">
    <div class="flex items-center justify-between">
        <p class="text-xs text-[var(--color-muted)] uppercase tracking-wide font-medium">{title}</p>
        {#if icon}
            <span class="text-lg opacity-40">{icon}</span>
        {/if}
    </div>
    <p class="text-3xl font-bold mt-2" style="color: {color}">{value}</p>
</div>
