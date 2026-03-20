<script>
    let { type = 'text', value = $bindable(''), class: className = '', ...rest } = $props();
</script>

<input
    {type}
    bind:value
    class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm text-[var(--color-foreground)] placeholder:text-[var(--color-muted)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-accent)] disabled:opacity-50 {className}"
    {...rest}
/>
