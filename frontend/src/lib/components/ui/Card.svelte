<script>
    let { children, class: className = '' } = $props();
</script>

<div class="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] text-[var(--color-card-foreground)] shadow-lg {className}">
    {@render children()}
</div>
