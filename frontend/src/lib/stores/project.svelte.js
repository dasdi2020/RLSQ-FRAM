import { get } from '$lib/api/client.js';

let currentProject = $state(null);
let loading = $state(false);

export function getProject() {
    return {
        get current() { return currentProject; },
        get loading() { return loading; },
        get slug() { return currentProject?.slug || ''; },
        get type() { return currentProject?.type || 'website'; },
        get name() { return currentProject?.name || ''; },
        get tenantSlug() {
            // Le tenant slug est nécessaire pour les API /api/t/{tenantSlug}/...
            // On le dérive du projet — le backend lie projet → tenant
            return currentProject?.tenant_slug || currentProject?.slug || '';
        },

        async load(slug) {
            if (currentProject?.slug === slug) return currentProject;
            loading = true;
            try {
                // Charger le projet par slug via l'API
                const res = await get(`/api/projects?slug=${slug}`);
                const projects = res.data || [];
                currentProject = projects.find(p => p.slug === slug) || null;

                // Si pas trouvé par liste, essayer par ID
                if (!currentProject) {
                    // Charger tous et chercher
                    const all = await get('/api/projects');
                    currentProject = (all.data || []).find(p => p.slug === slug) || null;
                }
            } catch (e) { console.error('Failed to load project:', e); }
            loading = false;
            return currentProject;
        },

        clear() { currentProject = null; },
    };
}
