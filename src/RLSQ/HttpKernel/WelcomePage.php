<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel;

class WelcomePage
{
    public static function render(array $routes = [], array $config = []): string
    {
        $phpVersion = PHP_VERSION;
        $rlsqVersion = '0.1.0';
        $routeCount = count($routes);
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'CLI';

        $routeRows = '';
        foreach ($routes as $name => $info) {
            $methods = is_array($info['methods'] ?? null) ? implode(', ', $info['methods']) : 'ANY';
            $path = htmlspecialchars($info['path'] ?? '/', ENT_QUOTES, 'UTF-8');
            $controller = htmlspecialchars($info['controller'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $routeRows .= "<tr><td class=\"route-name\">{$name}</td><td><span class=\"method-badge\">{$methods}</span></td><td class=\"route-path\">{$path}</td><td class=\"route-controller\">{$controller}</td></tr>";
        }

        $css = self::renderCSS();
        $js = self::renderJS();
        $homeTab = self::renderHomeTab($phpVersion, $rlsqVersion, $routeCount, $serverSoftware, $routeRows);
        $docsTab = self::renderDocsTab();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>RLSQ-FRAM — Bienvenue</title>
            <style>{$css}</style>
        </head>
        <body>
            <div class="hero">
                <h1><span class="accent">RLSQ</span><span class="sub">-FRAM</span></h1>
                <div class="tagline">Framework PHP from scratch — Inspir&eacute; de Symfony</div>
                <div class="version-badge"><span class="check-icon">&#10003;</span> Votre projet est pr&ecirc;t — v{$rlsqVersion}</div>
            </div>

            <div class="tab-bar">
                <div class="container" style="padding:0 20px;">
                    <nav class="tabs">
                        <button class="tab active" onclick="switchMainTab('home', this)">Accueil</button>
                        <button class="tab" onclick="switchMainTab('docs', this)">Documentation</button>
                    </nav>
                </div>
            </div>

            <div class="container">
                <div id="tab-home" class="main-tab active">{$homeTab}</div>
                <div id="tab-docs" class="main-tab">{$docsTab}</div>
            </div>

            <script>{$js}</script>
        </body>
        </html>
        HTML;
    }

    private static function renderHomeTab(string $phpVersion, string $rlsqVersion, int $routeCount, string $serverSoftware, string $routeRows): string
    {
        return <<<HTML
        <div class="grid">
            <div class="card">
                <h3>&#9881;&#65039; Environnement</h3>
                <div class="info-row"><span class="label">PHP</span><span class="value">{$phpVersion}</span></div>
                <div class="info-row"><span class="label">SAPI</span><span class="value">{$serverSoftware}</span></div>
                <div class="info-row"><span class="label">Framework</span><span class="value">RLSQ-FRAM {$rlsqVersion}</span></div>
                <div class="info-row"><span class="label">Environnement</span><span class="value green">dev</span></div>
            </div>
            <div class="card">
                <h3>&#128739;&#65039; Routing</h3>
                <div class="info-row"><span class="label">Routes</span><span class="value">{$routeCount}</span></div>
                <div class="info-row"><span class="label">Matcher</span><span class="value green">UrlMatcher</span></div>
                <div class="info-row"><span class="label">Attributs #[Route]</span><span class="value green">Support&eacute;</span></div>
                <div class="info-row"><span class="label">YAML config</span><span class="value green">Support&eacute;</span></div>
            </div>
            <div class="card">
                <h3>&#128274; S&eacute;curit&eacute;</h3>
                <div class="info-row"><span class="label">Hasher</span><span class="value">Argon2id</span></div>
                <div class="info-row"><span class="label">Firewall</span><span class="value green">Actif</span></div>
                <div class="info-row"><span class="label">Voters</span><span class="value green">RoleVoter</span></div>
                <div class="info-row"><span class="label">Authenticator</span><span class="value green">FormLogin</span></div>
            </div>
        </div>

        <h2 class="section-title">Composants du framework</h2>
        <div class="components-grid">
            <div class="component-tag"><span class="icon">&#128232;</span>HttpFoundation</div>
            <div class="component-tag"><span class="icon">&#9889;</span>EventDispatcher</div>
            <div class="component-tag"><span class="icon">&#128739;&#65039;</span>Routing</div>
            <div class="component-tag"><span class="icon">&#129504;</span>HttpKernel</div>
            <div class="component-tag"><span class="icon">&#128230;</span>DI Container</div>
            <div class="component-tag"><span class="icon">&#9881;&#65039;</span>Config</div>
            <div class="component-tag"><span class="icon">&#127918;</span>Controller</div>
            <div class="component-tag"><span class="icon">&#127912;</span>Templating</div>
            <div class="component-tag"><span class="icon">&#128187;</span>Console</div>
            <div class="component-tag"><span class="icon">&#128452;&#65039;</span>Database/ORM</div>
            <div class="component-tag"><span class="icon">&#128274;</span>Security</div>
            <div class="component-tag"><span class="icon">&#128221;</span>Form</div>
            <div class="component-tag"><span class="icon">&#128196;</span>Dotenv</div>
            <div class="component-tag"><span class="icon">&#9993;</span>Mailer</div>
            <div class="component-tag"><span class="icon">&#128270;</span>Profiler</div>
            <div class="component-tag"><span class="icon">&#128214;</span>OpenAPI</div>
            <div class="component-tag"><span class="icon">&#9878;</span>GraphQL</div>
        </div>

        <h2 class="section-title" style="margin-top:40px;">Routes enregistr&eacute;es</h2>
        <table class="routes-table">
            <thead><tr><th>Nom</th><th>M&eacute;thode</th><th>Path</th><th>Contr&ocirc;leur</th></tr></thead>
            <tbody>{$routeRows}</tbody>
        </table>

        <div class="getting-started">
            <h2 class="section-title" style="border:none;margin:0 0 12px 0;">Prochaines &eacute;tapes</h2>
            <p style="color:#888;margin-bottom:16px;">Cr&eacute;ez votre premier contr&ocirc;leur dans <code class="inline-code">src/App/Controller/</code></p>
            <pre class="code-block"><span class="c">// src/App/Controller/HomeController.php</span>
        <span class="k">namespace</span> App\Controller;

        <span class="k">use</span> RLSQ\Controller\AbstractController;
        <span class="k">use</span> RLSQ\Controller\Attribute\Route;
        <span class="k">use</span> RLSQ\HttpFoundation\Response;

        <span class="k">class</span> HomeController <span class="k">extends</span> AbstractController
        {
            <span class="k">#[Route(<span class="s">'/'</span>, name: <span class="s">'home'</span>)]</span>
            <span class="k">public function</span> index(): Response
            {
                <span class="k">return</span> <span class="k">\$this</span>->json([<span class="s">'message'</span> => <span class="s">'Hello RLSQ-FRAM!'</span>]);
            }
        }</pre>
        </div>
        HTML;
    }

    private static function renderDocsTab(): string
    {
        $sections = [
            ['id' => 'doc-httpfoundation', 'title' => 'HttpFoundation', 'icon' => '&#128232;'],
            ['id' => 'doc-eventdispatcher', 'title' => 'EventDispatcher', 'icon' => '&#9889;'],
            ['id' => 'doc-routing', 'title' => 'Routing', 'icon' => '&#128739;&#65039;'],
            ['id' => 'doc-httpkernel', 'title' => 'HttpKernel', 'icon' => '&#129504;'],
            ['id' => 'doc-container', 'title' => 'DI Container', 'icon' => '&#128230;'],
            ['id' => 'doc-config', 'title' => 'Configuration', 'icon' => '&#9881;&#65039;'],
            ['id' => 'doc-controller', 'title' => 'Controller', 'icon' => '&#127918;'],
            ['id' => 'doc-templating', 'title' => 'Templating', 'icon' => '&#127912;'],
            ['id' => 'doc-console', 'title' => 'Console', 'icon' => '&#128187;'],
            ['id' => 'doc-database', 'title' => 'Database / ORM', 'icon' => '&#128452;&#65039;'],
            ['id' => 'doc-security', 'title' => 'Security', 'icon' => '&#128274;'],
            ['id' => 'doc-form', 'title' => 'Form', 'icon' => '&#128221;'],
            ['id' => 'doc-dotenv', 'title' => 'Dotenv', 'icon' => '&#128196;'],
            ['id' => 'doc-mailer', 'title' => 'Mailer', 'icon' => '&#9993;'],
            ['id' => 'doc-profiler', 'title' => 'Profiler', 'icon' => '&#128270;'],
            ['id' => 'doc-openapi', 'title' => 'OpenAPI', 'icon' => '&#128214;'],
            ['id' => 'doc-graphql', 'title' => 'GraphQL', 'icon' => '&#9878;'],
        ];

        $nav = '';
        foreach ($sections as $i => $s) {
            $active = $i === 0 ? ' active' : '';
            $nav .= "<button class=\"doc-nav-btn{$active}\" onclick=\"switchDocSection('{$s['id']}', this)\">{$s['icon']} {$s['title']}</button>";
        }

        $panels = self::docHttpFoundation()
            . self::docEventDispatcher()
            . self::docRouting()
            . self::docHttpKernel()
            . self::docContainer()
            . self::docConfig()
            . self::docController()
            . self::docTemplating()
            . self::docConsole()
            . self::docDatabase()
            . self::docSecurity()
            . self::docForm()
            . self::docDotenv()
            . self::docMailer()
            . self::docProfiler()
            . self::docOpenApi()
            . self::docGraphQL();

        return <<<HTML
        <div class="docs-layout">
            <aside class="docs-sidebar">
                <div class="docs-sidebar-title">Composants</div>
                <nav class="docs-nav">{$nav}</nav>
            </aside>
            <main class="docs-content">{$panels}</main>
        </div>
        HTML;
    }

    // ========== DOCUMENTATION PANELS ==========

    private static function docHttpFoundation(): string
    {
        return self::docPanel('doc-httpfoundation', 'HttpFoundation', 'Abstraction des requ&ecirc;tes et r&eacute;ponses HTTP. Encapsule les superglobales PHP en objets propres.', <<<'CLASSES'
<b>Request</b> — Encapsule $_GET, $_POST, $_SERVER, $_COOKIE, $_FILES
<b>Response</b> — Contenu, status code, headers, send()
<b>JsonResponse</b> — Réponse JSON automatique
<b>RedirectResponse</b> — Redirection 301/302
<b>ParameterBag</b> — Collection clé/valeur générique
<b>HeaderBag</b> — Collection de headers (case-insensitive)
<b>ServerBag</b> — Extraction des headers depuis $_SERVER
<b>FileBag / UploadedFile</b> — Gestion des fichiers uploadés
<b>Cookie</b> — Objet valeur pour les cookies
<b>Session / SessionInterface</b> — Gestion de session avec flash messages
CLASSES, <<<'CODE'
<span class="c">// Créer une Request depuis les superglobales</span>
$request = Request::createFromGlobals();

<span class="c">// Ou manuellement (utile pour les tests)</span>
$request = Request::create(<span class="s">'/article/42'</span>, <span class="s">'GET'</span>);
$request = Request::create(<span class="s">'/login'</span>, <span class="s">'POST'</span>, [<span class="s">'username'</span> => <span class="s">'admin'</span>]);

<span class="c">// Accéder aux données</span>
$request->query->get(<span class="s">'page'</span>, <span class="s">'1'</span>);       <span class="c">// $_GET['page']</span>
$request->request->get(<span class="s">'username'</span>);       <span class="c">// $_POST['username']</span>
$request->getMethod();                      <span class="c">// GET, POST...</span>
$request->getPathInfo();                    <span class="c">// /article/42</span>
$request->isMethod(<span class="s">'POST'</span>);                <span class="c">// true/false</span>
$request->isXmlHttpRequest();               <span class="c">// AJAX ?</span>
$request->getClientIp();                    <span class="c">// IP du client</span>

<span class="c">// Réponses</span>
$response = <span class="k">new</span> Response(<span class="s">'Hello'</span>, <span class="n">200</span>, [<span class="s">'Content-Type'</span> => <span class="s">'text/html'</span>]);
$response = <span class="k">new</span> JsonResponse([<span class="s">'status'</span> => <span class="s">'ok'</span>]);
$response = <span class="k">new</span> RedirectResponse(<span class="s">'/login'</span>);
$response->send();

<span class="c">// Session</span>
$session = <span class="k">new</span> Session();
$session->set(<span class="s">'user_id'</span>, <span class="n">42</span>);
$session->setFlash(<span class="s">'success'</span>, <span class="s">'Article créé !'</span>);
$flashes = $session->getFlash(<span class="s">'success'</span>); <span class="c">// Lus une fois puis supprimés</span>
CODE, true);
    }

    private static function docEventDispatcher(): string
    {
        return self::docPanel('doc-eventdispatcher', 'EventDispatcher', 'Pattern Observer/Mediator. Permet de d&eacute;coupler les composants via des &eacute;v&eacute;nements.', <<<'CLASSES'
<b>EventDispatcherInterface / EventDispatcher</b> — dispatch(), addListener(), addSubscriber()
<b>Event</b> — Classe de base, stopPropagation()
<b>StoppableEventInterface</b> — Interface PSR-14
<b>EventSubscriberInterface</b> — Déclare ses événements via getSubscribedEvents()
CLASSES, <<<'CODE'
$dispatcher = <span class="k">new</span> EventDispatcher();

<span class="c">// Listener simple</span>
$dispatcher->addListener(<span class="s">'user.created'</span>, <span class="k">function</span> (Event $event) {
    <span class="c">// Envoyer un email de bienvenue...</span>
}, priority: <span class="n">10</span>); <span class="c">// Plus haute priorité = exécuté en premier</span>

<span class="c">// Subscriber (regroupe plusieurs listeners)</span>
<span class="k">class</span> MailSubscriber <span class="k">implements</span> EventSubscriberInterface
{
    <span class="k">public static function</span> getSubscribedEvents(): <span class="k">array</span>
    {
        <span class="k">return</span> [
            <span class="s">'user.created'</span>  => <span class="s">'onUserCreated'</span>,
            <span class="s">'order.placed'</span> => [<span class="s">'onOrderPlaced'</span>, <span class="n">-10</span>], <span class="c">// basse priorité</span>
        ];
    }

    <span class="k">public function</span> onUserCreated(Event $event): <span class="k">void</span> { <span class="c">/* ... */</span> }
    <span class="k">public function</span> onOrderPlaced(Event $event): <span class="k">void</span> { <span class="c">/* ... */</span> }
}

$dispatcher->addSubscriber(<span class="k">new</span> MailSubscriber());

<span class="c">// Dispatcher un événement</span>
$event = <span class="k">new</span> Event();
$dispatcher->dispatch($event, <span class="s">'user.created'</span>);

<span class="c">// Arrêter la propagation</span>
$event->stopPropagation(); <span class="c">// Les listeners suivants ne seront pas appelés</span>
CODE);
    }

    private static function docRouting(): string
    {
        return self::docPanel('doc-routing', 'Routing', 'Fait correspondre une URL et une m&eacute;thode HTTP &agrave; un contr&ocirc;leur.', <<<'CLASSES'
<b>Route</b> — Path avec paramètres {id}, méthodes HTTP, requirements (regex)
<b>RouteCollection</b> — Collection nommée, préfixe, fusion
<b>UrlMatcher</b> — Matche un path → [_controller, _route, params]
<b>UrlGenerator</b> — Génère une URL depuis un nom de route
<b>Router</b> — Façade combinant matcher + générateur
<b>RouteNotFoundException / MethodNotAllowedException</b> — Exceptions
CLASSES, <<<'CODE'
<span class="c">// Définir des routes</span>
$routes = <span class="k">new</span> RouteCollection();
$routes->add(<span class="s">'home'</span>, <span class="k">new</span> Route(<span class="s">'/'</span>, [<span class="s">'_controller'</span> => <span class="s">'HomeController::index'</span>]));
$routes->add(<span class="s">'article'</span>, <span class="k">new</span> Route(
    <span class="s">'/article/{id}'</span>,
    [<span class="s">'_controller'</span> => <span class="s">'ArticleController::show'</span>],
    [<span class="s">'GET'</span>],                    <span class="c">// Méthodes autorisées</span>
    [<span class="s">'id'</span> => <span class="s">'\d+'</span>]              <span class="c">// Contrainte regex</span>
));

<span class="c">// Matcher une URL</span>
$matcher = <span class="k">new</span> UrlMatcher($routes);
$result = $matcher->match(<span class="s">'/article/42'</span>, <span class="s">'GET'</span>);
<span class="c">// ['_controller' => 'ArticleController::show', '_route' => 'article', 'id' => '42']</span>

<span class="c">// Générer une URL</span>
$generator = <span class="k">new</span> UrlGenerator($routes);
$url = $generator->generate(<span class="s">'article'</span>, [<span class="s">'id'</span> => <span class="n">42</span>]); <span class="c">// /article/42</span>
$url = $generator->generate(<span class="s">'article'</span>, [<span class="s">'id'</span> => <span class="n">42</span>, <span class="s">'page'</span> => <span class="n">2</span>]); <span class="c">// /article/42?page=2</span>

<span class="c">// Préfixer un groupe de routes</span>
$api = <span class="k">new</span> RouteCollection();
$api->add(<span class="s">'users'</span>, <span class="k">new</span> Route(<span class="s">'/users'</span>, [...]));
$api->addPrefix(<span class="s">'/api/v1'</span>); <span class="c">// → /api/v1/users</span>
$routes->addCollection($api);
CODE);
    }

    private static function docHttpKernel(): string
    {
        return self::docPanel('doc-httpkernel', 'HttpKernel', 'Le c&oelig;ur du framework. Orchestre le cycle complet : Request &rarr; Routing &rarr; Controller &rarr; Response.', <<<'CLASSES'
<b>HttpKernel</b> — handle(Request): Response, terminate()
<b>KernelEvents</b> — REQUEST, CONTROLLER, VIEW, RESPONSE, EXCEPTION, TERMINATE
<b>ControllerResolver</b> — Résout _controller en callable (Class::method, Closure, __invoke)
<b>ArgumentResolver</b> — Injecte Request + paramètres de route avec cast auto (int, float)
<b>RouterListener</b> — Écoute kernel.request, fait le matching
<b>ExceptionListener</b> — Convertit les exceptions en Response (404, 405, 500)
CLASSES, <<<'CODE'
<span class="c">// Cycle de vie complet</span>
<span class="c">// 1. kernel.request    → RouterListener fait le matching</span>
<span class="c">// 2. ControllerResolver → résout le callable</span>
<span class="c">// 3. kernel.controller → listeners peuvent modifier le controller</span>
<span class="c">// 4. ArgumentResolver  → prépare les arguments</span>
<span class="c">// 5. Appel du contrôleur → retourne Response</span>
<span class="c">// 6. kernel.view       → si pas Response, convertir le retour</span>
<span class="c">// 7. kernel.response   → modification finale</span>
<span class="c">// 8. kernel.exception  → si erreur, convertir en Response</span>

$kernel = <span class="k">new</span> HttpKernel($dispatcher, $controllerResolver, $argumentResolver);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response); <span class="c">// Post-traitement</span>

<span class="c">// Court-circuiter avec un listener</span>
$dispatcher->addListener(KernelEvents::REQUEST, <span class="k">function</span> (RequestEvent $e) {
    <span class="k">if</span> ($e->getRequest()->getPathInfo() === <span class="s">'/maintenance'</span>) {
        $e->setResponse(<span class="k">new</span> Response(<span class="s">'En maintenance'</span>, <span class="n">503</span>));
    }
}, <span class="n">100</span>);
CODE);
    }

    private static function docContainer(): string
    {
        return self::docPanel('doc-container', 'DI Container', 'G&egrave;re la cr&eacute;ation, l&apos;injection et le partage des services (Dependency Injection).', <<<'CLASSES'
<b>ContainerBuilder</b> — register(), compile(), autowiring, tags
<b>Definition</b> — Classe, arguments, method calls, tags, shared, factory
<b>Reference</b> — Référence vers un autre service
<b>Parameter</b> — Référence vers un paramètre (%param%)
<b>CompilerPassInterface</b> — Modifier les définitions avant compilation
CLASSES, <<<'CODE'
$container = <span class="k">new</span> ContainerBuilder();

<span class="c">// Paramètres</span>
$container->setParameter(<span class="s">'db.host'</span>, <span class="s">'localhost'</span>);

<span class="c">// Enregistrer un service avec arguments</span>
$container->register(<span class="s">'mailer'</span>, Mailer::<span class="k">class</span>)
    ->setArguments([<span class="k">new</span> Reference(<span class="s">'logger'</span>), <span class="s">'%mailer.transport%'</span>])
    ->addMethodCall(<span class="s">'setDebug'</span>, [<span class="k">true</span>])
    ->addTag(<span class="s">'kernel.event_listener'</span>);

<span class="c">// Autowiring (résout les dépendances par type-hints)</span>
$container->register(<span class="s">'app.service'</span>, AppService::<span class="k">class</span>)
    ->setAutowired(<span class="k">true</span>);

<span class="c">// Compiler et utiliser</span>
$container->compile();
$mailer = $container->get(<span class="s">'mailer'</span>);

<span class="c">// Compiler pass (collecter les services taggés)</span>
$container->addCompilerPass(<span class="k">new class implements</span> CompilerPassInterface {
    <span class="k">public function</span> process(ContainerBuilder $c): <span class="k">void</span> {
        $tagged = $c->findTaggedServiceIds(<span class="s">'app.handler'</span>);
        <span class="c">// Injecter les handlers taggés dans un service...</span>
    }
});
CODE);
    }

    private static function docConfig(): string
    {
        return self::docPanel('doc-config', 'Configuration', 'Chargement et validation de fichiers de configuration YAML et PHP.', <<<'CLASSES'
<b>YamlParser</b> — Parser YAML maison (maps, listes, scalaires, inline, blocs |/>)
<b>FileLocator</b> — Localise un fichier dans des répertoires
<b>PhpFileLoader / YamlFileLoader / DelegatingLoader</b> — Chargement par type
<b>ConfigCache</b> — Cache PHP compilé avec vérification de fraîcheur
<b>TreeBuilder / ArrayNode / ScalarNode</b> — Validation et schéma de config
<b>Processor</b> — Fusion et validation de plusieurs fichiers
CLASSES, <<<'CODE'
<span class="c">// Charger un fichier YAML</span>
$locator = <span class="k">new</span> FileLocator(<span class="s">'/chemin/vers/config'</span>);
$loader = <span class="k">new</span> YamlFileLoader($locator);
$config = $loader->load(<span class="s">'services.yaml'</span>);

<span class="c">// Parser YAML directement</span>
$parser = <span class="k">new</span> YamlParser();
$data = $parser->parse(<span class="s">"
database:
    host: localhost
    port: 3306
allowed_hosts:
    - localhost
    - example.com
"</span>);

<span class="c">// Valider avec TreeBuilder</span>
$tree = <span class="k">new</span> TreeBuilder(<span class="s">'framework'</span>);
$root = $tree->getRootNode();
$root->addChild((<span class="k">new</span> ScalarNode(<span class="s">'secret'</span>))->isRequired());
$root->addChild((<span class="k">new</span> ScalarNode(<span class="s">'debug'</span>))->defaultValue(<span class="k">false</span>));
$root->addChild(
    (<span class="k">new</span> ArrayNode(<span class="s">'database'</span>))
        ->addChild((<span class="k">new</span> ScalarNode(<span class="s">'host'</span>))->defaultValue(<span class="s">'localhost'</span>))
        ->addChild((<span class="k">new</span> ScalarNode(<span class="s">'port'</span>))->defaultValue(<span class="n">3306</span>))
);
$validated = $tree->process($config);

<span class="c">// Cache</span>
$cache = <span class="k">new</span> ConfigCache(<span class="s">'/var/cache'</span>, debug: <span class="k">true</span>);
<span class="k">if</span> (!$cache->isFresh(<span class="s">'routes'</span>, [<span class="s">'config/routes.yaml'</span>])) {
    $cache->write(<span class="s">'routes'</span>, $compiledRoutes);
}
CODE);
    }

    private static function docController(): string
    {
        return self::docPanel('doc-controller', 'Controller', 'Classe de base avec raccourcis, attribut #[Route], et injection automatique de tous les types dans les m&eacute;thodes.', <<<'CLASSES'
<b>AbstractController</b> — json(), render(), redirect(), redirectToRoute(), generateUrl(), addFlash(), getParameter(), createNotFoundException()
<b>#[Route]</b> — Attribut PHP 8 : path, name, methods, requirements, defaults
<b>AttributeRouteLoader</b> — Scanne les #[Route] pour générer les RouteCollection
<b>ValueResolverInterface</b> — Interface pour les résolveurs d'arguments personnalisés
<b>EntityValueResolver</b> — Injecte une entité #[Entity] depuis {id} automatiquement
<b>ServiceValueResolver</b> — Injecte un service du Container par type-hint
<b>#[MapEntity(id: 'field')]</b> — Mappe un paramètre de route à un champ d'entité
<b>HttpException / NotFoundHttpException / AccessDeniedHttpException</b>
CLASSES, <<<'CODE'
<span class="k">use</span> RLSQ\Controller\AbstractController;
<span class="k">use</span> RLSQ\Controller\Attribute\Route;

<span class="k">#[Route(<span class="s">'/api'</span>)]</span> <span class="c">// Préfixe sur toute la classe</span>
<span class="k">class</span> ArticleController <span class="k">extends</span> AbstractController
{
    <span class="k">#[Route(<span class="s">'/articles'</span>, name: <span class="s">'article_list'</span>, methods: [<span class="s">'GET'</span>])]</span>
    <span class="k">public function</span> list(): JsonResponse
    {
        <span class="k">return</span> <span class="k">$this</span>->json([<span class="s">'articles'</span> => [...]]);
    }

    <span class="k">#[Route(<span class="s">'/articles/{id}'</span>, name: <span class="s">'article_show'</span>, requirements: [<span class="s">'id'</span> => <span class="s">'\d+'</span>])]</span>
    <span class="k">public function</span> show(<span class="k">int</span> $id, Request $request): Response
    {
        <span class="c">// $id est automatiquement casté en int</span>
        <span class="c">// $request est injecté automatiquement</span>
        <span class="k">return</span> <span class="k">$this</span>->render(<span class="s">'article/show.html'</span>, [<span class="s">'id'</span> => $id]);
    }

    <span class="k">#[Route(<span class="s">'/articles/{id}'</span>, methods: [<span class="s">'DELETE'</span>])]</span>
    <span class="k">public function</span> delete(<span class="k">int</span> $id): JsonResponse
    {
        <span class="c">// Raccourcis disponibles :</span>
        $url = <span class="k">$this</span>->generateUrl(<span class="s">'article_list'</span>);
        <span class="k">$this</span>->addFlash(<span class="s">'success'</span>, <span class="s">'Supprimé'</span>);
        <span class="k">throw</span> <span class="k">$this</span>->createNotFoundException(<span class="s">'Article introuvable'</span>); <span class="c">// 404</span>
        <span class="k">return</span> <span class="k">$this</span>->redirectToRoute(<span class="s">'article_list'</span>);
    }
}

<span class="c">// Charger les routes depuis les attributs</span>
$loader = <span class="k">new</span> AttributeRouteLoader();
$routes = $loader->loadAll([ArticleController::<span class="k">class</span>, UserController::<span class="k">class</span>]);

<span class="c">// === Injection automatique dans les méthodes ===</span>

<span class="c">// Entité automatique depuis {id}</span>
<span class="k">#[Route(<span class="s">'/article/{id}'</span>)]</span>
<span class="k">public function</span> show(Article $article): Response
{
    <span class="c">// Article chargé automatiquement ! 404 si introuvable.</span>
    <span class="k">return</span> <span class="k">$this</span>->json([<span class="s">'title'</span> => $article->title]);
}

<span class="c">// MapEntity pour un champ personnalisé</span>
<span class="k">#[Route(<span class="s">'/user/{user_id}/posts'</span>)]</span>
<span class="k">public function</span> posts(#[MapEntity(id: <span class="s">'user_id'</span>)] User $user): Response { <span class="c">/* ... */</span> }

<span class="c">// Service injecté par type-hint</span>
<span class="k">#[Route(<span class="s">'/send'</span>)]</span>
<span class="k">public function</span> send(Mailer $mailer, Request $request): Response
{
    $mailer->send(...); <span class="c">// Mailer injecté depuis le Container</span>
}

<span class="c">// Mix de tout : entité + service + request + scalaire</span>
<span class="k">#[Route(<span class="s">'/article/{id}/comment'</span>)]</span>
<span class="k">public function</span> comment(Article $article, Mailer $mailer, Request $req, <span class="k">int</span> $page = <span class="n">1</span>): Response
{
    <span class="c">// Tout est résolu automatiquement !</span>
}
CODE);
    }

    private static function docTemplating(): string
    {
        return self::docPanel('doc-templating', 'Templating', 'Moteur de templates type Twig avec lexer, parser et compilateur. Auto-&eacute;chappement HTML.', <<<'CLASSES'
<b>Engine</b> — render(name, params), cache disque optionnel
<b>Lexer</b> → <b>Parser</b> → <b>Compiler</b> — Pipeline de compilation
<b>FilesystemLoader</b> — Charge les templates depuis le disque
<b>Nodes</b> — TextNode, PrintNode, IfNode, ForNode, BlockNode, ExtendsNode, IncludeNode
CLASSES, <<<'CODE'
<span class="c">// Initialisation</span>
$engine = <span class="k">new</span> Engine(<span class="k">new</span> FilesystemLoader(<span class="s">'templates/'</span>));

<span class="c">// Rendu</span>
$html = $engine->render(<span class="s">'page.html'</span>, [<span class="s">'title'</span> => <span class="s">'Accueil'</span>, <span class="s">'user'</span> => $user]);

<span class="c">// === Syntaxe des templates ===</span>

<span class="c">// Variables (auto-échappées) :</span>
{{ title }}                        <span class="c">→ Échappé HTML</span>
{{ content|raw }}                  <span class="c">→ Pas d'échappement</span>
{{ user.name }}                    <span class="c">→ Accès par point (array ou objet)</span>
{{ name|default(<span class="s">'Anonyme'</span>) }}     <span class="c">→ Valeur par défaut</span>

<span class="c">// Filtres : upper, lower, capitalize, title, trim, length,</span>
<span class="c">//   escape, raw, nl2br, reverse, json, abs, keys, first, last, join</span>

<span class="c">// Conditions :</span>
{%raw%}{% if admin %}Admin{% elseif user %}User{% else %}Guest{% endif %}{%endraw%}

<span class="c">// Boucles :</span>
{%raw%}{% for item in items %}&lt;li&gt;{{ item }}&lt;/li&gt;{% endfor %}{%endraw%}
{%raw%}{% for key, val in data %}{{ key }}={{ val }}{% endfor %}{%endraw%}
{%raw%}{% for item in items %}{{ item }}{% else %}Vide{% endfor %}{%endraw%}

<span class="c">// Héritage :</span>
<span class="c">{# base.html #}</span>
&lt;html&gt;&lt;body&gt;{%raw%}{% block content %}Défaut{% endblock %}{%endraw%}&lt;/body&gt;&lt;/html&gt;

<span class="c">{# page.html #}</span>
{%raw%}{% extends "base.html" %}{%endraw%}
{%raw%}{% block content %}&lt;h1&gt;{{ title }}&lt;/h1&gt;{% endblock %}{%endraw%}

<span class="c">// Include :</span>
{%raw%}{% include "partials/header.html" %}{%endraw%}
CODE);
    }

    private static function docConsole(): string
    {
        return self::docPanel('doc-console', 'Console', 'Syst&egrave;me de commandes CLI avec arguments, options et helpers.', <<<'CLASSES'
<b>Application</b> — Point d'entrée CLI, routage des commandes
<b>Command</b> — Classe de base : configure(), execute(), addArgument(), addOption()
<b>ArgvInput / ArrayInput</b> — Parsing des arguments CLI
<b>ConsoleOutput / BufferedOutput</b> — Sortie STDOUT/STDERR ou buffer (tests)
<b>Table</b> — Affichage de données en tableau formaté
<b>ListCommand / HelpCommand</b> — Commandes intégrées
CLASSES, <<<'CODE'
<span class="c">// Créer une commande</span>
<span class="k">class</span> GreetCommand <span class="k">extends</span> Command
{
    <span class="k">protected function</span> configure(): <span class="k">void</span>
    {
        <span class="k">$this</span>->setName(<span class="s">'app:greet'</span>)
            ->setDescription(<span class="s">'Salue quelqu\'un'</span>)
            ->addArgument(<span class="s">'name'</span>, InputArgument::OPTIONAL, <span class="s">'Nom'</span>, <span class="s">'World'</span>)
            ->addOption(<span class="s">'yell'</span>, <span class="s">'y'</span>, InputOption::VALUE_NONE, <span class="s">'Crier'</span>);
    }

    <span class="k">protected function</span> execute(InputInterface $input, OutputInterface $output): <span class="k">int</span>
    {
        $name = $input->getArgument(<span class="s">'name'</span>);
        $msg = <span class="s">"Hello {$name}"</span>;

        <span class="k">if</span> ($input->getOption(<span class="s">'yell'</span>)) {
            $msg = strtoupper($msg);
        }

        $output->writeln($msg);

        <span class="c">// Helper Table</span>
        $table = <span class="k">new</span> Table($output);
        $table->setHeaders([<span class="s">'Nom'</span>, <span class="s">'Age'</span>]);
        $table->addRow([<span class="s">'Alice'</span>, <span class="s">'30'</span>]);
        $table->render();

        <span class="k">return</span> Command::SUCCESS;
    }
}

<span class="c">// bin/console</span>
$app = <span class="k">new</span> Application(<span class="s">'RLSQ-FRAM'</span>, <span class="s">'0.1.0'</span>);
$app->add(<span class="k">new</span> GreetCommand());
$app->run(); <span class="c">// php bin/console app:greet Alice --yell</span>
CODE);
    }

    private static function docDatabase(): string
    {
        return self::docPanel('doc-database', 'Database / ORM', 'Couche DBAL (PDO) + ORM complet avec EntityManager, UnitOfWork et mapping par attributs PHP 8.', <<<'CLASSES'
<b>Connection</b> — Wrapping PDO : execute(), fetchAll(), transactions, transactional()
<b>QueryBuilder</b> — select(), from(), join(), where(), orderBy(), insert(), update(), delete()
<b>EntityManager</b> — persist(), flush(), remove(), find(), getRepository(), createSchema()
<b>UnitOfWork</b> — Identity Map, dirty checking automatique
<b>EntityRepository</b> — find(), findAll(), findBy(), findOneBy(), count()
<b>#[Entity], #[Column], #[Id], #[GeneratedValue]</b> — Mapping par attributs
<b>#[ManyToOne], #[OneToMany]</b> — Relations
CLASSES, <<<'CODE'
<span class="c">// Définir une entité</span>
<span class="k">#[Entity(table: <span class="s">'articles'</span>)]</span>
<span class="k">class</span> Article
{
    <span class="k">#[Id, Column(type: <span class="s">'integer'</span>), GeneratedValue]</span>
    <span class="k">public int</span> $id;

    <span class="k">#[Column(type: <span class="s">'string'</span>, length: <span class="n">255</span>)]</span>
    <span class="k">public string</span> $title;

    <span class="k">#[Column(type: <span class="s">'text'</span>, nullable: <span class="k">true</span>)]</span>
    <span class="k">public</span> ?<span class="k">string</span> $body = <span class="k">null</span>;
}

<span class="c">// Utiliser l'EntityManager</span>
$conn = Connection::create([<span class="s">'driver'</span> => <span class="s">'sqlite'</span>, <span class="s">'path'</span> => <span class="s">'db.sqlite'</span>]);
$em = <span class="k">new</span> EntityManager($conn);
$em->createSchema([Article::<span class="k">class</span>]); <span class="c">// Crée la table</span>

<span class="c">// CRUD</span>
$article = <span class="k">new</span> Article();
$article->title = <span class="s">'Mon article'</span>;
$em->persist($article);
$em->flush(); <span class="c">// INSERT — $article->id est auto-rempli</span>

$article->title = <span class="s">'Titre modifié'</span>;
$em->flush(); <span class="c">// UPDATE automatique (dirty checking)</span>

$em->remove($article);
$em->flush(); <span class="c">// DELETE</span>

<span class="c">// Repository</span>
$repo = $em->getRepository(Article::<span class="k">class</span>);
$article = $repo->find(<span class="n">1</span>);
$all = $repo->findAll();
$results = $repo->findBy([<span class="s">'title'</span> => <span class="s">'Hello'</span>], [<span class="s">'title'</span> => <span class="s">'ASC'</span>], limit: <span class="n">10</span>);

<span class="c">// QueryBuilder</span>
$qb = $em->createQueryBuilder();
$rows = $qb->select(<span class="s">'*'</span>)->from(<span class="s">'articles'</span>)
    ->where(<span class="s">'title LIKE :q'</span>)->setParameter(<span class="s">'q'</span>, <span class="s">'%php%'</span>)
    ->orderBy(<span class="s">'id'</span>, <span class="s">'DESC'</span>)->setMaxResults(<span class="n">20</span>)
    ->fetchAllAssociative();
CODE);
    }

    private static function docSecurity(): string
    {
        return self::docPanel('doc-security', 'Security', 'Authentification, autorisation, attributs de s&eacute;curit&eacute; sur les routes, firewall et hashage Argon2id.', <<<'CLASSES'
<b>#[IsGranted('ROLE_ADMIN')]</b> — Attribut de rôle sur méthode/classe (403 si refusé)
<b>#[RequireAuth]</b> — Attribut exigeant l'authentification (401 ou redirect)
<b>SecurityListener</b> — Listener kernel.controller vérifiant les attributs
<b>UserInterface / InMemoryUser</b> — getUserIdentifier(), getRoles(), getPassword()
<b>UserProviderInterface / InMemoryUserProvider</b> — Chargement des utilisateurs
<b>NativePasswordHasher</b> — hash(), verify() avec PASSWORD_ARGON2ID
<b>FormLoginAuthenticator</b> — Auth par formulaire
<b>TokenStorage / UsernamePasswordToken</b> — Stockage du token authentifié
<b>Firewall</b> — Listener kernel.request, règles d'accès par URL
<b>RoleVoter / AccessDecisionManager / AuthorizationChecker</b> — Système de voters
CLASSES, <<<'CODE'
<span class="c">// === Attributs de sécurité sur les routes ===</span>

<span class="k">#[RequireAuth(redirectTo: <span class="s">'/login'</span>)]</span>  <span class="c">// Redirige si non connecté</span>
<span class="k">#[Route(<span class="s">'/profile'</span>)]</span>
<span class="k">public function</span> profile(): Response { <span class="c">/* ... */</span> }

<span class="k">#[IsGranted(<span class="s">'ROLE_ADMIN'</span>)]</span>            <span class="c">// 403 si pas admin</span>
<span class="k">#[Route(<span class="s">'/admin'</span>)]</span>
<span class="k">public function</span> admin(): Response { <span class="c">/* ... */</span> }

<span class="k">#[IsGranted(<span class="s">'ROLE_EDITOR'</span>, message: <span class="s">'Réservé aux éditeurs.'</span>)]</span>
<span class="k">#[Route(<span class="s">'/editor'</span>)]</span>
<span class="k">public function</span> editor(): Response { <span class="c">/* ... */</span> }

<span class="c">// Sur toute une classe :</span>
<span class="k">#[IsGranted(<span class="s">'ROLE_ADMIN'</span>)]</span>
<span class="k">class</span> AdminController { <span class="c">/* toutes les méthodes protégées */</span> }

<span class="c">// === Hasher de mots de passe (Argon2id par défaut) ===</span>
$hasher = <span class="k">new</span> NativePasswordHasher();
$hash = $hasher->hash(<span class="s">'mon_mot_de_passe'</span>);
$valid = $hasher->verify($hash, <span class="s">'mon_mot_de_passe'</span>); <span class="c">// true</span>

<span class="c">// Utilisateurs en mémoire</span>
$provider = <span class="k">new</span> InMemoryUserProvider([
    <span class="k">new</span> InMemoryUser(<span class="s">'admin'</span>, $hasher->hash(<span class="s">'admin123'</span>), [<span class="s">'ROLE_ADMIN'</span>]),
    <span class="k">new</span> InMemoryUser(<span class="s">'user'</span>,  $hasher->hash(<span class="s">'user123'</span>),  [<span class="s">'ROLE_USER'</span>]),
]);

<span class="c">// Firewall avec authenticateur et règles d'accès</span>
$firewall = <span class="k">new</span> Firewall($tokenStorage, [$formLoginAuthenticator]);
$firewall->addAccessRule(<span class="s">'^/admin'</span>, [<span class="s">'ROLE_ADMIN'</span>]);  <span class="c">// /admin/* → admin only</span>
$firewall->addAccessRule(<span class="s">'^/account'</span>, [<span class="s">'ROLE_USER'</span>]); <span class="c">// /account/* → connecté</span>
$dispatcher->addSubscriber($firewall);

<span class="c">// Vérifier les permissions</span>
$checker = <span class="k">new</span> AuthorizationChecker($tokenStorage, $decisionManager);
<span class="k">if</span> ($checker->isGranted(<span class="s">'ROLE_ADMIN'</span>)) { <span class="c">/* ... */</span> }

<span class="c">// Voter personnalisé</span>
<span class="k">class</span> ArticleVoter <span class="k">implements</span> VoterInterface {
    <span class="k">public function</span> vote(TokenInterface $token, <span class="k">mixed</span> $subject, <span class="k">array</span> $attrs): <span class="k">int</span> {
        <span class="k">if</span> ($subject instanceof Article && in_array(<span class="s">'EDIT'</span>, $attrs)) {
            <span class="k">return</span> $subject->authorId === $token->getUser()->getUserIdentifier()
                ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
        }
        <span class="k">return</span> self::ACCESS_ABSTAIN;
    }
}
CODE);
    }

    private static function docForm(): string
    {
        return self::docPanel('doc-form', 'Form', 'Cr&eacute;ation, validation et rendu de formulaires HTML avec binding vers des objets.', <<<'CLASSES'
<b>FormFactory</b> — create(Type), createBuilder()
<b>AbstractType</b> — buildForm(), configureOptions()
<b>FormBuilder</b> — add(name, type, options), fluent
<b>Form</b> — handleRequest(), isSubmitted(), isValid(), getData(), createView()
<b>FormView / FormFieldView</b> — Rendu HTML (input, textarea, select, button)
<b>Contraintes</b> — NotBlank, Length, Email, Range, Regex
CLASSES, <<<'CODE'
<span class="c">// Définir un type de formulaire</span>
<span class="k">class</span> ContactType <span class="k">extends</span> AbstractType
{
    <span class="k">public function</span> buildForm(FormBuilder $builder, <span class="k">array</span> $options): <span class="k">void</span>
    {
        $builder
            ->add(<span class="s">'name'</span>, <span class="s">'text'</span>, [
                <span class="s">'label'</span> => <span class="s">'Nom'</span>,
                <span class="s">'constraints'</span> => [<span class="k">new</span> NotBlank(), <span class="k">new</span> Length(min: <span class="n">2</span>, max: <span class="n">100</span>)],
            ])
            ->add(<span class="s">'email'</span>, <span class="s">'email'</span>, [
                <span class="s">'constraints'</span> => [<span class="k">new</span> NotBlank(), <span class="k">new</span> Email()],
            ])
            ->add(<span class="s">'message'</span>, <span class="s">'textarea'</span>, [
                <span class="s">'constraints'</span> => [<span class="k">new</span> NotBlank(), <span class="k">new</span> Length(min: <span class="n">10</span>)],
            ])
            ->add(<span class="s">'priority'</span>, <span class="s">'select'</span>, [
                <span class="s">'choices'</span> => [<span class="s">'Basse'</span> => <span class="s">'low'</span>, <span class="s">'Haute'</span> => <span class="s">'high'</span>],
            ])
            ->add(<span class="s">'submit'</span>, <span class="s">'submit'</span>, [<span class="s">'label'</span> => <span class="s">'Envoyer'</span>]);
    }
}

<span class="c">// Dans un contrôleur</span>
$factory = <span class="k">new</span> FormFactory();
$form = $factory->create(ContactType::<span class="k">class</span>);
$form->handleRequest($request);

<span class="k">if</span> ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData(); <span class="c">// ['name' => '...', 'email' => '...']</span>
}

<span class="c">// Binding vers un objet</span>
$article = <span class="k">new</span> Article();
$form = $factory->create(ArticleType::<span class="k">class</span>, $article);
$form->handleRequest($request);
<span class="c">// $article->title est automatiquement mis à jour</span>

<span class="c">// Rendu HTML</span>
$html = $form->createView()->render(); <span class="c">// Formulaire complet</span>
CODE);
    }

    private static function docOpenApi(): string
    {
        return self::docPanel('doc-openapi', 'OpenAPI / Swagger', 'G&eacute;n&eacute;ration automatique de sp&eacute;cification OpenAPI 3.0 depuis les routes et attributs, avec visualisation Swagger UI.', <<<'CLASSES'
<b>#[ApiRoute]</b> — Métadonnées OpenAPI sur une méthode : summary, description, tags, parameters, requestBody, responses
<b>#[ApiSchema]</b> — Définit un schéma OpenAPI sur un DTO/Entity (propriétés auto-extraites)
<b>OpenApiGenerator</b> — generateFromControllers(), generateFromRoutes(), generateSchema()
<b>SwaggerUi</b> — Génère la page HTML Swagger UI
<b>#[IsGranted]</b> — Détecté pour ajouter automatiquement security + 401/403 dans la spec
CLASSES, <<<'CODE'
<span class="c">// Attributs sur un contrôleur</span>
<span class="k">class</span> ArticleController
{
    <span class="k">#[Route(<span class="s">'/articles'</span>, methods: [<span class="s">'GET'</span>])]</span>
    <span class="k">#[ApiRoute(</span>
        summary: <span class="s">'Liste des articles'</span>,
        tags: [<span class="s">'Article'</span>],
        responses: [<span class="n">200</span> => <span class="s">'Liste JSON'</span>]
    <span class="k">)]</span>
    <span class="k">public function</span> list(): JsonResponse { <span class="c">/* ... */</span> }

    <span class="k">#[Route(<span class="s">'/articles/{id}'</span>, requirements: [<span class="s">'id'</span> => <span class="s">'\d+'</span>])]</span>
    <span class="k">#[ApiRoute(summary: <span class="s">'Détail article'</span>, tags: [<span class="s">'Article'</span>])]</span>
    <span class="k">public function</span> show(<span class="k">int</span> $id): JsonResponse { <span class="c">/* ... */</span> }

    <span class="k">#[Route(<span class="s">'/articles'</span>, methods: [<span class="s">'POST'</span>])]</span>
    <span class="k">#[IsGranted(<span class="s">'ROLE_EDITOR'</span>)]</span>     <span class="c">// → security + 401/403 auto dans la spec</span>
    <span class="k">#[ApiRoute(</span>
        summary: <span class="s">'Créer un article'</span>,
        tags: [<span class="s">'Article'</span>],
        requestBody: [<span class="s">'type'</span> => <span class="s">'object'</span>, <span class="s">'properties'</span> => [<span class="s">'title'</span> => [<span class="s">'type'</span> => <span class="s">'string'</span>]]],
        responses: [<span class="n">201</span> => <span class="s">'Créé'</span>]
    <span class="k">)]</span>
    <span class="k">public function</span> create(): JsonResponse { <span class="c">/* ... */</span> }
}

<span class="c">// Schéma DTO auto-extrait</span>
<span class="k">#[ApiSchema(description: <span class="s">'Un article'</span>)]</span>
<span class="k">class</span> ArticleDTO {
    <span class="k">public string</span> $title;   <span class="c">// → type: string, required</span>
    <span class="k">public int</span> $views = <span class="n">0</span>;  <span class="c">// → type: integer</span>
}

<span class="c">// Générer la spec</span>
$gen = <span class="k">new</span> OpenApiGenerator(<span class="s">'Mon API'</span>, <span class="s">'1.0'</span>);
$spec = $gen->generateFromControllers([ArticleController::<span class="k">class</span>]);

<span class="c">// Routes disponibles :</span>
<span class="c">// GET /api/docs      → Swagger UI</span>
<span class="c">// GET /api/openapi.json → Spec JSON</span>
CODE);
    }

    private static function docGraphQL(): string
    {
        return self::docPanel('doc-graphql', 'GraphQL', 'Moteur GraphQL complet avec sch&eacute;ma, types, queries, mutations et interface GraphiQL.', <<<'CLASSES'
<b>Schema</b> — addType(), addQuery(), addMutation(), toSDL()
<b>TypeDefinition</b> — Définition d'un type GraphQL avec champs
<b>FieldDefinition</b> — Champ avec type de retour, arguments et resolver
<b>Executor</b> — execute(query, variables, context) → {data, errors}
<b>GraphiQL</b> — Interface web GraphiQL pour explorer l'API
CLASSES, <<<'CODE'
<span class="c">// Définir le schéma</span>
$schema = <span class="k">new</span> Schema();

<span class="c">// Types</span>
$schema->addType(
    (<span class="k">new</span> TypeDefinition(<span class="s">'Article'</span>, <span class="s">'Un article du blog'</span>))
        ->addField(<span class="s">'id'</span>, <span class="s">'Int!'</span>)
        ->addField(<span class="s">'title'</span>, <span class="s">'String!'</span>)
        ->addField(<span class="s">'body'</span>, <span class="s">'String'</span>)
);

<span class="c">// Query avec arguments</span>
$field = <span class="k">new</span> FieldDefinition(<span class="s">'[Article]'</span>, <span class="k">function</span>($ctx, $args) {
    $limit = $args[<span class="s">'limit'</span>] ?? <span class="n">10</span>;
    <span class="k">return</span> $articleRepo->findBy([], <span class="k">null</span>, $limit);
});
$field->addArg(<span class="s">'limit'</span>, <span class="s">'Int'</span>);
$schema->addQuery(<span class="s">'articles'</span>, $field);

<span class="c">// Query simple</span>
$schema->addQuery(<span class="s">'article'</span>, (<span class="k">new</span> FieldDefinition(
    <span class="s">'Article'</span>,
    <span class="k">fn</span>($ctx, $args) => $articleRepo->find($args[<span class="s">'id'</span>])
))->addArg(<span class="s">'id'</span>, <span class="s">'Int!'</span>));

<span class="c">// Mutation</span>
$schema->addMutation(<span class="s">'createArticle'</span>, (<span class="k">new</span> FieldDefinition(
    <span class="s">'Article'</span>,
    <span class="k">function</span>($ctx, $args) <span class="k">use</span> ($em) {
        $article = <span class="k">new</span> Article();
        $article->title = $args[<span class="s">'title'</span>];
        $em->persist($article);
        $em->flush();
        <span class="k">return</span> $article;
    }
))->addArg(<span class="s">'title'</span>, <span class="s">'String!'</span>));

<span class="c">// Exécuter</span>
$executor = <span class="k">new</span> Executor($schema);
$result = $executor->execute(<span class="s">'{ articles(limit: 5) { id title } }'</span>);
<span class="c">// → ['data' => ['articles' => [['id' => 1, 'title' => '...'], ...]]]</span>

<span class="c">// Requêtes supportées :</span>
<span class="c">//   { field }                     — Query simple</span>
<span class="c">//   { field(arg: value) }         — Avec arguments</span>
<span class="c">//   { field { subfield } }        — Sous-sélections</span>
<span class="c">//   query NamedQuery { ... }      — Query nommée</span>
<span class="c">//   mutation { createX(a: v) { id } } — Mutation</span>

<span class="c">// Routes disponibles :</span>
<span class="c">// POST /graphql   → Endpoint GraphQL</span>
<span class="c">// GET  /graphiql  → Interface GraphiQL</span>
CODE);
    }

    private static function docDotenv(): string
    {
        return self::docPanel('doc-dotenv', 'Dotenv', 'Gestion des variables d&apos;environnement via des fichiers <code class="inline-code">.env</code>. Ordre de chargement : .env &rarr; .env.local &rarr; .env.{APP_ENV} &rarr; .env.{APP_ENV}.local', <<<'CLASSES'
<b>Dotenv</b> — load(), loadFile(), get(), has(), all(), loadIn() (raccourci statique)
CLASSES, <<<'CODE'
<span class="c">// Charger les variables d'environnement</span>
$dotenv = Dotenv::loadIn(__DIR__);
<span class="c">// Charge : .env → .env.local → .env.dev → .env.dev.local</span>

<span class="c">// Accéder aux valeurs</span>
$dotenv->get(<span class="s">'APP_ENV'</span>);                   <span class="c">// 'dev'</span>
$dotenv->get(<span class="s">'DB_HOST'</span>, <span class="s">'localhost'</span>);     <span class="c">// Avec valeur par défaut</span>
$dotenv->has(<span class="s">'APP_SECRET'</span>);                <span class="c">// true/false</span>

<span class="c">// Aussi disponible dans les superglobales</span>
$_ENV[<span class="s">'APP_ENV'</span>];
$_SERVER[<span class="s">'APP_ENV'</span>];
getenv(<span class="s">'APP_ENV'</span>);

<span class="c">// === Syntaxe du fichier .env ===</span>

APP_ENV=dev
APP_DEBUG=<span class="k">true</span>
APP_SECRET=change_me

<span class="c"># Valeurs entre guillemets</span>
DB_URL=<span class="s">"mysql://user:pass@localhost/mydb"</span>
SIMPLE=<span class="s">'no interpolation here'</span>

<span class="c"># Interpolation de variables</span>
DB_HOST=localhost
DB_NAME=myapp
DB_URL=mysql://${DB_HOST}/${DB_NAME}

<span class="c"># Export (compatible shell)</span>
<span class="k">export</span> API_KEY=abc123

<span class="c"># Commentaires inline</span>
PORT=<span class="n">8080</span> <span class="c"># Port du serveur</span>
CODE);
    }

    private static function docMailer(): string
    {
        return self::docPanel('doc-mailer', 'Mailer', 'Syst&egrave;me d&apos;envoi d&apos;emails avec file d&apos;attente (queue), transports multiples et commandes console.', <<<'CLASSES'
<b>Email</b> — Builder fluide : from(), to(), cc(), bcc(), subject(), text(), html(), priority()
<b>Mailer</b> — Façade : send() immédiat, queue() différé, processQueue(limit)
<b>TransportInterface</b> — Interface pour les transports
<b>NullTransport</b> — Ne fait rien (dev/test)
<b>SmtpTransport</b> — Envoi via mail() PHP
<b>LogTransport</b> — Écrit les emails dans var/mail_log/*.eml
<b>QueueInterface</b> — Interface pour les queues
<b>FilesystemQueue</b> — Persistance JSON dans var/mail_queue/, tri par priorité
<b>InMemoryQueue</b> — Queue en mémoire (tests)
CLASSES, <<<'CODE'
<span class="c">// Créer un email</span>
$email = (<span class="k">new</span> Email())
    ->from(<span class="s">'sender@app.com'</span>)
    ->to(<span class="s">'alice@example.com'</span>, <span class="s">'bob@example.com'</span>)
    ->cc(<span class="s">'manager@example.com'</span>)
    ->subject(<span class="s">'Bienvenue !'</span>)
    ->text(<span class="s">'Bienvenue sur notre plateforme.'</span>)
    ->html(<span class="s">'&lt;h1&gt;Bienvenue&lt;/h1&gt;&lt;p&gt;Merci de votre inscription.&lt;/p&gt;'</span>)
    ->priority(<span class="n">1</span>); <span class="c">// 1=urgent, 5=basse</span>

<span class="c">// Configurer le mailer</span>
$transport = <span class="k">new</span> LogTransport(<span class="s">'var/mail_log'</span>);       <span class="c">// Dev</span>
$queue = <span class="k">new</span> FilesystemQueue(<span class="s">'var/mail_queue'</span>);      <span class="c">// Persistant</span>
$mailer = <span class="k">new</span> Mailer($transport, $queue);
$mailer->setDefaultFrom(<span class="s">'noreply@app.com'</span>);

<span class="c">// Envoi immédiat</span>
$mailer->send($email);

<span class="c">// Mise en file d'attente</span>
$mailer->queue($email);

<span class="c">// Traiter la queue (dans un worker/cron)</span>
$sent = $mailer->processQueue(limit: <span class="n">50</span>);

<span class="c">// === Commandes console ===</span>
<span class="c">// Envoyer un email de test</span>
php bin/console mailer:send-test user@example.com
php bin/console mailer:send-test user@example.com --queue

<span class="c">// Voir le statut de la queue</span>
php bin/console mailer:queue:status

<span class="c">// Traiter les emails en queue</span>
php bin/console mailer:queue:process --limit=<span class="n">50</span>

<span class="c">// === Dans un contrôleur ===</span>
<span class="k">class</span> ContactController <span class="k">extends</span> AbstractController
{
    <span class="k">public function</span> send(Request $request, Mailer $mailer): Response
    {
        $email = (<span class="k">new</span> Email())
            ->to($request->request->get(<span class="s">'email'</span>))
            ->subject(<span class="s">'Confirmation'</span>)
            ->html(<span class="k">$this</span>->render(<span class="s">'emails/confirm.html'</span>, [...]));

        $mailer->queue($email); <span class="c">// Non-bloquant</span>

        <span class="k">return</span> <span class="k">$this</span>->redirectToRoute(<span class="s">'contact_success'</span>);
    }
}
CODE);
    }

    private static function docProfiler(): string
    {
        return self::docPanel('doc-profiler', 'Profiler', 'Web Debug Toolbar et panneau de profiling. Collecte les donn&eacute;es de chaque requ&ecirc;te pour le d&eacute;bogage.', <<<'CLASSES'
<b>Profiler</b> — Orchestre les collectors, mesure durée et mémoire
<b>DataCollectorInterface</b> — Interface pour créer un collector custom
<b>RequestCollector</b> — Method, path, headers, status code, IP
<b>RouteCollector</b> — Route name, controller, paramètres
<b>PerformanceCollector</b> — Durée (ms), mémoire peak, PHP version
<b>EventCollector</b> — Événements dispatchés, nombre de listeners
<b>MailerCollector</b> — Emails envoyés, en queue, erreurs
<b>WebDebugToolbar</b> — Génère le HTML de la toolbar
<b>ProfilerListener</b> — Injecte la toolbar dans les réponses HTML
<b>TraceableEventDispatcher</b> — EventDispatcher qui trace les événements
CLASSES, <<<'CODE'
<span class="c">// La toolbar est injectée automatiquement en mode debug.</span>
<span class="c">// Configurer dans public/index.php :</span>

$profiler = <span class="k">new</span> Profiler();

<span class="c">// Ajouter les collectors standard</span>
$profiler->addCollector(<span class="k">new</span> RequestCollector());
$profiler->addCollector(<span class="k">new</span> RouteCollector());
$profiler->addCollector(<span class="k">new</span> PerformanceCollector($profiler));
$profiler->addCollector(<span class="k">new</span> EventCollector($dispatcher));
$profiler->addCollector(<span class="k">new</span> MailerCollector($mailer));

<span class="c">// Le ProfilerListener injecte la toolbar avant &lt;/body&gt;</span>
$dispatcher->addSubscriber(
    <span class="k">new</span> ProfilerListener($profiler, <span class="k">new</span> WebDebugToolbar(), enabled: $debug)
);

<span class="c">// === Créer un collector personnalisé ===</span>
<span class="k">class</span> DatabaseCollector <span class="k">implements</span> DataCollectorInterface
{
    <span class="k">private array</span> $data = [];

    <span class="k">public function</span> collect(Request $req, Response $res, ?\Throwable $e = <span class="k">null</span>): <span class="k">void</span>
    {
        <span class="k">$this</span>->data = [
            <span class="s">'query_count'</span> => <span class="n">42</span>,
            <span class="s">'queries'</span>    => [<span class="c">/* ... */</span>],
        ];
    }

    <span class="k">public function</span> getName(): <span class="k">string</span> { <span class="k">return</span> <span class="s">'database'</span>; }
    <span class="k">public function</span> getData(): <span class="k">array</span>  { <span class="k">return</span> <span class="k">$this</span>->data; }
}

$profiler->addCollector(<span class="k">new</span> DatabaseCollector());

<span class="c">// === Onglets de la toolbar ===</span>
<span class="c">// Request   — Method, path, status, headers req/resp</span>
<span class="c">// Routing   — Route name, controller, paramètres</span>
<span class="c">// Perf      — Durée ms, mémoire, PHP/SAPI</span>
<span class="c">// Events    — Liste des événements dispatchés</span>
<span class="c">// Mailer    — Emails envoyés, en queue, erreurs</span>

<span class="c">// Désactiver la toolbar :</span>
<span class="k">new</span> ProfilerListener($profiler, $toolbar, enabled: <span class="k">false</span>);
<span class="c">// Ou via .env : APP_DEBUG=false</span>
CODE);
    }

    // ========== HELPERS ==========

    private static function docPanel(string $id, string $title, string $description, string $classes, string $code, bool $active = false): string
    {
        $activeClass = $active ? ' active' : '';
        $classesHtml = '';
        foreach (explode("\n", trim($classes)) as $line) {
            $classesHtml .= '<div class="doc-class-item">' . $line . '</div>';
        }

        return <<<HTML
        <section class="doc-panel{$activeClass}" id="{$id}">
            <h2 class="doc-title">{$title}</h2>
            <p class="doc-desc">{$description}</p>

            <div class="doc-section">
                <h3>Classes principales</h3>
                <div class="doc-class-list">{$classesHtml}</div>
            </div>

            <div class="doc-section">
                <h3>Exemples d'utilisation</h3>
                <pre class="code-block">{$code}</pre>
            </div>
        </section>
        HTML;
    }

    private static function renderCSS(): string
    {
        return <<<'CSS'
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; background:#0f0f1a; color:#e0e0e0; min-height:100vh; padding-bottom:50px; }
        .hero { background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%); text-align:center; padding:60px 20px 40px; border-bottom:3px solid #ff3e00; position:relative; overflow:hidden; }
        .hero::before { content:''; position:absolute; top:-50%; left:-50%; width:200%; height:200%; background:radial-gradient(circle,rgba(255,62,0,0.05) 0%,transparent 50%); animation:pulse 8s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
        .hero h1 { font-size:3rem; font-weight:800; position:relative; letter-spacing:-1px; }
        .hero h1 .accent { color:#ff3e00; }
        .hero h1 .sub { color:#6cb2eb; }
        .hero .tagline { font-size:1.1rem; color:#8899aa; margin-top:8px; position:relative; }
        .hero .version-badge { display:inline-block; margin-top:16px; padding:5px 14px; background:rgba(255,62,0,0.15); border:1px solid rgba(255,62,0,0.3); border-radius:20px; font-size:0.82rem; color:#ff3e00; position:relative; }
        .check-icon { color:#28a745; }

        /* Tab bar */
        .tab-bar { background:#16213e; border-bottom:1px solid #2a2a3e; position:sticky; top:0; z-index:100; }
        .tabs { display:flex; gap:0; }
        .tab { background:none; border:none; color:#8899aa; padding:14px 24px; font-size:0.95rem; font-weight:600; cursor:pointer; border-bottom:3px solid transparent; font-family:inherit; transition:all .15s; }
        .tab:hover { color:#fff; background:rgba(255,255,255,0.03); }
        .tab.active { color:#ff3e00; border-bottom-color:#ff3e00; }

        .main-tab { display:none; padding-top:30px; }
        .main-tab.active { display:block; }

        .container { max-width:1200px; margin:0 auto; padding:0 20px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; margin-bottom:40px; }
        .card { background:#1a1a2e; border:1px solid #2a2a3e; border-radius:12px; padding:24px; transition:border-color .2s,transform .2s; }
        .card:hover { border-color:#ff3e00; transform:translateY(-2px); }
        .card h3 { font-size:1rem; color:#ff3e00; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .card .info-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #2a2a3e; font-size:0.9rem; }
        .card .info-row:last-child { border:none; }
        .card .label { color:#888; }
        .card .value { color:#e0e0e0; font-weight:500; }
        .card .value.green { color:#28a745; }
        .section-title { font-size:1.3rem; font-weight:700; color:#fff; margin-bottom:16px; padding-bottom:8px; border-bottom:2px solid #2a2a3e; }
        .routes-table { width:100%; border-collapse:collapse; background:#1a1a2e; border:1px solid #2a2a3e; border-radius:12px; overflow:hidden; font-size:0.9rem; }
        .routes-table th { text-align:left; padding:12px 16px; background:#16213e; color:#8899aa; font-weight:600; font-size:0.8rem; text-transform:uppercase; letter-spacing:.5px; }
        .routes-table td { padding:10px 16px; border-top:1px solid #2a2a3e; }
        .route-name { color:#6cb2eb; font-weight:500; }
        .route-path { color:#e0e0e0; font-family:monospace; }
        .route-controller { color:#888; font-family:monospace; font-size:0.85rem; }
        .method-badge { display:inline-block; padding:2px 8px; background:rgba(108,178,235,0.15); color:#6cb2eb; border-radius:4px; font-size:0.75rem; font-weight:600; font-family:monospace; }
        .components-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:10px; margin-top:16px; }
        .component-tag { background:#16213e; border:1px solid #2a2a3e; border-radius:8px; padding:10px 14px; font-size:0.85rem; text-align:center; transition:border-color .2s; }
        .component-tag:hover { border-color:#ff3e00; }
        .component-tag .icon { font-size:1.2rem; display:block; margin-bottom:4px; }
        .getting-started { background:#1a1a2e; border:1px solid #2a2a3e; border-radius:12px; padding:24px; margin-top:30px; }
        .inline-code { display:inline!important; padding:2px 6px!important; border:none!important; margin:0!important; background:#16213e!important; border-radius:4px!important; font-size:0.85rem!important; color:#6cb2eb!important; }

        /* Code blocks */
        .code-block { display:block; background:#0d0d17; padding:20px; border-radius:8px; font-family:'Fira Code','Cascadia Code','JetBrains Mono',monospace; font-size:0.82rem; color:#ccc; overflow-x:auto; margin:12px 0; border:1px solid #2a2a3e; line-height:1.7; white-space:pre; }
        .code-block .c { color:#5c6370; font-style:italic; }
        .code-block .k { color:#c678dd; }
        .code-block .s { color:#98c379; }
        .code-block .n { color:#d19a66; }

        /* Documentation layout */
        .docs-layout { display:flex; gap:0; min-height:70vh; margin:0 -20px; }
        .docs-sidebar { width:240px; flex-shrink:0; background:#13131f; border-right:1px solid #2a2a3e; padding:0; position:sticky; top:50px; height:calc(100vh - 50px - 36px); overflow-y:auto; }
        .docs-sidebar-title { font-size:0.75rem; font-weight:700; color:#666; text-transform:uppercase; letter-spacing:1px; padding:20px 20px 10px; }
        .docs-nav { display:flex; flex-direction:column; }
        .doc-nav-btn { display:flex; align-items:center; gap:8px; width:100%; background:none; border:none; border-left:3px solid transparent; color:#8899aa; padding:11px 20px; font-size:0.88rem; font-family:inherit; cursor:pointer; text-align:left; transition:all .15s; }
        .doc-nav-btn:hover { color:#fff; background:rgba(255,255,255,0.03); }
        .doc-nav-btn.active { color:#ff3e00; background:rgba(255,62,0,0.06); border-left-color:#ff3e00; }
        .docs-content { flex:1; padding:0 32px 40px; overflow-y:auto; }
        .doc-panel { display:none; }
        .doc-panel.active { display:block; }
        .doc-title { font-size:1.8rem; font-weight:800; color:#fff; margin:24px 0 8px; }
        .doc-desc { color:#8899aa; font-size:1rem; margin-bottom:28px; line-height:1.5; }
        .doc-section { margin-bottom:28px; }
        .doc-section h3 { font-size:0.8rem; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #2a2a3e; }
        .doc-class-list { display:flex; flex-direction:column; gap:0; background:#1a1a2e; border:1px solid #2a2a3e; border-radius:8px; overflow:hidden; }
        .doc-class-item { padding:10px 16px; border-bottom:1px solid #222230; font-size:0.88rem; line-height:1.5; }
        .doc-class-item:last-child { border-bottom:none; }
        .doc-class-item b { color:#ff3e00; font-weight:600; }

        @media (max-width:800px) {
            .docs-layout { flex-direction:column; }
            .docs-sidebar { width:100%; position:static; height:auto; overflow-x:auto; }
            .docs-nav { flex-direction:row; overflow-x:auto; }
            .doc-nav-btn { white-space:nowrap; border-left:none; border-bottom:3px solid transparent; }
            .doc-nav-btn.active { border-bottom-color:#ff3e00; border-left-color:transparent; }
        }
CSS;
    }

    private static function renderJS(): string
    {
        return <<<'JS'
        function switchMainTab(id, btn) {
            document.querySelectorAll('.main-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + id).classList.add('active');
            btn.classList.add('active');
        }
        function switchDocSection(id, btn) {
            document.querySelectorAll('.doc-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.doc-nav-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            btn.classList.add('active');
        }
JS;
    }
}
