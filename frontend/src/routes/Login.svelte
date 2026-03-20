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
        await auth.login(email, password);
    }

    async function handleVerify(e) {
        e.preventDefault();
        const ok = await auth.verify2FA(code);
        if (ok) {
            push('/dashboard');
        }
    }
</script>

<div class="min-h-screen flex items-center justify-center p-4" style="background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #0f3460 100%);">
    <Card class="w-full max-w-md shadow-2xl">
        <div class="p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl bg-[var(--color-primary)] flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">R</div>
                <h1 class="text-3xl font-bold">
                    <span class="text-[var(--color-primary)]">RLSQ</span><span class="text-[var(--color-accent)]">-FRAM</span>
                </h1>
                <p class="text-[var(--color-muted)] mt-2">
                    {#if auth.requires2FA}
                        Code de vérification envoyé par email
                    {:else}
                        Connectez-vous à votre compte
                    {/if}
                </p>
            </div>

            <!-- Error -->
            {#if auth.error}
                <div class="mb-4 p-3 rounded-[var(--radius)] bg-[var(--color-destructive)]/10 border border-[var(--color-destructive)]/30 text-[var(--color-destructive)] text-sm flex items-center gap-2">
                    <span>&#9888;</span>
                    <span>{auth.error}</span>
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
                        {#if auth.isLoading}
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Connexion en cours...
                        {:else}
                            Se connecter
                        {/if}
                    </Button>
                </form>

                <p class="text-xs text-[var(--color-muted-foreground)] text-center mt-6">
                    Double authentification activée par défaut
                </p>
            {:else}
                <!-- 2FA Form -->
                <form onsubmit={handleVerify} class="space-y-4">
                    <div class="text-center mb-2">
                        <div class="w-12 h-12 rounded-full bg-[var(--color-accent)]/10 flex items-center justify-center mx-auto mb-3">
                            <span class="text-2xl">&#128231;</span>
                        </div>
                        <p class="text-sm text-[var(--color-muted)]">
                            Un code à 6 chiffres a été envoyé à<br>
                            <span class="text-[var(--color-foreground)] font-medium">{email}</span>
                        </p>
                    </div>
                    <div>
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
                        {#if auth.isLoading}
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Vérification...
                        {:else}
                            Vérifier le code
                        {/if}
                    </Button>
                    <button type="button" class="w-full text-sm text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer py-2" onclick={() => { auth.logout(); code = ''; }}>
                        &#8592; Retour à la connexion
                    </button>
                </form>
            {/if}
        </div>
    </Card>
</div>
