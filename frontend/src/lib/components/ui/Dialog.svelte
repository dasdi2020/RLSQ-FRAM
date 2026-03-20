<script>
    let { children, open = $bindable(false), class: className = 'max-w-md' } = $props();

    function handleBackdropClick(e) {
        if (e.target === e.currentTarget) {
            open = false;
        }
    }
</script>

{#if open}
    <div
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
        onclick={handleBackdropClick}
        role="dialog"
    >
        <div class="w-full {className} rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] text-[var(--color-card-foreground)] shadow-2xl max-h-[85vh] overflow-y-auto">
            {@render children()}
        </div>
    </div>
{/if}
