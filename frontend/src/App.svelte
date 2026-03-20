<script>
    import Router from 'svelte-spa-router';
    import Login from './routes/Login.svelte';
    import Dashboard from './routes/Dashboard.svelte';
    import SchemaEditor from './routes/schema/SchemaEditor.svelte';
    import PluginStore from './routes/plugins/PluginStore.svelte';
    import TenantDashboard from './routes/dashboard/TenantDashboard.svelte';
    import FormEditor from './routes/forms/FormEditor.svelte';
    import PageEditor from './routes/pages/PageEditor.svelte';

    const routes = {
        '/': Login,
        '/login': Login,
        '/dashboard': Dashboard,
        '/database': SchemaEditor,
        '/plugins': PluginStore,
        '/forms': FormEditor,
        '/pages': PageEditor,
        '/t/:slug': TenantDashboard,
        '/t/:slug/*': TenantDashboard,
    };
</script>

<Router {routes} />
