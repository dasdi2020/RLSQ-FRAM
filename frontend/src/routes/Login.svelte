<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import { push } from 'svelte-spa-router';

    const auth = getAuth();

    let email = $state('');
    let password = $state('');
    let code = $state('');

    async function handleLogin(e) {
        e.preventDefault();
        const ok = await auth.login(email, password);
    }

    async function handleVerify(e) {
        e.preventDefault();
        const ok = await auth.verify2FA(code);
        if (ok) {
            push('/dashboard');
        }
    }
</script>

<div class="min-h-screen flex items-center justify-center p-4">
    <Card class="w-full max-w-md">
        <div class="p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold">
                    <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
                </h1>
                <p class="text-[var(--color-muted)] mt-2">
                    {#if auth.requires2FA}
                        Entrez le code envoyé à votre email
                    {:else}
                        Connectez-vous à votre compte
                    {/if}
                </p>
            </div>

            <!-- Error -->
            {#if auth.error}
                <div class="mb-4 p-3 rounded-[var(--radius)] bg-[var(--color-destructive)]/10 border border-[var(--color-destructive)]/30 text-[var(--color-destructive)] text-sm">
                    {auth.error}
                </div>
            {/if}

            {#if !auth.requires2FA}
                <!-- Login Form -->
                <form onsubmit={handleLogin} class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-[var(--color-muted)] mb-1.5 block" for="email">Email</label>
                        <Input id="email" type="email" placeholder="admin@rlsq-fram.local" bind:value={email} required />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[var(--color-muted)] mb-1.5 block" for="password">Mot de passe</label>
                        <Input id="password" type="password" placeholder="••••••••" bind:value={password} required />
                    </div>
                    <Button class="w-full" disabled={auth.isLoading}>
                        {auth.isLoading ? 'Connexion...' : 'Se connecter'}
                    </Button>
                </form>
            {:else}
                <!-- 2FA Form -->
                <form onsubmit={handleVerify} class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-[var(--color-muted)] mb-1.5 block" for="code">Code de vérification</label>
                        <Input
                            id="code"
                            type="text"
                            placeholder="000000"
                            bind:value={code}
                            maxlength="6"
                            class="text-center text-2xl tracking-[0.5em] font-bold"
                            required
                        />
                    </div>
                    <Button class="w-full" disabled={auth.isLoading}>
                        {auth.isLoading ? 'Vérification...' : 'Vérifier'}
                    </Button>
                    <button type="button" class="w-full text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer" onclick={() => { auth.logout(); code = ''; }}>
                        Retour
                    </button>
                </form>
            {/if}
        </div>
    </Card>
</div>
