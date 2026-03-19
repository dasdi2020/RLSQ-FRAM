<script>
    let { children, variant = 'default', size = 'default', class: className = '', disabled = false, ...rest } = $props();

    const base = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius)] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-accent)] disabled:opacity-50 disabled:pointer-events-none cursor-pointer';

    const variants = {
        default: 'bg-[var(--color-primary)] text-[var(--color-primary-foreground)] hover:bg-[var(--color-primary)]/90',
        secondary: 'bg-[var(--color-secondary)] text-[var(--color-secondary-foreground)] border border-[var(--color-border)] hover:bg-[var(--color-border)]',
        destructive: 'bg-[var(--color-destructive)] text-white hover:bg-[var(--color-destructive)]/90',
        ghost: 'hover:bg-[var(--color-border)] text-[var(--color-foreground)]',
        link: 'text-[var(--color-accent)] underline-offset-4 hover:underline',
        outline: 'border border-[var(--color-border)] text-[var(--color-foreground)] hover:bg-[var(--color-border)]',
    };

    const sizes = {
        default: 'h-10 px-4 py-2 text-sm',
        sm: 'h-8 px-3 text-xs',
        lg: 'h-12 px-6 text-base',
        icon: 'h-10 w-10',
    };
</script>

<button class="{base} {variants[variant]} {sizes[size]} {className}" {disabled} {...rest}>
    {@render children()}
</button>
