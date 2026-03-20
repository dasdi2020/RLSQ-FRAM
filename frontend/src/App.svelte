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
    import ErdEditor from './routes/erd/ErdEditor.svelte';
    import Profile from './routes/profile/Profile.svelte';
    import Users from './routes/users/Users.svelte';

    const routes = {
        '/': Login,
        '/login': Login,
        '/dashboard': Dashboard,
        '/database': SchemaEditor,
        '/erd': ErdEditor,
        '/plugins': PluginStore,
        '/forms': FormEditor,
        '/pages': PageEditor,
        '/embeds': EmbedManager,
        '/users': Users,
        '/audit': AuditLog,
        '/settings': Settings,
        '/notifications': Notifications,
        '/profile': Profile,
        '/t/:slug': TenantDashboard,
        '/t/:slug/*': TenantDashboard,
    };
</script>

<Router {routes} />
