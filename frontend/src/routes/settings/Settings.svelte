<script>
    import { get, post, put } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';
    import AppLayout from '$lib/components/AppLayout.svelte';

    let activeTab = $state('theme');

    // Theme
    let theme = $state({ primary_color: '#ff3e00', secondary_color: '#1a1a2e', accent_color: '#6cb2eb', logo_url: '', font_family: '', custom_css: '' });

    // Roles
    let roles = $state([]);
    let showRoleDialog = $state(false);
    let newRole = $state({ name: '', slug: '', permissions: [] });
    let availablePermissions = $state([
        'members.view', 'members.create', 'members.edit', 'members.delete',
        'clubs.view', 'clubs.create', 'clubs.edit', 'clubs.delete',
        'formations.view', 'formations.create', 'formations.edit', 'formations.delete',
        'activities.view', 'activities.create', 'activities.edit', 'activities.delete',
        'payments.view', 'payments.refund', 'payments.configure',
        'forms.view', 'forms.create', 'forms.edit', 'forms.delete',
        'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish',
        'settings.view', 'settings.edit', 'plugins.manage', 'schema.manage',
        'audit.view', 'export.data', 'import.data',
    ]);

    // i18n
    let translations = $state([]);
    let currentLocale = $state('fr');
    let newTransKey = $state('');
    let newTransValue = $state('');

    // Backup
    let backups = $state([]);

    const tabs = [
        { id: 'theme', label: 'Thème', icon: '🎨' },
        { id: 'roles', label: 'Rôles & Permissions', icon: '🔐' },
        { id: 'i18n', label: 'Traductions', icon: '🌐' },
        { id: 'backup', label: 'Sauvegardes', icon: '💾' },
        { id: 'webhooks', label: 'Webhooks', icon: '🔗' },
    ];

    const permGroups = $derived(() => {
        const groups = {};
        availablePermissions.forEach(p => {
            const [g] = p.split('.');
            if (!groups[g]) groups[g] = [];
            groups[g].push(p);
        });
        return groups;
    });

    function togglePermission(perm) {
        if (newRole.permissions.includes(perm)) {
            newRole.permissions = newRole.permissions.filter(p => p !== perm);
        } else {
            newRole.permissions = [...newRole.permissions, perm];
        }
    }
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Paramètres</h2></header>
<div class="p-6 flex-1 overflow-auto">
<div class="space-y-6">
    <!-- Tabs -->
    <div class="flex gap-1 border-b border-[var(--color-border)]">
        {#each tabs as tab}
            <button
                class="px-4 py-2.5 text-sm font-medium transition-colors cursor-pointer border-b-2
                    {activeTab === tab.id ? 'text-[var(--color-primary)] border-[var(--color-primary)]' : 'text-[var(--color-muted)] border-transparent hover:text-[var(--color-foreground)]'}"
                onclick={() => activeTab = tab.id}
            >
                {tab.icon} {tab.label}
            </button>
        {/each}
    </div>

    <!-- Theme Tab -->
    {#if activeTab === 'theme'}
        <Card class="p-6 space-y-5">
            <h3 class="font-semibold">Branding & Thème</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Couleur principale</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" bind:value={theme.primary_color} class="w-10 h-10 rounded border border-[var(--color-border)] cursor-pointer" />
                        <Input bind:value={theme.primary_color} class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Couleur secondaire</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" bind:value={theme.secondary_color} class="w-10 h-10 rounded border border-[var(--color-border)] cursor-pointer" />
                        <Input bind:value={theme.secondary_color} class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Couleur accent</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" bind:value={theme.accent_color} class="w-10 h-10 rounded border border-[var(--color-border)] cursor-pointer" />
                        <Input bind:value={theme.accent_color} class="flex-1" />
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">URL du logo</label>
                    <Input placeholder="https://..." bind:value={theme.logo_url} />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Police</label>
                    <Input placeholder="Inter, system-ui, sans-serif" bind:value={theme.font_family} />
                </div>
            </div>
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">CSS personnalisé</label>
                <textarea class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm font-mono min-h-[100px]"
                    bind:value={theme.custom_css} placeholder="Votre CSS ici..."></textarea>
            </div>
            <!-- Preview -->
            <div class="p-4 rounded-[var(--radius)] border border-[var(--color-border)]" style="background:{theme.secondary_color}">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded" style="background:{theme.primary_color}"></div>
                    <span style="color:{theme.accent_color}" class="font-semibold">Aperçu du thème</span>
                </div>
                <div class="flex gap-2">
                    <button class="px-3 py-1.5 rounded text-sm text-white" style="background:{theme.primary_color}">Bouton principal</button>
                    <button class="px-3 py-1.5 rounded text-sm border" style="border-color:{theme.accent_color};color:{theme.accent_color}">Bouton secondaire</button>
                </div>
            </div>
            <Button>Sauvegarder le thème</Button>
        </Card>
    {/if}

    <!-- Roles Tab -->
    {#if activeTab === 'roles'}
        <div class="space-y-4">
            <div class="flex justify-between">
                <p class="text-sm text-[var(--color-muted)]">Gérez les rôles et permissions personnalisés</p>
                <Button size="sm" onclick={() => showRoleDialog = true}>+ Nouveau rôle</Button>
            </div>

            <!-- Default roles -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {#each [
                    { name: 'Admin Fédération', slug: 'ROLE_FEDERATION_ADMIN', perms: availablePermissions.length, color: 'var(--color-primary)' },
                    { name: 'Admin Club', slug: 'ROLE_CLUB_ADMIN', perms: 12, color: 'var(--color-accent)' },
                    { name: 'Membre', slug: 'ROLE_MEMBER', perms: 3, color: 'var(--color-success)' },
                ] as role}
                    <Card class="p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-3 h-3 rounded-full" style="background:{role.color}"></div>
                            <h4 class="font-semibold text-sm">{role.name}</h4>
                        </div>
                        <p class="text-xs text-[var(--color-muted)] font-mono">{role.slug}</p>
                        <p class="text-xs text-[var(--color-muted)] mt-1">{role.perms} permissions</p>
                        <span class="text-xs px-2 py-0.5 rounded bg-[var(--color-border)] text-[var(--color-muted)] mt-2 inline-block">Système</span>
                    </Card>
                {/each}
            </div>

            <!-- Custom roles -->
            {#each roles as role}
                <Card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold">{role.name}</h4>
                            <p class="text-xs text-[var(--color-muted)] font-mono">{role.slug} — {role.permissions?.length || 0} permissions</p>
                        </div>
                        <Button size="sm" variant="secondary">Modifier</Button>
                    </div>
                </Card>
            {/each}
        </div>

        <!-- Role creation dialog -->
        <Dialog bind:open={showRoleDialog} class="max-w-2xl">
            <div class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Nouveau rôle</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label>
                        <Input placeholder="Éditeur" bind:value={newRole.name} />
                    </div>
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Slug</label>
                        <Input placeholder="editor" bind:value={newRole.slug} />
                    </div>
                </div>

                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-2 block">Permissions ({newRole.permissions.length} sélectionnées)</label>
                    <div class="space-y-3">
                        {#each Object.entries(permGroups()) as [group, perms]}
                            <div>
                                <h5 class="text-xs font-semibold text-[var(--color-muted)] uppercase tracking-wide mb-1">{group}</h5>
                                <div class="flex flex-wrap gap-1.5">
                                    {#each perms as perm}
                                        <button class="px-2 py-1 rounded text-xs cursor-pointer transition-colors
                                            {newRole.permissions.includes(perm)
                                                ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)] border border-[var(--color-primary)]/30'
                                                : 'bg-[var(--color-border)] text-[var(--color-muted)] border border-transparent hover:border-[var(--color-accent)]'}"
                                            onclick={() => togglePermission(perm)}>
                                            {perm.split('.')[1]}
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <div class="flex gap-2 justify-end pt-2">
                    <Button variant="secondary" onclick={() => showRoleDialog = false}>Annuler</Button>
                    <Button>Créer le rôle</Button>
                </div>
            </div>
        </Dialog>
    {/if}

    <!-- i18n Tab -->
    {#if activeTab === 'i18n'}
        <Card class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Traductions</h3>
                <div class="flex gap-2">
                    {#each ['fr', 'en'] as locale}
                        <button class="px-3 py-1.5 rounded text-sm cursor-pointer
                            {currentLocale === locale ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-border)] text-[var(--color-muted)]'}"
                            onclick={() => currentLocale = locale}>
                            {locale.toUpperCase()}
                        </button>
                    {/each}
                </div>
            </div>

            <div class="flex gap-2">
                <Input placeholder="Clé (ex: common.hello)" bind:value={newTransKey} class="flex-1" />
                <Input placeholder="Valeur" bind:value={newTransValue} class="flex-1" />
                <Button size="sm">Ajouter</Button>
            </div>

            <div class="divide-y divide-[var(--color-border)] border border-[var(--color-border)] rounded-[var(--radius)]">
                {#each translations as t}
                    <div class="flex items-center gap-4 p-3 hover:bg-[var(--color-border)]/20">
                        <span class="font-mono text-xs text-[var(--color-accent)] w-40 truncate">{t.key_group}.{t.key_name}</span>
                        <span class="flex-1 text-sm">{t.value}</span>
                    </div>
                {:else}
                    <div class="p-8 text-center text-[var(--color-muted)]">
                        Aucune traduction pour {currentLocale.toUpperCase()}.
                    </div>
                {/each}
            </div>
        </Card>
    {/if}

    <!-- Backup Tab -->
    {#if activeTab === 'backup'}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-[var(--color-muted)]">Sauvegardez et restaurez les données du tenant</p>
                <Button>Créer une sauvegarde</Button>
            </div>

            <Card>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-border)]">
                            <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Nom</th>
                            <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Date</th>
                            <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Taille</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each backups as backup}
                            <tr class="border-b border-[var(--color-border)] hover:bg-[var(--color-border)]/20">
                                <td class="p-3 font-mono text-xs">{backup.name}</td>
                                <td class="p-3 text-[var(--color-muted)]">{backup.date}</td>
                                <td class="p-3">{Math.round(backup.size / 1024)} Ko</td>
                                <td class="p-3 text-right">
                                    <Button size="sm" variant="secondary">Restaurer</Button>
                                </td>
                            </tr>
                        {:else}
                            <tr><td colspan="4" class="p-8 text-center text-[var(--color-muted)]">Aucune sauvegarde.</td></tr>
                        {/each}
                    </tbody>
                </table>
            </Card>
        </div>
    {/if}

    <!-- Webhooks Tab -->
    {#if activeTab === 'webhooks'}
        <Card class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold">Webhooks sortants</h3>
                <Button size="sm">+ Endpoint</Button>
            </div>
            <p class="text-sm text-[var(--color-muted)]">
                Envoyez des notifications automatiques à des URLs externes quand des événements se produisent.
            </p>
            <div class="grid grid-cols-3 gap-2 text-xs">
                {#each ['member.created', 'member.updated', 'payment.completed', 'payment.refunded', 'form.submitted', 'registration.created'] as evt}
                    <span class="px-2 py-1.5 rounded bg-[var(--color-secondary)] border border-[var(--color-border)] font-mono">{evt}</span>
                {/each}
            </div>
        </Card>
    {/if}
</div>
</div>
</AppLayout>
