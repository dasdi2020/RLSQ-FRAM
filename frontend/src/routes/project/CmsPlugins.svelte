<script>
    import { get, post } from '$lib/api/client.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { projectSlug = '' } = $props();
    let tenantSlug = $derived(projectSlug);

    let plugins = $state([]);
    let loading = $state(true);

    const iconMap = { 'book-open': '📖', 'calendar-check': '📋', 'calendar': '📅', 'door-open': '🚪', 'credit-card': '💳', 'puzzle': '🧩' };

    // CMS-specific frontend plugins
    const cmsPlugins = [
        { slug: 'carousel', name: 'Carousel', icon: '🎠', desc: 'Carrousel d\'images responsive avec transitions fluides', installed: false },
        { slug: 'seo', name: 'SEO', icon: '🔍', desc: 'Optimisation SEO : meta tags, sitemap, OpenGraph', installed: false },
        { slug: 'gallery', name: 'Galerie', icon: '🖼️', desc: 'Galerie d\'images avec lightbox et filtres', installed: false },
        { slug: 'animations', name: 'Animations', icon: '✨', desc: 'Animations au scroll : fade, slide, zoom, parallax', installed: false },
        { slug: 'social', name: 'Réseaux sociaux', icon: '📱', desc: 'Boutons de partage, feeds Instagram/Twitter', installed: false },
        { slug: 'analytics', name: 'Analytics', icon: '📈', desc: 'Google Analytics, Matomo, statistiques de visites', installed: false },
        { slug: 'newsletter', name: 'Newsletter', icon: '📧', desc: 'Formulaire d\'inscription newsletter + Mailchimp', installed: false },
        { slug: 'cookie-consent', name: 'Cookies', icon: '🍪', desc: 'Bandeau RGPD de consentement aux cookies', installed: false },
        { slug: 'chat', name: 'Chat en ligne', icon: '💬', desc: 'Widget de chat en direct pour le support', installed: false },
        { slug: 'maps', name: 'Cartes', icon: '🗺️', desc: 'Google Maps / OpenStreetMap interactif', installed: false },
    ];

    async function loadPlugins() {
        loading = true;
        try { const r = await get(`/api/t/${tenantSlug}/plugins`); plugins = r.data || []; } catch {}
        loading = false;
    }

    async function installPlugin(slug) {
        try { await post(`/api/t/${tenantSlug}/plugins/${slug}/install`, {}); await loadPlugins(); } catch {}
    }

    function toggleCmsPlugin(slug) {
        cmsPlugins.forEach(p => { if (p.slug === slug) p.installed = !p.installed; });
    }

    $effect(() => { if (tenantSlug) loadPlugins(); });
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center px-6">
    <h2 class="text-lg font-semibold">Plugins</h2>
</header>

<div class="p-6 space-y-6">
    <!-- CMS Frontend Plugins -->
    <div>
        <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide mb-3">Plugins Frontend (CMS)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {#each cmsPlugins as plugin}
                <Card class="p-4 {plugin.installed ? 'border-[var(--color-success)]/30' : ''}">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">{plugin.icon}</span>
                            <div>
                                <h4 class="font-medium text-sm">{plugin.name}</h4>
                                <p class="text-xs text-[var(--color-muted)] mt-0.5">{plugin.desc}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="text-xs px-3 py-1.5 rounded-[var(--radius)] cursor-pointer transition-colors
                            {plugin.installed ? 'bg-[var(--color-success)]/10 text-[var(--color-success)] border border-[var(--color-success)]/20' : 'bg-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-foreground)]'}"
                            onclick={() => toggleCmsPlugin(plugin.slug)}>
                            {plugin.installed ? '✓ Activé' : 'Activer'}
                        </button>
                    </div>
                </Card>
            {/each}
        </div>
    </div>

    <!-- Backend Plugins -->
    {#if plugins.length > 0}
        <div>
            <h3 class="text-sm font-semibold text-[var(--color-muted)] uppercase tracking-wide mb-3">Plugins Backend</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {#each plugins as plugin}
                    <Card class="p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">{iconMap[plugin.icon] || '🧩'}</span>
                                <div>
                                    <h4 class="font-medium text-sm">{plugin.name}</h4>
                                    <p class="text-xs text-[var(--color-muted)]">v{plugin.version}</p>
                                </div>
                            </div>
                            {#if plugin.is_installed}
                                <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--color-success)]/15 text-[var(--color-success)]">{plugin.is_active ? 'Actif' : 'Inactif'}</span>
                            {:else}
                                <Button size="sm" onclick={() => installPlugin(plugin.slug)}>Installer</Button>
                            {/if}
                        </div>
                    </Card>
                {/each}
            </div>
        </div>
    {/if}
</div>
