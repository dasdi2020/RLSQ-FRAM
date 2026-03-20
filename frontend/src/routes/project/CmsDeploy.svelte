<script>
    import { get, post } from '$lib/api/client.js';
    import { getProject } from '$lib/stores/project.svelte.js';
    import Button from '$lib/components/ui/Button.svelte';
    import Input from '$lib/components/ui/Input.svelte';
    import Card from '$lib/components/ui/Card.svelte';

    let { projectSlug = '' } = $props();
    const project = getProject();

    let deployStep = $state('config'); // config → generating → deploying → done
    let deployConfig = $state({
        target: 'production',
        domain: '',
        plesk_host: '',
        plesk_login: '',
        plesk_password: '',
        ssl: true,
        create_database: true,
    });
    let deployLog = $state([]);
    let deployStatus = $state('');
    let versions = $state([]);
    let selectedVersion = $state('');
    let generating = $state(false);
    let deploying = $state(false);

    async function loadVersions() {
        try {
            const id = project.current?.id;
            if (!id) return;
            const tenantId = project.current?.tenant_id;
            if (!tenantId) return;
            const res = await get(`/api/admin/tenants/${tenantId}/versions`);
            versions = res.data || [];
        } catch {}
    }

    async function createSnapshot() {
        try {
            const tenantId = project.current?.tenant_id;
            if (!tenantId) return;
            const tag = `v${new Date().toISOString().replace(/[T:]/g, '-').substring(0, 19)}`;
            await post(`/api/admin/tenants/${tenantId}/versions`, {
                version_tag: tag,
                description: `Snapshot avant déploiement — ${projectSlug}`,
            });
            addLog('✅ Snapshot créé : ' + tag);
            await loadVersions();
        } catch (e) { addLog('❌ Erreur snapshot : ' + e.message); }
    }

    async function generateStandalone() {
        generating = true;
        addLog('📦 Génération du projet standalone...');
        try {
            const tenantId = project.current?.tenant_id;
            const res = await post(`/api/admin/tenants/${tenantId}/generate`);
            addLog(`✅ Projet généré : ${res.files_count || '?'} fichiers`);
            addLog(`📁 Répertoire : ${res.output_dir || 'var/standalone/'}`);
            deployStep = 'ready';
        } catch (e) { addLog('❌ Erreur : ' + (e.message || 'échec')); }
        generating = false;
    }

    async function deploy() {
        if (!deployConfig.domain) { addLog('❌ Domaine requis.'); return; }
        deploying = true;
        deployStep = 'deploying';
        addLog('🚀 Déploiement en cours...');
        addLog(`📡 Cible : ${deployConfig.domain}`);

        try {
            const tenantId = project.current?.tenant_id;
            const res = await post(`/api/admin/tenants/${tenantId}/deploy`, {
                ...deployConfig,
                version_id: selectedVersion || versions[0]?.id,
            });

            if (res.log) {
                res.log.forEach(l => addLog(l));
            }

            if (res.status === 'live') {
                deployStatus = 'live';
                addLog('🎉 Déploiement réussi !');
                addLog(`🌐 URL : https://${deployConfig.domain}`);
                deployStep = 'done';
            } else {
                deployStatus = 'failed';
                addLog('❌ ' + (res.error || 'Déploiement échoué'));
            }
        } catch (e) { addLog('❌ Erreur : ' + e.message); deployStatus = 'failed'; }
        deploying = false;
    }

    function addLog(msg) {
        deployLog = [...deployLog, { time: new Date().toLocaleTimeString(), message: msg }];
    }

    $effect(() => { if (project.current) { deployConfig.domain = project.current.dns_address || ''; loadVersions(); } });
</script>

<header class="h-14 border-b border-[var(--color-border)] flex items-center justify-between px-6">
    <h2 class="text-lg font-semibold">🚀 Déploiement</h2>
    <div class="flex items-center gap-2">
        {#if deployStatus === 'live'}
            <span class="text-xs px-2 py-1 rounded-full bg-[var(--color-success)]/15 text-[var(--color-success)] font-medium">● En ligne</span>
        {/if}
    </div>
</header>

<div class="p-6 flex-1 overflow-auto">
    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Steps -->
        <div class="flex items-center gap-2 justify-center">
            {#each ['Configurer', 'Générer', 'Déployer', 'En ligne'] as step, i}
                {@const stepIds = ['config', 'ready', 'deploying', 'done']}
                {@const isActive = stepIds.indexOf(deployStep) >= i}
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {isActive ? 'bg-[var(--color-primary)] text-white' : 'bg-[var(--color-border)] text-[var(--color-muted)]'}">
                        {i + 1}
                    </div>
                    <span class="text-xs {isActive ? 'text-[var(--color-foreground)]' : 'text-[var(--color-muted)]'}">{step}</span>
                    {#if i < 3}<div class="w-12 h-0.5 {isActive ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-border)]'}"></div>{/if}
                </div>
            {/each}
        </div>

        <!-- Config -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold flex items-center gap-2">📡 Configuration du serveur</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Domaine cible</label>
                    <Input bind:value={deployConfig.domain} placeholder="www.monsite.com" />
                </div>
                <div>
                    <label class="text-sm text-[var(--color-muted)] mb-1 block">Environnement</label>
                    <div class="flex gap-2">
                        {#each ['staging', 'production'] as target}
                            <button class="flex-1 py-2 rounded-[var(--radius)] border text-sm cursor-pointer
                                {deployConfig.target === target ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-[var(--color-border)] text-[var(--color-muted)]'}"
                                onclick={() => deployConfig.target = target}>
                                {target === 'staging' ? '🧪 Staging' : '🌐 Production'}
                            </button>
                        {/each}
                    </div>
                </div>
            </div>

            <h4 class="text-sm font-medium pt-2">Plesk</h4>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Plesk Host</label><Input bind:value={deployConfig.plesk_host} placeholder="https://plesk:8443" class="h-9 text-sm" /></div>
                <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Login</label><Input bind:value={deployConfig.plesk_login} placeholder="admin" class="h-9 text-sm" /></div>
                <div><label class="text-xs text-[var(--color-muted)] mb-1 block">Mot de passe</label><Input type="password" bind:value={deployConfig.plesk_password} class="h-9 text-sm" /></div>
            </div>

            <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={deployConfig.ssl} /> SSL (Let's Encrypt)</label>
                <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" bind:checked={deployConfig.create_database} /> Créer la DB</label>
            </div>
        </Card>

        <!-- Actions -->
        <Card class="p-6 space-y-4">
            <h3 class="font-semibold flex items-center gap-2">⚡ Actions</h3>
            <div class="grid grid-cols-3 gap-3">
                <Button variant="secondary" onclick={createSnapshot} class="flex-col h-auto py-4">
                    <span class="text-xl">📸</span>
                    <span class="text-xs mt-1">Créer un snapshot</span>
                </Button>
                <Button variant="secondary" onclick={generateStandalone} disabled={generating} class="flex-col h-auto py-4">
                    <span class="text-xl">{generating ? '⏳' : '📦'}</span>
                    <span class="text-xs mt-1">{generating ? 'Génération...' : 'Générer le projet'}</span>
                </Button>
                <Button onclick={deploy} disabled={deploying || deployStep === 'config'} class="flex-col h-auto py-4">
                    <span class="text-xl">{deploying ? '⏳' : '🚀'}</span>
                    <span class="text-xs mt-1">{deploying ? 'Déploiement...' : 'Déployer'}</span>
                </Button>
            </div>

            {#if versions.length > 0}
                <div>
                    <label class="text-xs text-[var(--color-muted)] mb-1 block">Version à déployer</label>
                    <select class="h-9 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-card)] px-2 text-sm" bind:value={selectedVersion}>
                        {#each versions as v}
                            <option value={v.id}>{v.version_tag} — {v.description || 'No description'} ({v.created_at})</option>
                        {/each}
                    </select>
                </div>
            {/if}
        </Card>

        <!-- Deploy log -->
        {#if deployLog.length > 0}
            <Card class="overflow-hidden">
                <div class="px-4 py-2 border-b border-[var(--color-border)] flex items-center justify-between">
                    <span class="text-xs font-semibold text-[var(--color-muted)]">Journal de déploiement</span>
                    <button class="text-xs text-[var(--color-muted)] cursor-pointer hover:text-[var(--color-foreground)]" onclick={() => deployLog = []}>Vider</button>
                </div>
                <div class="p-3 max-h-60 overflow-y-auto font-mono text-xs space-y-0.5 bg-[var(--color-background)]">
                    {#each deployLog as entry}
                        <div class="flex gap-2">
                            <span class="text-[var(--color-muted-foreground)] flex-shrink-0">{entry.time}</span>
                            <span class="{entry.message.startsWith('❌') ? 'text-[var(--color-destructive)]' : entry.message.startsWith('✅') || entry.message.startsWith('🎉') ? 'text-[var(--color-success)]' : 'text-[var(--color-foreground)]'}">{entry.message}</span>
                        </div>
                    {/each}
                </div>
            </Card>
        {/if}

        <!-- Success -->
        {#if deployStep === 'done' && deployStatus === 'live'}
            <Card class="p-6 text-center border-[var(--color-success)]/30">
                <span class="text-5xl block mb-3">🎉</span>
                <h3 class="text-lg font-bold text-[var(--color-success)]">Déploiement réussi !</h3>
                <p class="text-sm text-[var(--color-muted)] mt-2">Votre site est en ligne à :</p>
                <a href="https://{deployConfig.domain}" target="_blank" class="text-[var(--color-primary)] font-mono text-sm mt-1 block hover:underline">
                    https://{deployConfig.domain}
                </a>
            </Card>
        {/if}
    </div>
</div>
