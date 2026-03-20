<script>
    import { get } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import AppLayout from '$lib/components/AppLayout.svelte';

    let logs = $state([]);
    let total = $state(0);
    let page = $state(1);
    let loading = $state(true);
    let filterAction = $state('');
    let filterEntity = $state('');

    const actionColors = {
        create: 'bg-[var(--color-success)]/15 text-[var(--color-success)]',
        update: 'bg-[var(--color-accent)]/15 text-[var(--color-accent)]',
        delete: 'bg-[var(--color-destructive)]/15 text-[var(--color-destructive)]',
        login: 'bg-[var(--color-warning)]/15 text-[var(--color-warning)]',
    };

    // Note: audit logs API would be at /api/t/{slug}/audit
    // For now showing the UI structure
    $effect(() => { loading = false; });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Journal d'audit</h2></header>
<div class="p-6 flex-1 overflow-auto">
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-[var(--color-muted)]">Historique de toutes les actions</p>
        </div>
        <div class="flex gap-2">
            <Input placeholder="Filtrer par action" bind:value={filterAction} class="w-40" />
            <Input placeholder="Type d'entité" bind:value={filterEntity} class="w-40" />
            <Button variant="secondary" size="sm">Exporter</Button>
        </div>
    </div>

    <Card>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--color-border)]">
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Date</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Action</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Entité</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Utilisateur</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Changements</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">IP</th>
                </tr>
            </thead>
            <tbody>
                {#each logs as log}
                    <tr class="border-b border-[var(--color-border)] hover:bg-[var(--color-border)]/20">
                        <td class="p-3 text-xs text-[var(--color-muted)]">{log.created_at}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {actionColors[log.action] || 'bg-[var(--color-border)]'}">
                                {log.action}
                            </span>
                        </td>
                        <td class="p-3 font-mono text-xs">{log.entity_type} #{log.entity_id}</td>
                        <td class="p-3">User #{log.user_id}</td>
                        <td class="p-3 text-xs max-w-[200px] truncate">{JSON.stringify(log.changes)}</td>
                        <td class="p-3 text-xs text-[var(--color-muted)]">{log.ip_address}</td>
                    </tr>
                {:else}
                    <tr><td colspan="6" class="p-8 text-center text-[var(--color-muted)]">Aucune entrée dans le journal.</td></tr>
                {/each}
            </tbody>
        </table>
    </Card>
</div>
</div>
</AppLayout>
