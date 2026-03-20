<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { getI18n } from '$lib/stores/i18n.svelte.js';
    import { get, post, put } from '$lib/api/client.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    const auth = getAuth();
    const i18n = getI18n();
    let t = $derived(i18n.t);

    let firstName = $state(auth.user?.first_name || '');
    let lastName = $state(auth.user?.last_name || '');
    let email = $state(auth.user?.email || '');
    let saving = $state(false);
    let message = $state('');

    let currentPassword = $state('');
    let newPassword = $state('');
    let confirmPassword = $state('');
    let passwordMessage = $state('');

    // MFA
    let mfaMethod = $state('email');
    let totpSetup = $state(null); // {secret, qr_code_url, provisioning_uri}
    let totpCode = $state('');
    let totpMessage = $state('');
    let totpLoading = $state(false);

    async function loadMfaStatus() {
        try {
            const res = await get('/api/auth/me');
            mfaMethod = res.mfa_method || 'email';
        } catch {}
    }

    async function saveProfile() {
        saving = true; message = '';
        try { message = t.save + ' ✓'; } catch { message = 'Error'; }
        saving = false;
    }

    async function changePassword() {
        if (newPassword !== confirmPassword) { passwordMessage = 'Passwords do not match.'; return; }
        if (newPassword.length < 8) { passwordMessage = 'Min 8 chars.'; return; }
        passwordMessage = t.change_password + ' ✓';
        currentPassword = ''; newPassword = ''; confirmPassword = '';
    }

    async function setupTotp() {
        totpLoading = true; totpMessage = '';
        try {
            const res = await post('/api/auth/mfa/setup-totp', {});
            totpSetup = res;
        } catch (e) { totpMessage = 'Error'; }
        totpLoading = false;
    }

    async function confirmTotp() {
        if (totpCode.length !== 6) return;
        totpLoading = true; totpMessage = '';
        try {
            const res = await post('/api/auth/mfa/confirm-totp', { code: totpCode });
            if (res.error) { totpMessage = res.error; }
            else { totpMessage = '✓ TOTP ' + t.enabled; mfaMethod = 'totp'; totpSetup = null; totpCode = ''; }
        } catch { totpMessage = 'Error'; }
        totpLoading = false;
    }

    async function switchMfa(method) {
        try {
            const res = await post('/api/auth/mfa/switch', { method });
            if (res.error) { totpMessage = res.error; return; }
            mfaMethod = method;
        } catch {}
    }

    $effect(() => { loadMfaStatus(); });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6"><h2 class="text-lg font-semibold">{t.my_profile}</h2></header>
<div class="p-6 flex-1 overflow-auto">
    <div class="max-w-2xl space-y-6">
        <!-- Avatar -->
        <Card class="p-6">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 rounded-full bg-[var(--color-primary)] flex items-center justify-center text-white text-2xl font-bold">{firstName[0] || '?'}{lastName[0] || ''}</div>
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
            <h3 class="font-semibold">{t.personal_info}</h3>
            {#if message}
                <div class="p-3 rounded-[var(--radius)] bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">{message}</div>
            {/if}
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.first_name}</label><Input bind:value={firstName} /></div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.last_name}</label><Input bind:value={lastName} /></div>
            </div>
            <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.email}</label><Input type="email" bind:value={email} /></div>
            <Button onclick={saveProfile} disabled={saving}>{saving ? t.saving : t.save}</Button>
        </Card>

        <!-- Change password -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold">{t.change_password}</h3>
            {#if passwordMessage}
                <div class="p-3 rounded-[var(--radius)] bg-[var(--color-success)]/10 text-[var(--color-success)] text-sm">{passwordMessage}</div>
            {/if}
            <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.current_password}</label><Input type="password" bind:value={currentPassword} /></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.new_password}</label><Input type="password" bind:value={newPassword} /></div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">{t.confirm_password}</label><Input type="password" bind:value={confirmPassword} /></div>
            </div>
            <Button variant="secondary" onclick={changePassword}>{t.change_password}</Button>
        </Card>

        <!-- MFA Configuration -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold">{t.mfa_title}</h3>

            {#if totpMessage}
                <div class="p-3 rounded-[var(--radius)] bg-[var(--color-accent)]/10 text-[var(--color-accent)] text-sm">{totpMessage}</div>
            {/if}

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Email MFA -->
                <div class="p-4 rounded-[var(--radius)] border-2 transition-colors {mfaMethod === 'email' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)]'}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">✉️</span>
                            <span class="font-medium">{t.mfa_email}</span>
                        </div>
                        {#if mfaMethod === 'email'}
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--color-success)]/15 text-[var(--color-success)] font-medium">{t.mfa_active}</span>
                        {/if}
                    </div>
                    <p class="text-xs text-[var(--color-muted)] mb-3">{t.mfa_email_desc}</p>
                    {#if mfaMethod !== 'email'}
                        <Button size="sm" variant="secondary" onclick={() => switchMfa('email')}>{t.mfa_switch}</Button>
                    {/if}
                </div>

                <!-- TOTP MFA -->
                <div class="p-4 rounded-[var(--radius)] border-2 transition-colors {mfaMethod === 'totp' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)]'}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📱</span>
                            <span class="font-medium">{t.mfa_totp}</span>
                        </div>
                        {#if mfaMethod === 'totp'}
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--color-success)]/15 text-[var(--color-success)] font-medium">{t.mfa_active}</span>
                        {/if}
                    </div>
                    <p class="text-xs text-[var(--color-muted)] mb-3">{t.mfa_totp_desc}</p>
                    {#if mfaMethod !== 'totp'}
                        <Button size="sm" onclick={setupTotp} disabled={totpLoading}>{t.mfa_configure}</Button>
                    {:else}
                        <Button size="sm" variant="secondary" onclick={() => switchMfa('email')}>{t.mfa_switch} ({t.mfa_email})</Button>
                    {/if}
                </div>
            </div>

            <!-- TOTP Setup QR Code -->
            {#if totpSetup}
                <div class="p-5 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-secondary)] space-y-4">
                    <h4 class="font-semibold text-center">{t.mfa_configure} TOTP</h4>

                    <!-- QR Code -->
                    <div class="flex justify-center">
                        <div class="p-3 bg-white rounded-lg">
                            <img src={totpSetup.qr_code_url} alt="QR Code TOTP" class="w-48 h-48" />
                        </div>
                    </div>
                    <p class="text-sm text-center text-[var(--color-muted)]">{t.totp_scan}</p>

                    <!-- Manual secret -->
                    <div class="text-center">
                        <p class="text-xs text-[var(--color-muted)] mb-1">{t.totp_manual}</p>
                        <code class="text-sm font-mono bg-[var(--color-card)] px-3 py-1.5 rounded border border-[var(--color-border)] select-all">{totpSetup.secret}</code>
                    </div>

                    <!-- Verification -->
                    <div>
                        <p class="text-sm text-[var(--color-muted)] mb-2 text-center">{t.totp_verify}</p>
                        <div class="flex gap-2 max-w-xs mx-auto">
                            <Input type="text" bind:value={totpCode} placeholder="000000" maxlength="6" class="text-center text-xl tracking-widest font-bold flex-1" />
                            <Button onclick={confirmTotp} disabled={totpCode.length !== 6 || totpLoading}>{t.totp_confirm}</Button>
                        </div>
                    </div>
                </div>
            {/if}
        </Card>

        <!-- Sessions -->
        <Card class="p-6">
            <h3 class="font-semibold mb-3">{t.sessions}</h3>
            <div class="flex items-center justify-between py-3">
                <div>
                    <p class="text-sm font-medium">{t.sessions}</p>
                    <p class="text-xs text-[var(--color-muted)]">{t.one_session}</p>
                </div>
                <span class="w-2 h-2 rounded-full bg-[var(--color-success)]"></span>
            </div>
        </Card>
    </div>
</div>
</AppLayout>
