<script>
    import { get, post } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let notifications = $state([
        { id: 1, type: 'info', title: 'Bienvenue', body: 'Bienvenue sur la plateforme RLSQ-FRAM', is_read: 0, created_at: '2026-03-19 20:00:00', data: {} },
        { id: 2, type: 'success', title: 'Paiement reçu', body: 'Un paiement de 50,00$ a été reçu pour la formation PHP', is_read: 0, created_at: '2026-03-19 19:30:00', data: { amount: 50 } },
        { id: 3, type: 'alert', title: 'Nouvelle inscription', body: 'Alice Dupont s\'est inscrite à la formation Svelte', is_read: 1, created_at: '2026-03-19 18:00:00', data: {} },
    ]);

    let filter = $state('all');

    const typeStyles = {
        info: { icon: 'ℹ️', color: 'var(--color-accent)' },
        success: { icon: '✅', color: 'var(--color-success)' },
        alert: { icon: '⚠️', color: 'var(--color-warning)' },
        error: { icon: '❌', color: 'var(--color-destructive)' },
    };

    let filteredNotifs = $derived(
        filter === 'unread' ? notifications.filter(n => !n.is_read) : notifications
    );
    let unreadCount = $derived(notifications.filter(n => !n.is_read).length);

    function markAsRead(id) {
        notifications = notifications.map(n => n.id === id ? { ...n, is_read: 1 } : n);
    }

    function markAllRead() {
        notifications = notifications.map(n => ({ ...n, is_read: 1 }));
    }

    function deleteNotif(id) {
        notifications = notifications.filter(n => n.id !== id);
    }

    function timeAgo(date) {
        const diff = Math.floor((Date.now() - new Date(date).getTime()) / 60000);
        if (diff < 1) return 'À l\'instant';
        if (diff < 60) return `Il y a ${diff}min`;
        if (diff < 1440) return `Il y a ${Math.floor(diff / 60)}h`;
        return `Il y a ${Math.floor(diff / 1440)}j`;
    }
</script>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">Notifications</h2>
            <p class="text-sm text-[var(--color-muted)]">{unreadCount} non lue{unreadCount > 1 ? 's' : ''}</p>
        </div>
        <div class="flex gap-2">
            <div class="flex rounded-[var(--radius)] border border-[var(--color-border)] overflow-hidden">
                <button class="px-3 py-1.5 text-sm cursor-pointer {filter === 'all' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)]'}" onclick={() => filter = 'all'}>Toutes</button>
                <button class="px-3 py-1.5 text-sm cursor-pointer border-l border-[var(--color-border)] {filter === 'unread' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-muted)]'}" onclick={() => filter = 'unread'}>Non lues</button>
            </div>
            {#if unreadCount > 0}
                <Button variant="secondary" size="sm" onclick={markAllRead}>Tout marquer lu</Button>
            {/if}
        </div>
    </div>

    <div class="space-y-2">
        {#each filteredNotifs as notif}
            <Card class="overflow-hidden {notif.is_read ? 'opacity-60' : ''}">
                <div class="flex items-start gap-4 p-4">
                    <span class="text-xl mt-0.5">{typeStyles[notif.type]?.icon || 'ℹ️'}</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="font-semibold text-sm">{notif.title}</h4>
                            {#if !notif.is_read}
                                <span class="w-2 h-2 rounded-full bg-[var(--color-primary)]"></span>
                            {/if}
                        </div>
                        <p class="text-sm text-[var(--color-muted)] mt-0.5">{notif.body}</p>
                        <p class="text-xs text-[var(--color-muted-foreground)] mt-1">{timeAgo(notif.created_at)}</p>
                    </div>
                    <div class="flex gap-1">
                        {#if !notif.is_read}
                            <button class="text-xs text-[var(--color-accent)] hover:underline cursor-pointer" onclick={() => markAsRead(notif.id)}>Marquer lu</button>
                        {/if}
                        <button class="text-xs text-[var(--color-destructive)] hover:underline cursor-pointer ml-2" onclick={() => deleteNotif(notif.id)}>✕</button>
                    </div>
                </div>
            </Card>
        {:else}
            <Card class="p-8 text-center">
                <p class="text-4xl mb-3">🔔</p>
                <p class="text-[var(--color-muted)]">{filter === 'unread' ? 'Aucune notification non lue.' : 'Aucune notification.'}</p>
            </Card>
        {/each}
    </div>
</div>
