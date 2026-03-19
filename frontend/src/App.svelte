<script>
    import Router from 'svelte-spa-router';
    import Login from './routes/Login.svelte';
    import Dashboard from './routes/Dashboard.svelte';
    import SchemaEditor from './routes/schema/SchemaEditor.svelte';

    const routes = {
        '/': Login,
        '/login': Login,
        '/dashboard': Dashboard,
        '/database': SchemaEditor,
    };
</script>

<Router {routes} />
