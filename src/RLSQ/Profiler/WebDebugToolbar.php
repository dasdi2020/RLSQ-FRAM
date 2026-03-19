<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

/**
 * Génère le HTML de la Web Debug Toolbar avec panneau à onglets style Symfony.
 */
class WebDebugToolbar
{
    public function render(Profiler $profiler): string
    {
        $perf = $profiler->getCollector('performance')?->getData() ?? [];
        $route = $profiler->getCollector('route')?->getData() ?? [];
        $req = $profiler->getCollector('request')?->getData() ?? [];
        $events = $profiler->getCollector('events')?->getData() ?? [];

        $statusCode = $req['status_code'] ?? 200;
        $duration = $perf['duration_ms'] ?? 0;

        $statusColor = match (true) {
            $statusCode >= 500 => '#e74c3c',
            $statusCode >= 400 => '#f39c12',
            $statusCode >= 300 => '#3498db',
            default => '#2ecc71',
        };
        $timeColor = match (true) {
            $duration > 500 => '#e74c3c',
            $duration > 200 => '#f39c12',
            default => '#2ecc71',
        };

        $toolbarItems = $this->renderToolbarItems($req, $route, $perf, $events, $statusColor, $timeColor);
        $panelTabs = $this->renderPanelTabs();
        $panelContents = $this->renderPanelContents($req, $route, $perf, $events);
        $css = $this->renderCSS();
        $js = $this->renderJS();

        return <<<HTML
        <!-- RLSQ-FRAM Web Debug Toolbar -->
        <style>{$css}</style>
        <div id="rlsq-wdt">
            <div class="wdt-bar">
                {$toolbarItems}
                <div class="wdt-item wdt-close" onclick="rlsqWdt.close()">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><path d="M1.05.34L6 5.29 10.95.34 11.66 1.05 6.71 6l4.95 4.95-.71.71L6 6.71 1.05 11.66.34 10.95 5.29 6 .34 1.05z"/></svg>
                </div>
            </div>
            <div id="rlsq-profiler" class="wdt-profiler">
                <div class="wdt-profiler-sidebar">
                    <div class="wdt-profiler-header">
                        <span class="wdt-logo-lg">R</span>
                        <span class="wdt-logo-text">Profiler</span>
                    </div>
                    <nav class="wdt-tabs">
                        {$panelTabs}
                    </nav>
                </div>
                <div class="wdt-profiler-content">
                    {$panelContents}
                </div>
            </div>
        </div>
        <script>{$js}</script>
        HTML;
    }

    private function renderToolbarItems(array $req, array $route, array $perf, array $events, string $statusColor, string $timeColor): string
    {
        $statusCode = $req['status_code'] ?? 200;
        $duration = $perf['duration_ms'] ?? 0;
        $memory = $perf['memory_peak_formatted'] ?? '?';
        $method = $req['method'] ?? 'GET';
        $routeName = $route['route'] ?? 'N/A';
        $controller = $this->shortController($route['controller'] ?? 'N/A');
        $evtCount = $events['dispatched_count'] ?? 0;
        $phpVersion = $perf['php_version'] ?? PHP_VERSION;

        return <<<HTML
        <div class="wdt-item wdt-logo" onclick="rlsqWdt.toggle()">
            <span class="wdt-logo-icon">R</span>
        </div>
        <div class="wdt-item wdt-clickable" onclick="rlsqWdt.open('request')" title="HTTP Status">
            <span class="wdt-badge" style="background:{$statusColor}">{$statusCode}</span>
            <span class="wdt-label">{$this->e($method)}</span>
        </div>
        <div class="wdt-sep"></div>
        <div class="wdt-item wdt-clickable" onclick="rlsqWdt.open('routing')" title="Route">
            <svg class="wdt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h4l3-9 4 18 3-9h4"/></svg>
            <span class="wdt-label">{$this->e($routeName)}</span>
        </div>
        <div class="wdt-sep"></div>
        <div class="wdt-item wdt-clickable" onclick="rlsqWdt.open('performance')" title="Performance">
            <svg class="wdt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="wdt-value" style="color:{$timeColor}">{$duration} ms</span>
        </div>
        <div class="wdt-sep"></div>
        <div class="wdt-item wdt-clickable" onclick="rlsqWdt.open('performance')" title="Memory">
            <svg class="wdt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/></svg>
            <span class="wdt-value">{$this->e($memory)}</span>
        </div>
        <div class="wdt-sep"></div>
        <div class="wdt-item wdt-clickable" onclick="rlsqWdt.open('events')" title="Events">
            <svg class="wdt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <span class="wdt-value">{$evtCount}</span>
        </div>
        <div class="wdt-sep"></div>
        <div class="wdt-item" title="PHP Version">
            <svg class="wdt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span class="wdt-value wdt-muted">PHP {$this->e($phpVersion)}</span>
        </div>
        HTML;
    }

    private function renderPanelTabs(): string
    {
        $tabs = [
            ['id' => 'request', 'icon' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>', 'label' => 'Request'],
            ['id' => 'routing', 'icon' => '<path d="M3 12h4l3-9 4 18 3-9h4"/>', 'label' => 'Routing'],
            ['id' => 'performance', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', 'label' => 'Performance'],
            ['id' => 'events', 'icon' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>', 'label' => 'Events'],
        ];

        $html = '';
        foreach ($tabs as $i => $tab) {
            $active = $i === 0 ? ' active' : '';
            $html .= <<<HTML
            <button class="wdt-tab{$active}" onclick="rlsqWdt.switchTab('{$tab['id']}')" data-tab="{$tab['id']}">
                <svg class="wdt-tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{$tab['icon']}</svg>
                <span>{$tab['label']}</span>
            </button>
            HTML;
        }

        return $html;
    }

    private function renderPanelContents(array $req, array $route, array $perf, array $events): string
    {
        return
            $this->renderRequestPanel($req) .
            $this->renderRoutingPanel($route) .
            $this->renderPerformancePanel($perf) .
            $this->renderEventsPanel($events);
    }

    private function renderRequestPanel(array $data): string
    {
        $method = $this->e($data['method'] ?? '');
        $path = $this->e($data['path'] ?? '');
        $status = $this->e((string)($data['status_code'] ?? ''));
        $ip = $this->e($data['client_ip'] ?? 'N/A');
        $contentType = $this->e($data['content_type'] ?? 'N/A');

        $reqHeaders = $this->renderHeadersTable($data['headers'] ?? []);
        $resHeaders = $this->renderHeadersTable($data['response_headers'] ?? []);

        $queryParams = '';
        foreach (($data['query'] ?? []) as $k => $v) {
            $queryParams .= $this->tableRow($k, is_array($v) ? json_encode($v) : (string)$v);
        }
        $querySection = $queryParams !== '' ? <<<HTML
        <div class="wdt-panel-section">
            <h4>Query Parameters</h4>
            <table class="wdt-table">{$queryParams}</table>
        </div>
        HTML : '';

        $postParams = '';
        foreach (($data['request'] ?? []) as $k => $v) {
            $postParams .= $this->tableRow($k, is_array($v) ? json_encode($v) : (string)$v);
        }
        $postSection = $postParams !== '' ? <<<HTML
        <div class="wdt-panel-section">
            <h4>Request Body</h4>
            <table class="wdt-table">{$postParams}</table>
        </div>
        HTML : '';

        return <<<HTML
        <div class="wdt-panel active" data-panel="request">
            <h3>Request / Response</h3>
            <div class="wdt-panel-grid">
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Method</div>
                    <div class="wdt-info-value wdt-mono">{$method}</div>
                </div>
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Path</div>
                    <div class="wdt-info-value wdt-mono">{$path}</div>
                </div>
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Status</div>
                    <div class="wdt-info-value">{$status}</div>
                </div>
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Content-Type</div>
                    <div class="wdt-info-value wdt-mono">{$contentType}</div>
                </div>
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Client IP</div>
                    <div class="wdt-info-value wdt-mono">{$ip}</div>
                </div>
            </div>
            {$querySection}
            {$postSection}
            <div class="wdt-panel-section">
                <h4>Request Headers</h4>
                <table class="wdt-table">{$reqHeaders}</table>
            </div>
            <div class="wdt-panel-section">
                <h4>Response Headers</h4>
                <table class="wdt-table">{$resHeaders}</table>
            </div>
        </div>
        HTML;
    }

    private function renderRoutingPanel(array $data): string
    {
        $routeName = $this->e($data['route'] ?? 'N/A');
        $controller = $this->e($data['controller'] ?? 'N/A');

        $params = '';
        foreach (($data['route_params'] ?? []) as $key => $val) {
            $params .= $this->tableRow($key, (string)$val);
        }
        if ($params === '') {
            $params = '<tr><td colspan="2" class="wdt-muted" style="padding:8px 12px;">Aucun paramètre de route</td></tr>';
        }

        return <<<HTML
        <div class="wdt-panel" data-panel="routing">
            <h3>Routing</h3>
            <div class="wdt-panel-grid">
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Route Name</div>
                    <div class="wdt-info-value wdt-accent">{$routeName}</div>
                </div>
                <div class="wdt-info-card wdt-info-card-wide">
                    <div class="wdt-info-label">Controller</div>
                    <div class="wdt-info-value wdt-mono">{$controller}</div>
                </div>
            </div>
            <div class="wdt-panel-section">
                <h4>Route Parameters</h4>
                <table class="wdt-table">{$params}</table>
            </div>
        </div>
        HTML;
    }

    private function renderPerformancePanel(array $data): string
    {
        $duration = $this->e((string)($data['duration_ms'] ?? 0));
        $memory = $this->e($data['memory_peak_formatted'] ?? '?');
        $php = $this->e($data['php_version'] ?? '');
        $sapi = $this->e($data['php_sapi'] ?? '');
        $framework = $this->e($data['framework'] ?? '');

        return <<<HTML
        <div class="wdt-panel" data-panel="performance">
            <h3>Performance</h3>
            <div class="wdt-panel-grid">
                <div class="wdt-info-card wdt-info-card-lg">
                    <div class="wdt-info-label">Total Time</div>
                    <div class="wdt-info-value wdt-big">{$duration}<span class="wdt-unit">ms</span></div>
                </div>
                <div class="wdt-info-card wdt-info-card-lg">
                    <div class="wdt-info-label">Peak Memory</div>
                    <div class="wdt-info-value wdt-big">{$memory}</div>
                </div>
            </div>
            <div class="wdt-panel-section">
                <h4>Environment</h4>
                <table class="wdt-table">
                    {$this->tableRow('Framework', $framework)}
                    {$this->tableRow('PHP Version', $php)}
                    {$this->tableRow('SAPI', $sapi)}
                    {$this->tableRow('Extensions', implode(', ', ['PDO', 'json', 'mbstring', 'openssl']))}
                </table>
            </div>
        </div>
        HTML;
    }

    private function renderEventsPanel(array $data): string
    {
        $count = $this->e((string)($data['dispatched_count'] ?? 0));
        $listeners = $this->e((string)($data['registered_listeners'] ?? 0));

        $rows = '';
        foreach (($data['dispatched_events'] ?? []) as $i => $evt) {
            $bg = $i % 2 === 0 ? 'background:rgba(255,255,255,0.02);' : '';
            $name = $this->e($evt['name']);
            $lCount = $evt['listeners'];
            $badge = $lCount > 0
                ? "<span class=\"wdt-badge-sm\">{$lCount}</span>"
                : "<span class=\"wdt-badge-sm wdt-badge-muted\">0</span>";
            $rows .= "<tr style=\"{$bg}\"><td style=\"padding:8px 12px;\" class=\"wdt-mono\">{$name}</td><td style=\"padding:8px 12px;text-align:center;\">{$badge}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2" class="wdt-muted" style="padding:8px 12px;">Aucun événement dispatché</td></tr>';
        }

        return <<<HTML
        <div class="wdt-panel" data-panel="events">
            <h3>Events</h3>
            <div class="wdt-panel-grid">
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Dispatched</div>
                    <div class="wdt-info-value wdt-big">{$count}</div>
                </div>
                <div class="wdt-info-card">
                    <div class="wdt-info-label">Registered Listeners</div>
                    <div class="wdt-info-value wdt-big">{$listeners}</div>
                </div>
            </div>
            <div class="wdt-panel-section">
                <h4>Dispatched Events</h4>
                <table class="wdt-table">
                    <thead><tr><th style="text-align:left;padding:8px 12px;">Event Name</th><th style="padding:8px 12px;width:100px;">Listeners</th></tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        </div>
        HTML;
    }

    private function renderCSS(): string
    {
        return <<<CSS
        #rlsq-wdt { position:fixed;bottom:0;left:0;right:0;z-index:99999;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;font-size:13px; }
        #rlsq-wdt * { box-sizing:border-box; }
        .wdt-bar {
            display:flex;align-items:center;height:36px;background:#1b1b1b;color:#aaa;
            border-top:1px solid #333;user-select:none;
        }
        .wdt-item { display:flex;align-items:center;gap:5px;padding:0 10px;height:100%;white-space:nowrap; }
        .wdt-clickable { cursor:pointer;transition:background .15s; }
        .wdt-clickable:hover { background:#2a2a2a; }
        .wdt-sep { width:1px;height:18px;background:#333;flex-shrink:0; }
        .wdt-logo { background:#252525;cursor:pointer;padding:0 12px;gap:0; }
        .wdt-logo:hover { background:#2a2a2a; }
        .wdt-logo-icon { font-weight:800;font-size:16px;color:#ff3e00; }
        .wdt-badge { display:inline-block;padding:1px 7px;border-radius:3px;font-weight:700;font-size:12px;color:#fff;line-height:1.4; }
        .wdt-icon { width:14px;height:14px;flex-shrink:0;color:#666; }
        .wdt-label { color:#bbb; }
        .wdt-value { color:#ddd; }
        .wdt-muted { color:#666; }
        .wdt-close { margin-left:auto;cursor:pointer;color:#666;padding:0 14px; }
        .wdt-close:hover { color:#e74c3c; }

        .wdt-profiler {
            display:none;position:fixed;bottom:36px;left:0;right:0;top:0;z-index:99998;
            background:#16161e;color:#ccc;
            animation:wdt-slide-up .2s ease-out;
        }
        .wdt-profiler.open { display:flex; }
        @keyframes wdt-slide-up { from{transform:translateY(30px);opacity:0} to{transform:translateY(0);opacity:1} }

        .wdt-profiler-sidebar {
            width:220px;background:#1a1a24;border-right:1px solid #2a2a3a;display:flex;flex-direction:column;flex-shrink:0;
        }
        .wdt-profiler-header {
            padding:20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #2a2a3a;
        }
        .wdt-logo-lg { font-weight:800;font-size:28px;color:#ff3e00; }
        .wdt-logo-text { font-size:15px;color:#888;font-weight:600; }

        .wdt-tabs { display:flex;flex-direction:column;padding:8px 0;flex:1;overflow-y:auto; }
        .wdt-tab {
            display:flex;align-items:center;gap:10px;padding:10px 20px;border:none;background:none;
            color:#888;font-size:13px;cursor:pointer;text-align:left;width:100%;font-family:inherit;
            transition:all .15s;border-left:3px solid transparent;
        }
        .wdt-tab:hover { color:#ccc;background:rgba(255,255,255,0.03); }
        .wdt-tab.active { color:#ff3e00;background:rgba(255,62,0,0.06);border-left-color:#ff3e00; }
        .wdt-tab-icon { width:16px;height:16px;flex-shrink:0; }

        .wdt-profiler-content { flex:1;overflow-y:auto;padding:0; }
        .wdt-panel { display:none;padding:28px 32px; }
        .wdt-panel.active { display:block; }
        .wdt-panel h3 { font-size:18px;font-weight:700;color:#fff;margin:0 0 20px;padding-bottom:12px;border-bottom:1px solid #2a2a3a; }
        .wdt-panel h4 { font-size:12px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.8px;margin:0 0 8px; }

        .wdt-panel-grid { display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px; }
        .wdt-info-card {
            background:#1e1e28;border:1px solid #2a2a3a;border-radius:8px;padding:14px 18px;min-width:140px;flex:1;
        }
        .wdt-info-card-wide { flex:2;min-width:280px; }
        .wdt-info-card-lg { min-width:180px; }
        .wdt-info-label { font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px; }
        .wdt-info-value { font-size:14px;color:#e0e0e0;font-weight:500;word-break:break-all; }
        .wdt-big { font-size:24px;font-weight:700;color:#fff; }
        .wdt-unit { font-size:13px;color:#888;font-weight:400;margin-left:2px; }
        .wdt-accent { color:#ff3e00; }
        .wdt-mono { font-family:'Fira Code','Cascadia Code',monospace;font-size:13px; }

        .wdt-panel-section { margin-bottom:24px; }
        .wdt-table { width:100%;border-collapse:collapse;background:#1e1e28;border:1px solid #2a2a3a;border-radius:8px;overflow:hidden;font-size:13px; }
        .wdt-table thead th { background:#1a1a24;color:#666;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;border-bottom:1px solid #2a2a3a; }
        .wdt-table td { padding:6px 12px;border-bottom:1px solid #222230; }
        .wdt-table tr:last-child td { border-bottom:none; }
        .wdt-table .wdt-key { color:#ff3e00;font-weight:500;white-space:nowrap;width:200px;font-family:'Fira Code','Cascadia Code',monospace;font-size:12px; }
        .wdt-table .wdt-val { color:#ccc;word-break:break-all;font-family:'Fira Code','Cascadia Code',monospace;font-size:12px; }

        .wdt-badge-sm { display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:rgba(255,62,0,0.15);color:#ff3e00; }
        .wdt-badge-muted { background:rgba(255,255,255,0.05);color:#555; }
        CSS;
    }

    private function renderJS(): string
    {
        return <<<JS
        window.rlsqWdt = {
            toggle() {
                const p = document.getElementById('rlsq-profiler');
                p.classList.toggle('open');
            },
            open(tab) {
                const p = document.getElementById('rlsq-profiler');
                p.classList.add('open');
                this.switchTab(tab);
            },
            switchTab(id) {
                document.querySelectorAll('.wdt-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === id));
                document.querySelectorAll('.wdt-panel').forEach(p => p.classList.toggle('active', p.dataset.panel === id));
            },
            close() {
                document.getElementById('rlsq-wdt').style.display = 'none';
            }
        };
        JS;
    }

    private function renderHeadersTable(array $headers): string
    {
        $rows = '';
        foreach ($headers as $name => $values) {
            $val = is_array($values) ? implode(', ', $values) : (string)$values;
            $rows .= $this->tableRow($name, $val);
        }

        return $rows ?: '<tr><td colspan="2" class="wdt-muted" style="padding:8px 12px;">Aucun header</td></tr>';
    }

    private function tableRow(string $key, string $value): string
    {
        return '<tr><td class="wdt-key">' . $this->e($key) . '</td><td class="wdt-val">' . $this->e($value) . '</td></tr>';
    }

    private function shortController(string $controller): string
    {
        if (str_contains($controller, '\\')) {
            $parts = explode('\\', $controller);
            return end($parts);
        }

        return $controller;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
