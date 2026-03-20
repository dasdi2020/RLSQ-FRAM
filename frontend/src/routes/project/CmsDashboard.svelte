<script>
    import { getProject } from '$lib/stores/project.svelte.js';
    import Card from '$lib/components/ui/Card.svelte';
    import Button from '$lib/components/ui/Button.svelte';

    let { projectSlug = '' } = $props();
    const project = getProject();

    const quickActions = [
        { path: 'pages', icon: '📄', label: 'Gérer les pages', desc: 'Créer et éditer les pages du site' },
        { path: 'menus', icon: '☰', label: 'Gérer les menus', desc: 'Configurer la navigation' },
        { path: 'database', icon: '🗄️', label: 'Base de données', desc: 'Tables et champs' },
        { path: 'plugins', icon: '🧩', label: 'Plugins', desc: 'Activer des fonctionnalités' },
        { path: 'forms', icon: '📝', label: 'Formulaires', desc: 'Créer des formulaires' },
        { path: 'code', icon: '💻', label: 'Éditeur de code', desc: 'Écrire du code custom' },
        { path: 'login-design', icon: '🔐', label: 'Page de login', desc: 'Personnaliser le login' },
        { path: 'preview', icon: '👁️', label: 'Preview', desc: 'Voir le rendu du site' },
    ];
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">Dashboard du projet</h2>
    <div class="flex gap-2">
        <a href="#/p/{projectSlug}/preview"><Button size="sm" variant="secondary">👁️ Preview</Button></a>
        <a href="#/p/{projectSlug}/deploy"><Button size="sm">🚀 Déployer</Button></a>
    </div>
</header>

<div class="p-6 space-y-6">
    <!-- Project info -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card class="p-4">
            <p class="text-[10px] text-[var(--color-muted)] uppercase tracking-wide">Projet</p>
            <p class="text-xl font-bold mt-1">{project.name || projectSlug}</p>
        </Card>
        <Card class="p-4">
            <p class="text-[10px] text-[var(--color-muted)] uppercase tracking-wide">Type</p>
            <p class="text-xl font-bold mt-1">{project.type === 'website' ? '🌐 Site web' : '💻 Application'}</p>
        </Card>
        <Card class="p-4">
            <p class="text-[10px] text-[var(--color-muted)] uppercase tracking-wide">Statut</p>
            <p class="text-xl font-bold mt-1 text-[var(--color-success)]">{project.current?.status || 'draft'}</p>
        </Card>
        <Card class="p-4">
            <p class="text-[10px] text-[var(--color-muted)] uppercase tracking-wide">DNS</p>
            <p class="text-sm font-mono mt-2 truncate">{project.current?.dns_address || '—'}</p>
        </Card>
    </div>

    <!-- Quick actions -->
    <div>
        <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide mb-3">Actions rapides</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            {#each quickActions as action}
                <a href="#/p/{projectSlug}/{action.path}" class="p-4 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] hover:border-[var(--color-primary)] transition-colors block group">
                    <span class="text-2xl block mb-2 group-hover:scale-110 transition-transform inline-block">{action.icon}</span>
                    <h4 class="font-medium text-sm">{action.label}</h4>
                    <p class="text-[11px] text-[var(--color-muted)] mt-0.5">{action.desc}</p>
                </a>
            {/each}
        </div>
    </div>

    <!-- Template config -->
    {#if project.current?.template_config}
        <Card class="p-5">
            <h3 class="font-semibold mb-3">Configuration du template</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {project.current.template_config.cms_enabled ? 'bg-[var(--color-success)]' : 'bg-[var(--color-muted)]'}"></span>
                    CMS {project.current.template_config.cms_enabled ? 'activé' : 'désactivé'}
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {project.current.template_config.has_frontend ? 'bg-[var(--color-success)]' : 'bg-[var(--color-muted)]'}"></span>
                    Frontend
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {project.current.template_config.has_backend ? 'bg-[var(--color-success)]' : 'bg-[var(--color-muted)]'}"></span>
                    Backend
                </div>
                <div class="text-[var(--color-muted)]">
                    {(project.current.template_config.default_plugins || []).length} plugins par défaut
                </div>
            </div>
        </Card>
    {/if}
</div>
