<script>
    import Card from '$lib/components/ui/Card.svelte';
    import CounterWidget from './CounterWidget.svelte';
    import DataTableWidget from './DataTableWidget.svelte';
    import WelcomeWidget from './WelcomeWidget.svelte';

    let { widget = {}, data = null } = $props();

    const colors = ['var(--color-foreground)', 'var(--color-success)', 'var(--color-accent)', 'var(--color-warning)'];
</script>

<Card class="h-full overflow-hidden">
    {#if widget.widget_type === 'counter'}
        <CounterWidget
            title={widget.title}
            value={data?.value ?? 0}
            color={colors[widget.sort_order % colors.length]}
        />
    {:else if widget.widget_type === 'datatable'}
        <DataTableWidget
            title={widget.title}
            rows={data?.rows ?? []}
            columns={widget.config?.columns ?? []}
        />
    {:else if widget.widget_type === 'welcome'}
        <WelcomeWidget message={widget.config?.message ?? ''} />
    {:else}
        <div class="p-4">
            <p class="text-sm text-[var(--color-muted)]">{widget.widget_type}: {widget.title}</p>
        </div>
    {/if}
</Card>
