<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';

    let { message = '' } = $props();
    const auth = getAuth();
</script>

<div class="p-6">
    <h3 class="text-lg font-semibold mb-2">
        Bienvenue{#if auth.user}, {auth.user.first_name}{/if} !
    </h3>
    <p class="text-[var(--color-muted)]">{message || 'Votre espace est prêt.'}</p>
</div>
