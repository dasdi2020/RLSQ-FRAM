<script>
    /**
     * Router interne pour les pages du projet CMS.
     * Reçoit le slug du projet depuis le router parent et dispatch vers le bon composant.
     */
    import CmsLayout from '$lib/components/CmsLayout.svelte';
    import CmsDashboard from './CmsDashboard.svelte';
    import CmsPages from './CmsPages.svelte';
    import CmsMenus from './CmsMenus.svelte';
    import CmsPlugins from './CmsPlugins.svelte';
    import PageBuilderV2 from './PageBuilderV2.svelte';
    import CmsSettings from './CmsSettings.svelte';
    import CmsCodeEditor from './CmsCodeEditor.svelte';
    import CmsLoginDesigner from './CmsLoginDesigner.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { params = {} } = $props();
    let slug = $derived(params.slug || '');
    let subPath = $state('');

    // Parse the sub-path from hash
    $effect(() => {
        const hash = window.location.hash.replace('#', '');
        const prefix = `/p/${slug}`;
        subPath = hash.startsWith(prefix) ? hash.slice(prefix.length) || '' : '';
    });
    $effect(() => {
        const h = () => {
            const hash = window.location.hash.replace('#', '');
            const prefix = `/p/${slug}`;
            subPath = hash.startsWith(prefix) ? hash.slice(prefix.length) || '' : '';
        };
        window.addEventListener('hashchange', h);
        return () => window.removeEventListener('hashchange', h);
    });
</script>

<CmsLayout projectSlug={slug}>
    {#if subPath === '' || subPath === '/'}
        <CmsDashboard projectSlug={slug} />
    {:else if subPath.match(/^\/pages\/\d+/)}
        <PageBuilderV2 projectSlug={slug} pageId={subPath.replace('/pages/', '')} />
    {:else if subPath.startsWith('/pages')}
        <CmsPages projectSlug={slug} />
    {:else if subPath.startsWith('/menus')}
        <CmsMenus projectSlug={slug} />
    {:else if subPath.startsWith('/plugins')}
        <CmsPlugins projectSlug={slug} />
    {:else if subPath.startsWith('/settings')}
        <CmsSettings projectSlug={slug} />
    {:else if subPath.startsWith('/code')}
        <CmsCodeEditor projectSlug={slug} />
    {:else if subPath.startsWith('/login-design')}
        <CmsLoginDesigner projectSlug={slug} />
    {:else if subPath.startsWith('/database') || subPath.startsWith('/erd') || subPath.startsWith('/forms') || subPath.startsWith('/media') || subPath.startsWith('/login-design') || subPath.startsWith('/preview') || subPath.startsWith('/deploy')}
        <!-- Placeholder pour les pages en développement -->
        <header class="h-14 border-b border-[var(--color-border)] flex items-center px-6">
            <h2 class="text-lg font-semibold capitalize">{subPath.replace('/', '')}</h2>
        </header>
        <div class="p-6 flex-1">
            <Card class="p-8 text-center">
                <span class="text-5xl block mb-4">🚧</span>
                <h3 class="text-lg font-semibold mb-2">En construction</h3>
                <p class="text-sm text-[var(--color-muted)]">Cette section sera disponible dans la prochaine phase.</p>
            </Card>
        </div>
    {:else}
        <div class="p-6"><p class="text-[var(--color-muted)]">Page introuvable : {subPath}</p></div>
    {/if}
</CmsLayout>
