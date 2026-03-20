<script>
    import { getProject } from '$lib/stores/project.svelte.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { projectSlug = '' } = $props();
    const project = getProject();

    let activeTab = $state('general');
    const tabs = [
        { id: 'general', label: 'Général', icon: '⚙️' },
        { id: 'seo', label: 'SEO', icon: '🔍' },
        { id: 'login', label: 'Login', icon: '🔐' },
        { id: 'deploy', label: 'Déploiement', icon: '🚀' },
    ];
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6">
    <h2 class="text-lg font-semibold">Paramètres du projet</h2>
</header>

<div class="flex-1 overflow-auto">
    <div class="flex border-b border-[var(--color-border)] px-6">
        {#each tabs as tab}
            <button class="px-4 py-2.5 text-sm font-medium border-b-2 cursor-pointer transition-colors
                {activeTab === tab.id ? 'text-[var(--color-primary)] border-[var(--color-primary)]' : 'text-[var(--color-muted)] border-transparent'}"
                onclick={() => activeTab = tab.id}>
                {tab.icon} {tab.label}
            </button>
        {/each}
    </div>

    <div class="p-6">
        {#if activeTab === 'general'}
            <Card class="p-6 space-y-4 max-w-2xl">
                <h3 class="font-semibold">Informations générales</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Nom du projet</label><Input value={project.name || ''} /></div>
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">DNS</label><Input value={project.current?.dns_address || ''} /></div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">DB Host</label><Input value={project.current?.db_host || ''} class="h-9 text-sm" /></div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">DB Name</label><Input value={project.current?.db_name || ''} class="h-9 text-sm" /></div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">DB Port</label><Input value={project.current?.db_port || ''} class="h-9 text-sm" /></div>
                </div>
                <Button>Sauvegarder</Button>
            </Card>
        {:else if activeTab === 'seo'}
            <Card class="p-6 space-y-4 max-w-2xl">
                <h3 class="font-semibold">SEO par défaut</h3>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Titre du site</label><Input placeholder="Mon Site Web" /></div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Description</label><textarea class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm min-h-[80px]" placeholder="Description du site pour les moteurs de recherche"></textarea></div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Favicon URL</label><Input placeholder="/favicon.ico" /></div>
                <Button>Sauvegarder</Button>
            </Card>
        {:else if activeTab === 'login'}
            <Card class="p-6 space-y-4 max-w-2xl">
                <h3 class="font-semibold">Configuration du login</h3>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" checked /> Login activé</label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" checked /> MFA activé</label>
                    <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" /> Inscription publique</label>
                </div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Méthode MFA</label>
                    <div class="flex gap-2">
                        <button class="px-3 py-1.5 rounded border text-sm cursor-pointer border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]">✉️ Email</button>
                        <button class="px-3 py-1.5 rounded border text-sm cursor-pointer border-[var(--color-border)] text-[var(--color-muted)]">📱 TOTP</button>
                    </div>
                </div>
                <Button>Sauvegarder</Button>
            </Card>
        {:else if activeTab === 'deploy'}
            <Card class="p-6 space-y-4 max-w-2xl">
                <h3 class="font-semibold">Déploiement</h3>
                <p class="text-sm text-[var(--color-muted)]">Configurez le déploiement vers votre serveur Plesk.</p>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Plesk Host</label><Input placeholder="https://plesk.monserveur.com:8443" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Login</label><Input placeholder="admin" /></div>
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Mot de passe</label><Input type="password" /></div>
                </div>
                <Button>🚀 Déployer</Button>
            </Card>
        {/if}
    </div>
</div>
