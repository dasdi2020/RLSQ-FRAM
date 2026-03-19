<?php

declare(strict_types=1);

namespace RLSQ\HttpKernel;

/**
 * Page d'accueil affichée quand on démarre un nouveau projet RLSQ-FRAM.
 */
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
            $routeRows .= <<<ROW
            <tr>
                <td class="route-name">{$name}</td>
                <td><span class="method-badge">{$methods}</span></td>
                <td class="route-path">{$path}</td>
                <td class="route-controller">{$controller}</td>
            </tr>
            ROW;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>RLSQ-FRAM — Bienvenue</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
                    background: #0f0f1a;
                    color: #e0e0e0;
                    min-height: 100vh;
                    padding-bottom: 50px;
                }
                .hero {
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
                    text-align: center;
                    padding: 80px 20px 60px;
                    border-bottom: 3px solid #ff3e00;
                    position: relative;
                    overflow: hidden;
                }
                .hero::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(255,62,0,0.05) 0%, transparent 50%);
                    animation: pulse 8s ease-in-out infinite;
                }
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }
                .hero h1 {
                    font-size: 3.5rem;
                    font-weight: 800;
                    position: relative;
                    letter-spacing: -1px;
                }
                .hero h1 .accent { color: #ff3e00; }
                .hero h1 .sub { color: #6cb2eb; }
                .hero .tagline {
                    font-size: 1.2rem;
                    color: #8899aa;
                    margin-top: 12px;
                    position: relative;
                }
                .hero .version-badge {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 6px 16px;
                    background: rgba(255,62,0,0.15);
                    border: 1px solid rgba(255,62,0,0.3);
                    border-radius: 20px;
                    font-size: 0.85rem;
                    color: #ff3e00;
                    position: relative;
                }
                .check-icon { color: #28a745; font-size: 1.2em; }
                .container {
                    max-width: 1100px;
                    margin: 0 auto;
                    padding: 40px 20px;
                }
                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-bottom: 40px;
                }
                .card {
                    background: #1a1a2e;
                    border: 1px solid #2a2a3e;
                    border-radius: 12px;
                    padding: 24px;
                    transition: border-color 0.2s, transform 0.2s;
                }
                .card:hover {
                    border-color: #ff3e00;
                    transform: translateY(-2px);
                }
                .card h3 {
                    font-size: 1rem;
                    color: #ff3e00;
                    margin-bottom: 12px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .card .info-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 6px 0;
                    border-bottom: 1px solid #2a2a3e;
                    font-size: 0.9rem;
                }
                .card .info-row:last-child { border: none; }
                .card .label { color: #888; }
                .card .value { color: #e0e0e0; font-weight: 500; }
                .card .value.green { color: #28a745; }
                .section-title {
                    font-size: 1.3rem;
                    font-weight: 700;
                    color: #fff;
                    margin-bottom: 16px;
                    padding-bottom: 8px;
                    border-bottom: 2px solid #2a2a3e;
                }
                .routes-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #1a1a2e;
                    border: 1px solid #2a2a3e;
                    border-radius: 12px;
                    overflow: hidden;
                    font-size: 0.9rem;
                }
                .routes-table th {
                    text-align: left;
                    padding: 12px 16px;
                    background: #16213e;
                    color: #8899aa;
                    font-weight: 600;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .routes-table td { padding: 10px 16px; border-top: 1px solid #2a2a3e; }
                .route-name { color: #6cb2eb; font-weight: 500; }
                .route-path { color: #e0e0e0; font-family: monospace; }
                .route-controller { color: #888; font-family: monospace; font-size: 0.85rem; }
                .method-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    background: rgba(108,178,235,0.15);
                    color: #6cb2eb;
                    border-radius: 4px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    font-family: monospace;
                }
                .components-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                    gap: 10px;
                    margin-top: 16px;
                }
                .component-tag {
                    background: #16213e;
                    border: 1px solid #2a2a3e;
                    border-radius: 8px;
                    padding: 10px 14px;
                    font-size: 0.85rem;
                    text-align: center;
                    transition: border-color 0.2s;
                }
                .component-tag:hover { border-color: #ff3e00; }
                .component-tag .icon { font-size: 1.2rem; display: block; margin-bottom: 4px; }
                .getting-started {
                    background: #1a1a2e;
                    border: 1px solid #2a2a3e;
                    border-radius: 12px;
                    padding: 24px;
                    margin-top: 30px;
                }
                .getting-started code {
                    display: block;
                    background: #0f0f1a;
                    padding: 16px;
                    border-radius: 8px;
                    font-family: 'Fira Code', 'Cascadia Code', monospace;
                    font-size: 0.85rem;
                    color: #ccc;
                    overflow-x: auto;
                    margin: 12px 0;
                    border: 1px solid #2a2a3e;
                    line-height: 1.6;
                }
                .getting-started code .comment { color: #6a737d; }
                .getting-started code .keyword { color: #ff3e00; }
                .getting-started code .string { color: #28a745; }
            </style>
        </head>
        <body>
            <div class="hero">
                <h1><span class="accent">RLSQ</span><span class="sub">-FRAM</span></h1>
                <div class="tagline">Framework PHP from scratch — Inspiré de Symfony</div>
                <div class="version-badge"><span class="check-icon">✓</span> Votre projet est prêt — v{$rlsqVersion}</div>
            </div>

            <div class="container">
                <!-- Info Cards -->
                <div class="grid">
                    <div class="card">
                        <h3>⚙️ Environnement</h3>
                        <div class="info-row"><span class="label">PHP</span><span class="value">{$phpVersion}</span></div>
                        <div class="info-row"><span class="label">SAPI</span><span class="value">{$serverSoftware}</span></div>
                        <div class="info-row"><span class="label">Framework</span><span class="value">RLSQ-FRAM {$rlsqVersion}</span></div>
                        <div class="info-row"><span class="label">Environnement</span><span class="value green">dev</span></div>
                    </div>
                    <div class="card">
                        <h3>🛣️ Routing</h3>
                        <div class="info-row"><span class="label">Routes définies</span><span class="value">{$routeCount}</span></div>
                        <div class="info-row"><span class="label">Matcher</span><span class="value green">UrlMatcher</span></div>
                        <div class="info-row"><span class="label">Attributs #[Route]</span><span class="value green">Supporté</span></div>
                        <div class="info-row"><span class="label">YAML config</span><span class="value green">Supporté</span></div>
                    </div>
                    <div class="card">
                        <h3>🔒 Sécurité</h3>
                        <div class="info-row"><span class="label">Hasher</span><span class="value">Argon2id</span></div>
                        <div class="info-row"><span class="label">Firewall</span><span class="value green">Actif</span></div>
                        <div class="info-row"><span class="label">Voters</span><span class="value green">RoleVoter</span></div>
                        <div class="info-row"><span class="label">Authenticator</span><span class="value green">FormLogin</span></div>
                    </div>
                </div>

                <!-- Composants -->
                <h2 class="section-title">Composants du framework</h2>
                <div class="components-grid">
                    <div class="component-tag"><span class="icon">📨</span>HttpFoundation</div>
                    <div class="component-tag"><span class="icon">⚡</span>EventDispatcher</div>
                    <div class="component-tag"><span class="icon">🛣️</span>Routing</div>
                    <div class="component-tag"><span class="icon">🧠</span>HttpKernel</div>
                    <div class="component-tag"><span class="icon">📦</span>DI Container</div>
                    <div class="component-tag"><span class="icon">⚙️</span>Config</div>
                    <div class="component-tag"><span class="icon">🎮</span>Controller</div>
                    <div class="component-tag"><span class="icon">🎨</span>Templating</div>
                    <div class="component-tag"><span class="icon">💻</span>Console</div>
                    <div class="component-tag"><span class="icon">🗄️</span>Database/ORM</div>
                    <div class="component-tag"><span class="icon">🔒</span>Security</div>
                    <div class="component-tag"><span class="icon">📝</span>Form</div>
                </div>

                <!-- Routes -->
                <h2 class="section-title" style="margin-top: 40px;">Routes enregistrées</h2>
                <table class="routes-table">
                    <thead>
                        <tr><th>Nom</th><th>Méthode</th><th>Path</th><th>Contrôleur</th></tr>
                    </thead>
                    <tbody>
                        {$routeRows}
                    </tbody>
                </table>

                <!-- Getting Started -->
                <div class="getting-started">
                    <h2 class="section-title" style="border:none;margin:0 0 12px 0;">Prochaines étapes</h2>
                    <p style="color:#888;margin-bottom:16px;">Créez votre premier contrôleur dans <code style="display:inline;padding:2px 6px;border:none;margin:0;">src/App/Controller/</code></p>
                    <code><span class="comment">// src/App/Controller/HomeController.php</span>
        <span class="keyword">namespace</span> App\Controller;

        <span class="keyword">use</span> RLSQ\Controller\AbstractController;
        <span class="keyword">use</span> RLSQ\Controller\Attribute\Route;
        <span class="keyword">use</span> RLSQ\HttpFoundation\Response;

        <span class="keyword">class</span> HomeController <span class="keyword">extends</span> AbstractController
        {
            <span class="keyword">#[Route(<span class="string">'/'</span>, name: <span class="string">'home'</span>)]</span>
            <span class="keyword">public function</span> index(): Response
            {
                <span class="keyword">return</span> <span class="keyword">\$this</span>->json([<span class="string">'message'</span> => <span class="string">'Hello RLSQ-FRAM!'</span>]);
            }
        }</code>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
