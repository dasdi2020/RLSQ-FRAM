<script>
    import Router from 'svelte-spa-router';
    import Login from './routes/Login.svelte';
    import Dashboard from './routes/Dashboard.svelte';
    import SchemaEditor from './routes/schema/SchemaEditor.svelte';
    import PluginStore from './routes/plugins/PluginStore.svelte';
    import TenantDashboard from './routes/dashboard/TenantDashboard.svelte';
    import FormEditor from './routes/forms/FormEditor.svelte';
    import PageEditor from './routes/pages/PageEditor.svelte';
    import EmbedManager from './routes/embed/EmbedManager.svelte';
    import AuditLog from './routes/audit/AuditLog.svelte';
    import Settings from './routes/settings/Settings.svelte';
    import Notifications from './routes/notifications/Notifications.svelte';

    const routes = {
        '/': Login,
        '/login': Login,
        '/dashboard': Dashboard,
        '/database': SchemaEditor,
        '/plugins': PluginStore,
        '/forms': FormEditor,
        '/pages': PageEditor,
        '/embeds': EmbedManager,
        '/audit': AuditLog,
        '/settings': Settings,
        '/notifications': Notifications,
        '/t/:slug': TenantDashboard,
        '/t/:slug/*': TenantDashboard,
    };
</script>

<Router {routes} />
