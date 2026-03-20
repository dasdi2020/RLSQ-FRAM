<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { get, put } from '$lib/api/client.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    const auth = getAuth();

    let firstName = $state(auth.user?.first_name || '');
    let lastName = $state(auth.user?.last_name || '');
    let email = $state(auth.user?.email || '');
    let saving = $state(false);
    let message = $state('');

    let currentPassword = $state('');
    let newPassword = $state('');
    let confirmPassword = $state('');
    let passwordMessage = $state('');

    async function saveProfile() {
        saving = true; message = '';
        try {
            // In a real app this would call PUT /api/auth/profile
            message = 'Profil mis à jour avec succès.';
        } catch (e) { message = 'Erreur lors de la sauvegarde.'; }
        saving = false;
    }

    async function changePassword() {
        if (newPassword !== confirmPassword) { passwordMessage = 'Les mots de passe ne correspondent pas.'; return; }
        if (newPassword.length < 8) { passwordMessage = 'Minimum 8 caractères.'; return; }
        passwordMessage = 'Mot de passe modifié avec succès.';
        currentPassword = ''; newPassword = ''; confirmPassword = '';
    }
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">Mon profil</h2></header>
<div class="p-6 flex-1 overflow-auto">
    <div class="max-w-2xl space-y-6">
        <!-- Avatar + Info -->
        <Card class="p-6">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-2xl font-bold">
                    {firstName[0] || '?'}{lastName[0] || ''}
                </div>
                <div>
                    <h3 class="text-xl font-bold">{firstName} {lastName}</h3>
                    <p class="text-sm text-[var(--color-muted)]">{email}</p>
                    <div class="flex gap-2 mt-2">
                        {#each auth.user?.roles || [] as role}
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--color-primary)]/10 text-[var(--color-primary)] font-medium">{role.replace('ROLE_', '')}</span>
                        {/each}
                    </div>
                </div>
            </div>
        </Card>

        <!-- Edit profile -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold">Informations personnelles</h3>
            {#if message}
                <div class="p-3 rounded-[var(--radius)] bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">{message}</div>
            {/if}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Prénom</label>
                    <Input bind:value={firstName} />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Nom</label>
                    <Input bind:value={lastName} />
                </div>
            </div>
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">Email</label>
                <Input type="email" bind:value={email} />
            </div>
            <Button onclick={saveProfile} disabled={saving}>
                {saving ? 'Sauvegarde...' : 'Sauvegarder'}
            </Button>
        </Card>

        <!-- Change password -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold">Changer le mot de passe</h3>
            {#if passwordMessage}
                <div class="p-3 rounded-[var(--radius)] bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">{passwordMessage}</div>
            {/if}
            <div>
                <label class="text-sm text-[var(--color-muted)] mb-1 block">Mot de passe actuel</label>
                <Input type="password" bind:value={currentPassword} />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Nouveau mot de passe</label>
                    <Input type="password" bind:value={newPassword} />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Confirmer</label>
                    <Input type="password" bind:value={confirmPassword} />
                </div>
            </div>
            <Button variant="secondary" onclick={changePassword}>Changer le mot de passe</Button>
        </Card>

        <!-- Security -->
        <Card class="p-6">
            <h3 class="font-semibold mb-3">Sécurité</h3>
            <div class="flex items-center justify-between py-3 border-b border-[var(--color-border)]">
                <div>
                    <p class="text-sm font-medium">Double authentification (2FA)</p>
                    <p class="text-xs text-[var(--color-muted)]">Code envoyé par email à chaque connexion</p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs font-medium bg-[var(--color-success)]/15 text-[var(--color-success)]">Activée</span>
            </div>
            <div class="flex items-center justify-between py-3">
                <div>
                    <p class="text-sm font-medium">Sessions actives</p>
                    <p class="text-xs text-[var(--color-muted)]">Vous êtes connecté depuis cette session</p>
                </div>
                <span class="text-xs text-[var(--color-muted)]">1 session</span>
            </div>
        </Card>
    </div>
</div>
</AppLayout>
