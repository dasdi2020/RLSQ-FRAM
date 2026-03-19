<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

/**
 * Génère le HTML de la Web Debug Toolbar.
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
        $statusColor = match (true) {
            $statusCode >= 500 => '#dc3545',
            $statusCode >= 400 => '#ffc107',
            $statusCode >= 300 => '#17a2b8',
            default => '#28a745',
        };

        $duration = $perf['duration_ms'] ?? 0;
        $timeColor = match (true) {
            $duration > 500 => '#dc3545',
            $duration > 200 => '#ffc107',
            default => '#28a745',
        };

        $html = <<<HTML
        <!-- RLSQ-FRAM Web Debug Toolbar -->
        <div id="rlsq-wdt" style="
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;
            background: #222; color: #ccc; font-family: system-ui, -apple-system, sans-serif;
            font-size: 13px; line-height: 36px; height: 36px;
            display: flex; align-items: center; gap: 0;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.3); user-select: none;
        ">
            <!-- Logo -->
            <div style="padding: 0 12px; background: #333; height: 100%; display: flex; align-items: center; gap: 6px; cursor: pointer;"
                 onclick="document.getElementById('rlsq-profiler-panel').style.display = document.getElementById('rlsq-profiler-panel').style.display === 'none' ? 'block' : 'none'">
                <span style="font-weight: 700; color: #ff3e00; font-size: 14px;">R</span>
                <span style="color: #aaa; font-size: 11px;">RLSQ</span>
            </div>

            <!-- Status Code -->
            <div class="rlsq-wdt-block" style="background: {$statusColor}; color: #fff; padding: 0 10px; font-weight: 600;">
                {$statusCode}
            </div>

            <!-- Route -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; border-right: 1px solid #444;">
                <span style="color: #888;">@</span> {$this->e($route['route'] ?? 'N/A')}
            </div>

            <!-- Controller -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; border-right: 1px solid #444; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <span style="color: #888;">Ctrl</span> {$this->e($this->shortController($route['controller'] ?? 'N/A'))}
            </div>

            <!-- Method -->
            <div class="rlsq-wdt-block" style="padding: 0 10px; border-right: 1px solid #444;">
                <span style="color: #6cb2eb;">{$this->e($req['method'] ?? 'GET')}</span>
            </div>

            <!-- Time -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; border-right: 1px solid #444; color: {$timeColor};">
                ⏱ {$duration} ms
            </div>

            <!-- Memory -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; border-right: 1px solid #444;">
                💾 {$this->e($perf['memory_peak_formatted'] ?? '?')}
            </div>

            <!-- Events -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; border-right: 1px solid #444;">
                ⚡ {$this->e((string)($events['dispatched_count'] ?? 0))} evt
            </div>

            <!-- PHP -->
            <div class="rlsq-wdt-block" style="padding: 0 12px; color: #888;">
                PHP {$this->e($perf['php_version'] ?? PHP_VERSION)}
            </div>

            <!-- Toggle -->
            <div style="margin-left: auto; padding: 0 12px; cursor: pointer; color: #888;"
                 onclick="document.getElementById('rlsq-wdt').style.display='none'">✕</div>
        </div>

        <!-- Profiler Panel (hidden by default) -->
        <div id="rlsq-profiler-panel" style="
            display: none; position: fixed; bottom: 36px; left: 0; right: 0; z-index: 99998;
            background: #1a1a2e; color: #ccc; font-family: system-ui, -apple-system, monospace;
            font-size: 13px; max-height: 50vh; overflow-y: auto;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.5); border-top: 2px solid #ff3e00;
        ">
            <div style="display: flex; gap: 0; min-height: 300px;">
                {$this->renderPanelRequest($req)}
                {$this->renderPanelRoute($route)}
                {$this->renderPanelPerformance($perf)}
                {$this->renderPanelEvents($events)}
            </div>
        </div>
        HTML;

        return $html;
    }

    private function renderPanelRequest(array $data): string
    {
        $headers = '';
        foreach (($data['headers'] ?? []) as $name => $values) {
            $val = is_array($values) ? implode(', ', $values) : $values;
            $headers .= "<tr><td style='color:#ff3e00;padding:2px 8px;'>{$this->e($name)}</td><td style='padding:2px 8px;'>{$this->e((string)$val)}</td></tr>";
        }

        $respHeaders = '';
        foreach (($data['response_headers'] ?? []) as $name => $values) {
            $val = is_array($values) ? implode(', ', $values) : $values;
            $respHeaders .= "<tr><td style='color:#6cb2eb;padding:2px 8px;'>{$this->e($name)}</td><td style='padding:2px 8px;'>{$this->e((string)$val)}</td></tr>";
        }

        return <<<HTML
        <div style="flex: 1; padding: 16px; border-right: 1px solid #333; overflow-y: auto;">
            <h3 style="color: #ff3e00; margin: 0 0 12px 0; font-size: 14px;">Request</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="color:#888;padding:2px 8px;">Method</td><td style="padding:2px 8px;">{$this->e($data['method'] ?? '')}</td></tr>
                <tr><td style="color:#888;padding:2px 8px;">Path</td><td style="padding:2px 8px;">{$this->e($data['path'] ?? '')}</td></tr>
                <tr><td style="color:#888;padding:2px 8px;">Status</td><td style="padding:2px 8px;">{$this->e((string)($data['status_code'] ?? ''))}</td></tr>
                <tr><td style="color:#888;padding:2px 8px;">IP</td><td style="padding:2px 8px;">{$this->e($data['client_ip'] ?? '')}</td></tr>
            </table>
            <h4 style="color:#aaa;margin:12px 0 6px;font-size:12px;">Request Headers</h4>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">{$headers}</table>
            <h4 style="color:#aaa;margin:12px 0 6px;font-size:12px;">Response Headers</h4>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">{$respHeaders}</table>
        </div>
        HTML;
    }

    private function renderPanelRoute(array $data): string
    {
        $params = '';
        foreach (($data['route_params'] ?? []) as $key => $val) {
            $params .= "<tr><td style='color:#ff3e00;padding:2px 8px;'>{$this->e($key)}</td><td style='padding:2px 8px;'>{$this->e((string)$val)}</td></tr>";
        }
        if ($params === '') {
            $params = '<tr><td style="color:#666;padding:2px 8px;" colspan="2">Aucun paramètre</td></tr>';
        }

        return <<<HTML
        <div style="flex: 1; padding: 16px; border-right: 1px solid #333;">
            <h3 style="color: #ff3e00; margin: 0 0 12px 0; font-size: 14px;">Routing</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="color:#888;padding:2px 8px;">Route</td><td style="padding:2px 8px;">{$this->e($data['route'] ?? 'N/A')}</td></tr>
                <tr><td style="color:#888;padding:2px 8px;">Controller</td><td style="padding:2px 8px;word-break:break-all;">{$this->e($data['controller'] ?? 'N/A')}</td></tr>
            </table>
            <h4 style="color:#aaa;margin:12px 0 6px;font-size:12px;">Route Parameters</h4>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">{$params}</table>
        </div>
        HTML;
    }

    private function renderPanelPerformance(array $data): string
    {
        return <<<HTML
        <div style="flex: 1; padding: 16px; border-right: 1px solid #333;">
            <h3 style="color: #ff3e00; margin: 0 0 12px 0; font-size: 14px;">Performance</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="color:#888;padding:4px 8px;">Duration</td><td style="padding:4px 8px;">{$this->e((string)($data['duration_ms'] ?? 0))} ms</td></tr>
                <tr><td style="color:#888;padding:4px 8px;">Memory Peak</td><td style="padding:4px 8px;">{$this->e($data['memory_peak_formatted'] ?? '?')}</td></tr>
                <tr><td style="color:#888;padding:4px 8px;">PHP</td><td style="padding:4px 8px;">{$this->e($data['php_version'] ?? '')}</td></tr>
                <tr><td style="color:#888;padding:4px 8px;">SAPI</td><td style="padding:4px 8px;">{$this->e($data['php_sapi'] ?? '')}</td></tr>
                <tr><td style="color:#888;padding:4px 8px;">Framework</td><td style="padding:4px 8px;">{$this->e($data['framework'] ?? '')}</td></tr>
            </table>
        </div>
        HTML;
    }

    private function renderPanelEvents(array $data): string
    {
        $rows = '';
        foreach (($data['dispatched_events'] ?? []) as $evt) {
            $rows .= "<tr><td style='padding:2px 8px;'>{$this->e($evt['name'])}</td><td style='padding:2px 8px;text-align:center;'>{$evt['listeners']}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2" style="color:#666;padding:2px 8px;">Aucun événement</td></tr>';
        }

        return <<<HTML
        <div style="flex: 1; padding: 16px;">
            <h3 style="color: #ff3e00; margin: 0 0 12px 0; font-size: 14px;">Events ({$this->e((string)($data['dispatched_count'] ?? 0))})</h3>
            <p style="color:#888;font-size:12px;margin:0 0 8px;">{$this->e((string)($data['registered_listeners'] ?? 0))} listeners enregistrés</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <tr style="color:#888;"><th style="text-align:left;padding:2px 8px;">Event</th><th style="padding:2px 8px;">Listeners</th></tr>
                {$rows}
            </table>
        </div>
        HTML;
    }

    private function shortController(string $controller): string
    {
        // App\Controller\HomeController::index → HomeController::index
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
