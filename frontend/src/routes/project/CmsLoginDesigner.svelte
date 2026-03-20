<script>
    import { put } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { projectSlug = '' } = $props();

    let config = $state({
        layout: 'centered',
        logo_url: '',
        app_name: 'Mon Application',
        welcome_text: 'Connectez-vous à votre compte',
        primary_color: '#ff3e00',
        background_type: 'gradient',
        background_color: '#0f0f1a',
        background_gradient_from: '#1a1a2e',
        background_gradient_to: '#0f3460',
        background_image: '',
        card_bg: 'rgba(26,26,46,0.9)',
        card_border_radius: '12',
        text_color: '#e0e0e0',
        mfa_enabled: true,
        mfa_method: 'email',
        allow_registration: false,
        show_forgot_password: true,
        show_social_login: false,
        social_providers: [],
        custom_css: '',
    });

    let activePanel = $state('layout');

    const layouts = [
        { id: 'centered', label: 'Centré', icon: '⬜', desc: 'Carte au centre' },
        { id: 'split-left', label: 'Split gauche', icon: '◧', desc: 'Image gauche, login droite' },
        { id: 'split-right', label: 'Split droite', icon: '◨', desc: 'Login gauche, image droite' },
        { id: 'fullscreen', label: 'Plein écran', icon: '▣', desc: 'Formulaire sur image de fond' },
    ];

    const panels = [
        { id: 'layout', label: 'Mise en page', icon: '📐' },
        { id: 'branding', label: 'Branding', icon: '🎨' },
        { id: 'colors', label: 'Couleurs', icon: '🖌' },
        { id: 'auth', label: 'Authentification', icon: '🔐' },
        { id: 'advanced', label: 'Avancé', icon: '⚙️' },
    ];

    async function saveConfig() {
        try {
            await put(`/api/projects/${projectSlug}`, { login_config: config });
        } catch {}
    }

    // Live preview styles
    let previewBg = $derived(() => {
        if (config.background_type === 'gradient') return `linear-gradient(135deg, ${config.background_gradient_from}, ${config.background_gradient_to})`;
        if (config.background_type === 'image' && config.background_image) return `url(${config.background_image}) center/cover`;
        return config.background_color;
    });
</script>

<div class="flex h-full overflow-hidden">
    <!-- Config panels -->
    <div class="w-80 flex-shrink-0 bg-[var(--color-secondary)] border-r border-[var(--color-border)] flex flex-col overflow-hidden">
        <div class="p-3 border-b border-[var(--color-border)] flex items-center justify-between">
            <span class="text-sm font-semibold">Login Designer</span>
            <Button size="sm" onclick={saveConfig}>💾 Sauvegarder</Button>
        </div>

        <!-- Panel tabs -->
        <div class="flex border-b border-[var(--color-border)]">
            {#each panels as panel}
                <button class="flex-1 py-2 text-xs text-center cursor-pointer transition-colors border-b-2
                    {activePanel === panel.id ? 'text-[var(--color-primary)] border-[var(--color-primary)]' : 'text-[var(--color-muted)] border-transparent'}"
                    onclick={() => activePanel = panel.id}>
                    {panel.icon}
                </button>
            {/each}
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-4">
            {#if activePanel === 'layout'}
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-2 block">Disposition</label>
                    <div class="grid grid-cols-2 gap-2">
                        {#each layouts as l}
                            <button class="p-3 rounded-[var(--radius)] border text-center cursor-pointer transition-colors text-xs
                                {config.layout === l.id ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}"
                                onclick={() => config.layout = l.id}>
                                <span class="text-2xl block mb-1">{l.icon}</span>
                                <span class="font-medium">{l.label}</span>
                            </button>
                        {/each}
                    </div>
                </div>
            {:else if activePanel === 'branding'}
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Nom de l'application</label>
                    <Input bind:value={config.app_name} class="h-8 text-sm" />
                </div>
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Texte d'accueil</label>
                    <Input bind:value={config.welcome_text} class="h-8 text-sm" />
                </div>
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">URL du logo</label>
                    <Input bind:value={config.logo_url} placeholder="https://..." class="h-8 text-sm" />
                </div>
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Border radius de la carte</label>
                    <input type="range" min="0" max="24" bind:value={config.card_border_radius} class="w-full" />
                    <span class="text-xs text-[var(--color-muted)]">{config.card_border_radius}px</span>
                </div>
            {:else if activePanel === 'colors'}
                <div class="space-y-3">
                    <div>
                        <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Couleur primaire</label>
                        <div class="flex gap-2 items-center"><input type="color" bind:value={config.primary_color} class="w-8 h-8 rounded cursor-pointer" /><Input bind:value={config.primary_color} class="h-8 text-xs flex-1" /></div>
                    </div>
                    <div>
                        <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Fond</label>
                        <div class="flex gap-1 mb-2">
                            {#each ['solid', 'gradient', 'image'] as bg}
                                <button class="flex-1 py-1 text-xs rounded cursor-pointer {config.background_type === bg ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-border)] text-[var(--color-muted)]'}"
                                    onclick={() => config.background_type = bg}>{bg}</button>
                            {/each}
                        </div>
                        {#if config.background_type === 'solid'}
                            <div class="flex gap-2 items-center"><input type="color" bind:value={config.background_color} class="w-8 h-8 rounded cursor-pointer" /><Input bind:value={config.background_color} class="h-8 text-xs flex-1" /></div>
                        {:else if config.background_type === 'gradient'}
                            <div class="flex gap-2 items-center mb-1"><input type="color" bind:value={config.background_gradient_from} class="w-8 h-8 rounded cursor-pointer" /><span class="text-xs text-[var(--color-muted)]">→</span><input type="color" bind:value={config.background_gradient_to} class="w-8 h-8 rounded cursor-pointer" /></div>
                        {:else}
                            <Input bind:value={config.background_image} placeholder="URL de l'image" class="h-8 text-xs" />
                        {/if}
                    </div>
                    <div>
                        <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Couleur du texte</label>
                        <div class="flex gap-2 items-center"><input type="color" bind:value={config.text_color} class="w-8 h-8 rounded cursor-pointer" /><Input bind:value={config.text_color} class="h-8 text-xs flex-1" /></div>
                    </div>
                </div>
            {:else if activePanel === 'auth'}
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={config.mfa_enabled} /> MFA activé</label>
                    {#if config.mfa_enabled}
                        <div>
                            <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">Méthode MFA</label>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 text-xs rounded border cursor-pointer {config.mfa_method === 'email' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)]'}" onclick={() => config.mfa_method = 'email'}>✉️ Email</button>
                                <button class="flex-1 py-2 text-xs rounded border cursor-pointer {config.mfa_method === 'totp' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)]'}" onclick={() => config.mfa_method = 'totp'}>📱 TOTP</button>
                                <button class="flex-1 py-2 text-xs rounded border cursor-pointer {config.mfa_method === 'both' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)]'}" onclick={() => config.mfa_method = 'both'}>Les deux</button>
                            </div>
                        </div>
                    {/if}
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={config.allow_registration} /> Inscription publique</label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={config.show_forgot_password} /> Mot de passe oublié</label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={config.show_social_login} /> Login social</label>
                    {#if config.show_social_login}
                        <div class="flex flex-wrap gap-1">
                            {#each ['Google', 'Facebook', 'GitHub', 'Apple'] as provider}
                                <button class="px-2 py-1 text-xs rounded border cursor-pointer
                                    {config.social_providers.includes(provider) ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10' : 'border-[var(--color-border)]'}"
                                    onclick={() => { config.social_providers = config.social_providers.includes(provider) ? config.social_providers.filter(p=>p!==provider) : [...config.social_providers, provider]; }}>
                                    {provider}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>
            {:else if activePanel === 'advanced'}
                <div>
                    <label class="text-[11px] text-[var(--color-muted)] uppercase tracking-wide mb-1 block">CSS personnalisé</label>
                    <textarea class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 py-1.5 text-xs font-mono min-h-[120px]"
                        bind:value={config.custom_css} placeholder="Votre CSS ici..."></textarea>
                </div>
            {/if}
        </div>
    </div>

    <!-- Live Preview -->
    <div class="flex-1 overflow-auto flex items-center justify-center p-6" style="background: {previewBg()};">
        <div class="{config.layout === 'centered' ? 'w-full max-w-md' : config.layout.includes('split') ? 'w-full max-w-4xl flex' : 'w-full max-w-md'}">
            {#if config.layout.includes('split')}
                <!-- Split layout -->
                <div class="flex w-full rounded-xl overflow-hidden shadow-2xl" style="min-height: 500px;">
                    {#if config.layout === 'split-right'}
                        <div class="flex-1 p-8 flex flex-col justify-center" style="background: {config.card_bg}; border-radius: {config.card_border_radius}px 0 0 {config.card_border_radius}px;">
                            {@render loginForm()}
                        </div>
                        <div class="flex-1 bg-cover bg-center" style="background: {config.primary_color}; display: flex; align-items: center; justify-content: center;">
                            <span class="text-white text-6xl opacity-30">🌐</span>
                        </div>
                    {:else}
                        <div class="flex-1 bg-cover bg-center" style="background: {config.primary_color}; display: flex; align-items: center; justify-content: center;">
                            <span class="text-white text-6xl opacity-30">🌐</span>
                        </div>
                        <div class="flex-1 p-8 flex flex-col justify-center" style="background: {config.card_bg}; border-radius: 0 {config.card_border_radius}px {config.card_border_radius}px 0;">
                            {@render loginForm()}
                        </div>
                    {/if}
                </div>
            {:else}
                <!-- Centered / Fullscreen -->
                <div class="p-8 shadow-2xl" style="background: {config.card_bg}; border-radius: {config.card_border_radius}px; color: {config.text_color};">
                    {@render loginForm()}
                </div>
            {/if}
        </div>
    </div>
</div>

{#snippet loginForm()}
    <div class="space-y-5" style="color: {config.text_color};">
        <!-- Logo/Brand -->
        <div class="text-center">
            {#if config.logo_url}
                <img src={config.logo_url} alt="Logo" class="h-12 mx-auto mb-3" />
            {:else}
                <div class="w-14 h-14 rounded-xl mx-auto mb-3 flex items-center justify-center text-white text-xl font-bold" style="background: {config.primary_color};">
                    {config.app_name?.[0] || 'A'}
                </div>
            {/if}
            <h2 class="text-xl font-bold">{config.app_name}</h2>
            <p class="text-sm opacity-60 mt-1">{config.welcome_text}</p>
        </div>

        <!-- Social login -->
        {#if config.show_social_login && config.social_providers.length > 0}
            <div class="space-y-2">
                {#each config.social_providers as provider}
                    <button class="w-full py-2.5 rounded-lg border text-sm font-medium flex items-center justify-center gap-2" style="border-color: {config.text_color}30;">
                        {provider === 'Google' ? '🔵' : provider === 'Facebook' ? '🔷' : provider === 'GitHub' ? '⬛' : '🍎'} Continuer avec {provider}
                    </button>
                {/each}
                <div class="flex items-center gap-2 py-1"><div class="flex-1 border-t" style="border-color: {config.text_color}20;"></div><span class="text-xs opacity-40">ou</span><div class="flex-1 border-t" style="border-color: {config.text_color}20;"></div></div>
            </div>
        {/if}

        <!-- Form -->
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium opacity-60 block mb-1">Email</label>
                <input type="email" placeholder="vous@exemple.com" class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: {config.text_color}10; border: 1px solid {config.text_color}20; color: {config.text_color};" />
            </div>
            <div>
                <label class="text-xs font-medium opacity-60 block mb-1">Mot de passe</label>
                <input type="password" placeholder="••••••••" class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: {config.text_color}10; border: 1px solid {config.text_color}20; color: {config.text_color};" />
            </div>
        </div>

        {#if config.show_forgot_password}
            <div class="text-right"><a href="#" class="text-xs" style="color: {config.primary_color};">Mot de passe oublié ?</a></div>
        {/if}

        <button class="w-full py-2.5 rounded-lg text-sm font-semibold text-white" style="background: {config.primary_color};">
            Se connecter
        </button>

        {#if config.mfa_enabled}
            <p class="text-center text-xs opacity-40">
                🔒 {config.mfa_method === 'email' ? 'Vérification par email' : config.mfa_method === 'totp' ? 'Vérification par app' : 'Double authentification'} activée
            </p>
        {/if}

        {#if config.allow_registration}
            <p class="text-center text-sm opacity-60">Pas encore de compte ? <a href="#" style="color: {config.primary_color};">S'inscrire</a></p>
        {/if}
    </div>
{/snippet}
