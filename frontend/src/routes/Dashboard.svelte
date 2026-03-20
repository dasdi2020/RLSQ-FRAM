<script>
    import { getAuth } from '$lib/stores/auth.svelte.js';
    import { getI18n } from '$lib/stores/i18n.svelte.js';
    import { get, post, del } from '$lib/api/client.js';
    import AppLayout from '$lib/components/AppLayout.svelte';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';
    import Dialog from '$lib/components/ui/Dialog.svelte';

    const auth = getAuth();
    const i18n = getI18n();
    let t = $derived(i18n.t);
    let activeTab = $state('projects');

    // Tenants
    let tenants = $state([]);
    let tenantsLoading = $state(true);
    let showTenantDialog = $state(false);
    let newTenantName = $state('');

    // Projects
    let projects = $state([]);
    let projectsLoading = $state(true);
    let showProjectWizard = $state(false);
    let wizardStep = $state(1);
    let wizardData = $state({ tenant_id: '', name: '', dns_address: '', db_driver: 'mysql', db_host: 'localhost', db_port: '3306', db_name: '', db_user: '', db_password: '', type: 'website' });
    let wizardError = $state('');
    let wizardCreating = $state(false);

    async function loadTenants() { tenantsLoading = true; try { const r = await get('/api/admin/tenants'); tenants = r.data || []; } catch {} tenantsLoading = false; }
    async function loadProjects() { projectsLoading = true; try { const r = await get('/api/projects'); projects = r.data || []; } catch {} projectsLoading = false; }
    async function createTenant() { if (!newTenantName) return; await post('/api/admin/tenants', { name: newTenantName }); newTenantName = ''; showTenantDialog = false; await loadTenants(); }
    async function provisionTenant(id) { try { await post(`/api/admin/tenants/${id}/provision`); await loadTenants(); } catch {} }
    async function deleteTenant(id) { if (!confirm('Désactiver ?')) return; await del(`/api/admin/tenants/${id}`); await loadTenants(); }
    async function deleteProject(id) { if (!confirm('Supprimer ?')) return; await del(`/api/projects/${id}`); await loadProjects(); }

    function openProjectWizard() {
        wizardStep = 1; wizardError = '';
        wizardData = { tenant_id: tenants[0]?.id || '', name: '', dns_address: '', db_driver: 'mysql', db_host: 'localhost', db_port: '3306', db_name: '', db_user: '', db_password: '', type: 'website' };
        showProjectWizard = true;
    }

    async function createProject() {
        if (!wizardData.name || !wizardData.tenant_id) { wizardError = 'Nom et organisation requis.'; return; }
        wizardCreating = true; wizardError = '';
        try {
            const res = await post('/api/projects', wizardData);
            if (res.error) { wizardError = res.error; wizardCreating = false; return; }
            try { await post(`/api/projects/${res.data.id}/provision`); } catch {}
            showProjectWizard = false; await loadProjects();
        } catch { wizardError = 'Erreur.'; }
        wizardCreating = false;
    }

    const statusColors = { draft: 'var(--color-muted)', active: 'var(--color-success)', deploying: 'var(--color-warning)' };

    $effect(() => { if (auth.isAuthenticated) { loadTenants(); loadProjects(); } });
</script>

<AppLayout>
<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">{t.dashboard}</h2>
    <div class="flex gap-2">
        {#if activeTab === 'tenants'}
            <Button size="sm" onclick={() => showTenantDialog = true}>+ Organisation</Button>
        {:else}
            <Button size="sm" onclick={openProjectWizard}>+ Nouveau projet</Button>
        {/if}
    </div>
</header>

<div class="flex-1 overflow-auto">
    <div class="flex border-b border-[var(--color-border)] px-6">
        <button class="px-5 py-3 text-sm font-medium border-b-2 cursor-pointer transition-colors {activeTab === 'projects' ? 'text-[var(--color-primary)] border-[var(--color-primary)]' : 'text-[var(--color-muted)] border-transparent'}" onclick={() => activeTab = 'projects'}>📁 Projets ({projects.length})</button>
        <button class="px-5 py-3 text-sm font-medium border-b-2 cursor-pointer transition-colors {activeTab === 'tenants' ? 'text-[var(--color-primary)] border-[var(--color-primary)]' : 'text-[var(--color-muted)] border-transparent'}" onclick={() => activeTab = 'tenants'}>🏛️ {t.organizations} ({tenants.length})</button>
    </div>

    <div class="p-6">
        {#if activeTab === 'projects'}
            {#if projectsLoading}
                <p class="text-[var(--color-muted)]">{t.loading}</p>
            {:else if projects.length === 0}
                <div class="flex flex-col items-center justify-center py-16">
                    <span class="text-6xl mb-4 opacity-30">📁</span>
                    <p class="text-lg font-medium mb-2">Aucun projet</p>
                    <p class="text-sm text-[var(--color-muted)] mb-6">Créez votre premier projet pour commencer</p>
                    <Button onclick={openProjectWizard}>+ Nouveau projet</Button>
                </div>
            {:else}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {#each projects as project}
                        <Card class="overflow-hidden hover:border-[var(--color-primary)] transition-colors">
                            <div class="p-5">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="font-semibold">{project.name}</h3>
                                        <p class="text-xs text-[var(--color-muted)] font-mono">{project.slug}</p>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background: {statusColors[project.status] || statusColors.draft}20; color: {statusColors[project.status] || statusColors.draft};">{project.status}</span>
                                </div>
                                <div class="space-y-1 text-sm text-[var(--color-muted)]">
                                    <div class="flex justify-between"><span>Type</span><span>{project.type === 'website' ? '🌐 Site web' : '💻 App'}</span></div>
                                    <div class="flex justify-between"><span>Org.</span><span class="text-[var(--color-foreground)]">{project.tenant_name || '—'}</span></div>
                                    {#if project.dns_address}<div class="flex justify-between"><span>DNS</span><span class="font-mono text-xs">{project.dns_address}</span></div>{/if}
                                </div>
                            </div>
                            <div class="flex border-t border-[var(--color-border)] bg-[var(--color-secondary)]/50 text-xs">
                                <a href="#/p/{project.slug}" class="flex-1 py-2.5 text-center hover:bg-[var(--color-border)]/50 cursor-pointer text-[var(--color-primary)] font-medium">Ouvrir</a>
                                <button class="py-2.5 px-3 hover:bg-[var(--color-border)]/50 cursor-pointer border-l border-[var(--color-border)] text-[var(--color-destructive)]" onclick={() => deleteProject(project.id)}>✕</button>
                            </div>
                        </Card>
                    {/each}
                </div>
            {/if}
        {:else}
            <Card>
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-[var(--color-border)] bg-[var(--color-secondary)]/30">
                        <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Organisation</th>
                        <th class="text-left p-3 text-xs text-[var(--color-muted)] uppercase">Slug</th>
                        <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase">DB</th>
                        <th class="text-center p-3 text-xs text-[var(--color-muted)] uppercase">{t.status}</th>
                        <th class="p-3 w-32"></th>
                    </tr></thead>
                    <tbody>
                        {#each tenants as tenant}
                            <tr class="border-b border-[var(--color-border)]/50 hover:bg-[var(--color-border)]/20 group">
                                <td class="p-3 font-medium">{tenant.name}</td>
                                <td class="p-3 font-mono text-xs text-[var(--color-accent)]">{tenant.slug}</td>
                                <td class="p-3 text-center">{tenant.is_provisioned == 1 ? '✓' : '—'}</td>
                                <td class="p-3 text-center"><span class="w-2 h-2 rounded-full inline-block {tenant.is_active == 1 ? 'bg-[var(--color-success)]' : 'bg-[var(--color-muted)]'}"></span></td>
                                <td class="p-3 text-right"><div class="flex gap-2 justify-end opacity-0 group-hover:opacity-100">
                                    {#if !tenant.is_provisioned}<button class="text-xs text-[var(--color-accent)] cursor-pointer hover:underline" onclick={() => provisionTenant(tenant.id)}>Provisionner</button>{/if}
                                    <button class="text-xs text-[var(--color-destructive)] cursor-pointer hover:underline" onclick={() => deleteTenant(tenant.id)}>{t.delete}</button>
                                </div></td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </Card>
        {/if}
    </div>
</div>

<!-- Tenant Dialog -->
<Dialog bind:open={showTenantDialog}>
    <div class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Nouvelle organisation</h3>
        <Input placeholder="Fédération Québec" bind:value={newTenantName} />
        <div class="flex gap-2 justify-end"><Button variant="secondary" onclick={() => showTenantDialog = false}>{t.cancel}</Button><Button onclick={createTenant}>{t.create}</Button></div>
    </div>
</Dialog>

<!-- Project Wizard -->
<Dialog bind:open={showProjectWizard} class="max-w-2xl">
    <div class="flex flex-col" style="min-height: 520px;">
        <div class="px-6 py-4 border-b border-[var(--color-border)] flex items-center justify-between">
            <div class="flex items-center gap-3">
                {#if wizardStep > 1}<button class="text-[var(--color-muted)] hover:text-[var(--color-foreground)] cursor-pointer text-lg" onclick={() => wizardStep--}>←</button>{/if}
                <h3 class="text-lg font-semibold">{wizardStep === 1 ? 'Configuration du projet' : 'Type de projet'}</h3>
            </div>
            <div class="flex gap-1.5"><div class="w-3 h-3 rounded-full {wizardStep >= 1 ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]'}"></div><div class="w-3 h-3 rounded-full {wizardStep >= 2 ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]'}"></div></div>
        </div>

        {#if wizardError}<div class="mx-6 mt-4 p-3 rounded-[var(--radius)] bg-[var(--color-destructive)]/10 text-[var(--color-destructive)] text-sm">{wizardError}</div>{/if}

        {#if wizardStep === 1}
            <div class="p-6 flex-1 space-y-4 overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-[var(--color-muted)] mb-1 block">Organisation <span class="text-[var(--color-destructive)]">*</span></label>
                        <select class="flex h-10 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-3 py-2 text-sm" bind:value={wizardData.tenant_id}>
                            <option value="">Sélectionner...</option>
                            {#each tenants as tenant}<option value={tenant.id}>{tenant.name}</option>{/each}
                        </select>
                    </div>
                    <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Nom du projet <span class="text-[var(--color-destructive)]">*</span></label><Input placeholder="Mon Site Web" bind:value={wizardData.name} /></div>
                </div>
                <div><label class="text-sm text-[var(--color-muted)] mb-1 block">Adresse DNS</label><Input placeholder="www.monsite.com" bind:value={wizardData.dns_address} /></div>

                <h4 class="text-sm font-semibold flex items-center gap-2 pt-2">🗄️ Base de données</h4>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Driver</label>
                        <select class="flex h-9 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 text-sm" bind:value={wizardData.db_driver}>
                            <option value="mysql">MySQL</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option>
                        </select>
                    </div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Host</label><Input placeholder="localhost" bind:value={wizardData.db_host} class="h-9 text-sm" /></div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Port</label><Input placeholder="3306" bind:value={wizardData.db_port} class="h-9 text-sm" /></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Base de données</label><Input placeholder="nom_db" bind:value={wizardData.db_name} class="h-9 text-sm" /></div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Utilisateur</label><Input placeholder="root" bind:value={wizardData.db_user} class="h-9 text-sm" /></div>
                    <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Mot de passe</label><Input type="password" placeholder="••••" bind:value={wizardData.db_password} class="h-9 text-sm" /></div>
                </div>
                <div class="flex justify-end pt-2"><Button onclick={() => { if (wizardData.name && wizardData.tenant_id) wizardStep = 2; else wizardError = 'Nom et organisation requis.'; }}>Suivant →</Button></div>
            </div>
        {:else}
            <div class="p-6 flex-1 space-y-4">
                <p class="text-sm text-[var(--color-muted)]">Quel type de projet souhaitez-vous créer ?</p>
                <div class="grid grid-cols-2 gap-4">
                    <button class="p-6 rounded-[var(--radius)] border-2 text-left cursor-pointer transition-all {wizardData.type === 'website' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}" onclick={() => wizardData.type = 'website'}>
                        <span class="text-4xl block mb-3">🌐</span><h4 class="font-semibold mb-1">Site Web</h4>
                        <p class="text-xs text-[var(--color-muted)]">CMS complet avec page builder, menus, plugins, formulaires et login configurable.</p>
                        <div class="flex flex-wrap gap-1 mt-3">{#each ['CMS', 'Pages', 'Menus', 'Plugins', 'SEO'] as tag}<span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-primary)]/10 text-[var(--color-primary)]">{tag}</span>{/each}</div>
                    </button>
                    <button class="p-6 rounded-[var(--radius)] border-2 text-left cursor-pointer transition-all {wizardData.type === 'webapp' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5' : 'border-[var(--color-border)] hover:border-[var(--color-accent)]'}" onclick={() => wizardData.type = 'webapp'}>
                        <span class="text-4xl block mb-3">💻</span><h4 class="font-semibold mb-1">Application Web</h4>
                        <p class="text-xs text-[var(--color-muted)]">Dashboard personnalisable, gestion de données, API REST/GraphQL, auth et éditeur de code.</p>
                        <div class="flex flex-wrap gap-1 mt-3">{#each ['API', 'Dashboard', 'CRUD', 'Auth', 'Code'] as tag}<span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--color-accent)]/10 text-[var(--color-accent)]">{tag}</span>{/each}</div>
                    </button>
                </div>
                <Card class="p-4"><h4 class="text-xs text-[var(--color-muted)] uppercase tracking-wide mb-2">Résumé</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Projet</span><span class="font-medium">{wizardData.name}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">Type</span><span>{wizardData.type === 'website' ? '🌐 Site' : '💻 App'}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">DNS</span><span class="font-mono text-xs">{wizardData.dns_address || '—'}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-muted)]">DB</span><span class="font-mono text-xs">{wizardData.db_driver}://{wizardData.db_host}:{wizardData.db_port}</span></div>
                    </div>
                </Card>
                <div class="flex justify-between pt-2">
                    <Button variant="secondary" onclick={() => wizardStep = 1}>← Retour</Button>
                    <Button onclick={createProject} disabled={wizardCreating}>
                        {#if wizardCreating}<svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Création...{:else}Créer le projet{/if}
                    </Button>
                </div>
            </div>
        {/if}
    </div>
</Dialog>
</AppLayout>
