<script>
    import { get, post, del } from '$lib/api/client.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';

    let users = $state([
        { id: 1, email: 'admin@rlsq-fram.local', first_name: 'Super', last_name: 'Admin', roles: '["ROLE_SUPER_ADMIN","ROLE_ADMIN","ROLE_USER"]', is_active: 1, created_at: '2026-03-19' },
    ]);
    let showInviteDialog = $state(false);
    let showRoleDialog = $state(false);
    let selectedUser = $state(null);
    let inviteEmail = $state('');
    let inviteFirstName = $state('');
    let inviteLastName = $state('');
    let inviteRole = $state('ROLE_USER');
    let inviteMessage = $state('');

    const availableRoles = [
        { value: 'ROLE_SUPER_ADMIN', label: 'Super Admin', desc: 'Accès total à la plateforme', color: '#e74c3c' },
        { value: 'ROLE_ADMIN', label: 'Administrateur', desc: 'Gestion des tenants et utilisateurs', color: '#ff3e00' },
        { value: 'ROLE_USER', label: 'Utilisateur', desc: 'Accès standard', color: '#2ecc71' },
    ];

    async function inviteUser() {
        if (!inviteEmail) return;
        inviteMessage = '';
        try {
            // In production: POST /api/admin/users/invite
            users = [...users, {
                id: users.length + 1, email: inviteEmail, first_name: inviteFirstName,
                last_name: inviteLastName, roles: `["${inviteRole}"]`, is_active: 1, created_at: new Date().toISOString().split('T')[0],
            }];
            inviteMessage = `Invitation envoyée à ${inviteEmail}`;
            inviteEmail = ''; inviteFirstName = ''; inviteLastName = ''; inviteRole = 'ROLE_USER';
            setTimeout(() => { showInviteDialog = false; inviteMessage = ''; }, 1500);
        } catch (e) { inviteMessage = 'Erreur lors de l\'envoi.'; }
    }

    function openRoleDialog(user) {
        selectedUser = { ...user, rolesArray: JSON.parse(user.roles || '[]') };
        showRoleDialog = true;
    }

    function toggleRole(role) {
        if (selectedUser.rolesArray.includes(role)) {
            selectedUser.rolesArray = selectedUser.rolesArray.filter(r => r !== role);
        } else {
            selectedUser.rolesArray = [...selectedUser.rolesArray, role];
        }
    }

    function saveRoles() {
        users = users.map(u => u.id === selectedUser.id ? { ...u, roles: JSON.stringify(selectedUser.rolesArray) } : u);
        showRoleDialog = false;
    }

    function getRoleColor(role) {
        return availableRoles.find(r => r.value === role)?.color || '#888';
    }
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">Utilisateurs</h2>
    <Button size="sm" onclick={() => showInviteDialog = true}>+ Inviter</Button>
</header>
<div class="p-6 flex-1 overflow-auto">
    <Card>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--color-border)] bg-[var(--color-secondary)]/30">
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Utilisateur</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Email</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Rôles</th>
                    <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Statut</th>
                    <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase font-medium">Inscrit le</th>
                    <th class="p-3 w-20"></th>
                </tr>
            </thead>
            <tbody>
                {#each users as user}
                    <tr class="border-b border-[var(--color-border)]/50 hover:bg-[var(--color-border)]/20 group">
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-[var(--color-primary)]/20 flex items-center justify-center text-xs font-bold text-[var(--color-primary)]">
                                    {user.first_name?.[0]}{user.last_name?.[0]}
                                </div>
                                <span class="font-medium">{user.first_name} {user.last_name}</span>
                            </div>
                        </td>
                        <td class="p-3 text-[var(--color-muted)] font-mono text-xs">{user.email}</td>
                        <td class="p-3">
                            <div class="flex gap-1 flex-wrap">
                                {#each JSON.parse(user.roles || '[]') as role}
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" style="background: {getRoleColor(role)}20; color: {getRoleColor(role)};">{role.replace('ROLE_', '')}</span>
                                {/each}
                            </div>
                        </td>
                        <td class="p-3 text-center">
                            {#if user.is_active}
                                <span class="w-2 h-2 rounded-full bg-[var(--color-success)] inline-block"></span>
                            {:else}
                                <span class="w-2 h-2 rounded-full bg-[var(--color-muted)] inline-block"></span>
                            {/if}
                        </td>
                        <td class="p-3 text-xs text-[var(--color-muted)]">{user.created_at}</td>
                        <td class="p-3 text-right">
                            <button class="text-xs text-[var(--color-accent)] opacity-0 group-hover:opacity-100 cursor-pointer hover:underline" onclick={() => openRoleDialog(user)}>Rôles</button>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </Card>
</div>

<!-- Invite Dialog -->
<Dialog bind:open={showInviteDialog} class="max-w-md">
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Inviter un utilisateur</h3>
        <p class="text-sm text-[var(--color-muted)]">Un email d'invitation sera envoyé avec un lien de connexion.</p>
        {#if inviteMessage}
            <div class="p-3 rounded-[var(--radius)] bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">{inviteMessage}</div>
        {/if}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">Prénom</label>
                <Input bind:value={inviteFirstName} placeholder="Jean" />
            </div>
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label>
                <Input bind:value={inviteLastName} placeholder="Dupont" />
            </div>
        </div>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Email</label>
            <Input type="email" bind:value={inviteEmail} placeholder="jean@exemple.com" />
        </div>
        <div>
            <label class="text-sm text-[var(--color-muted)] mb-1 block">Rôle</label>
            <div class="space-y-2">
                {#each availableRoles as role}
                    <button class="w-full flex items-center gap-3 p-3 rounded-[var(--radius)] border text-left cursor-pointer transition-colors
                        {inviteRole === role.value ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                        onclick={() => inviteRole = role.value}>
                        <div class="w-3 h-3 rounded-full" style="background: {role.color};"></div>
                        <div>
                            <div class="font-medium text-sm">{role.label}</div>
                            <div class="text-xs text-[var(--color-muted)]">{role.desc}</div>
                        </div>
                    </button>
                {/each}
            </div>
        </div>
        <div class="flex gap-2 justify-end pt-2">
            <Button variant="secondary" onclick={() => showInviteDialog = false}>Annuler</Button>
            <Button onclick={inviteUser} disabled={!inviteEmail}>Envoyer l'invitation</Button>
        </div>
    </div>
</Dialog>

<!-- Role Editor Dialog -->
<Dialog bind:open={showRoleDialog} class="max-w-md">
    {#if selectedUser}
        <div class="p-6 space-y-4">
            <h3 class="text-lg font-semibold">Rôles de {selectedUser.first_name} {selectedUser.last_name}</h3>
            <div class="space-y-2">
                {#each availableRoles as role}
                    <button class="w-full flex items-center gap-3 p-3 rounded-[var(--radius)] border cursor-pointer transition-colors
                        {selectedUser.rolesArray.includes(role.value) ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)]'}"
                        onclick={() => toggleRole(role.value)}>
                        <div class="w-5 h-5 rounded border-2 flex items-center justify-center text-xs
                            {selectedUser.rolesArray.includes(role.value) ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)]'}">
                            {selectedUser.rolesArray.includes(role.value) ? '✓' : ''}
                        </div>
                        <div class="w-3 h-3 rounded-full" style="background: {role.color};"></div>
                        <div>
                            <div class="font-medium text-sm">{role.label}</div>
                            <div class="text-xs text-[var(--color-muted)]">{role.desc}</div>
                        </div>
                    </button>
                {/each}
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <Button variant="secondary" onclick={() => showRoleDialog = false}>Annuler</Button>
                <Button onclick={saveRoles}>Sauvegarder</Button>
            </div>
        </div>
    {/if}
</Dialog>
</AppLayout>
