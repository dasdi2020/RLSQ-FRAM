<?php

declare(strict_types=1);

namespace App\Embed;

use RLSQ\Database\Connection;

/**
 * Génère le HTML affiché dans l'iframe embed.
 * Affiche les formations, activités, etc. selon le module configuré.
 */
class EmbedRenderer
{
    public function __construct(
        private readonly Connection $tenantConnection,
    ) {}

    /**
     * Génère le HTML complet de l'iframe.
     */
    public function render(array $embed): string
    {
        $theme = $embed['theme'] ?? [];
        $settings = $embed['settings'] ?? [];
        $primaryColor = $theme['primary_color'] ?? '#ff3e00';
        $bgColor = $theme['background_color'] ?? '#ffffff';
        $textColor = $theme['text_color'] ?? '#333333';
        $fontFamily = $theme['font_family'] ?? 'system-ui, -apple-system, sans-serif';

        $content = match ($embed['module_slug']) {
            'formations' => $this->renderFormations($settings, $primaryColor),
            'activities' => $this->renderActivities($settings, $primaryColor),
            'calendar' => $this->renderCalendar($settings),
            'room-booking' => $this->renderRoomBooking($settings, $primaryColor),
            default => '<p>Module non supporté pour l\'embed.</p>',
        };

        $token = htmlspecialchars($embed['token'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { margin:0; padding:0; box-sizing:border-box; }
                body { font-family:{$fontFamily}; background:{$bgColor}; color:{$textColor}; padding:16px; }
                .embed-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; }
                .embed-card { border:1px solid #e0e0e0; border-radius:12px; overflow:hidden; transition:box-shadow .2s; }
                .embed-card:hover { box-shadow:0 4px 20px rgba(0,0,0,0.1); }
                .embed-card-img { height:180px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; color:#aaa; }
                .embed-card-img img { width:100%; height:100%; object-fit:cover; }
                .embed-card-body { padding:16px; }
                .embed-card-title { font-size:1.1rem; font-weight:700; margin-bottom:4px; }
                .embed-card-meta { font-size:0.85rem; color:#888; margin-bottom:8px; }
                .embed-card-desc { font-size:0.9rem; color:#666; margin-bottom:12px; line-height:1.5; }
                .embed-card-footer { display:flex; align-items:center; justify-content:space-between; }
                .embed-price { font-size:1.2rem; font-weight:700; color:{$primaryColor}; }
                .embed-btn { display:inline-block; padding:8px 20px; background:{$primaryColor}; color:#fff; border:none; border-radius:6px; font-size:0.9rem; font-weight:600; cursor:pointer; text-decoration:none; }
                .embed-btn:hover { opacity:0.9; }
                .embed-empty { text-align:center; padding:40px; color:#aaa; }
                .embed-header { margin-bottom:20px; }
                .embed-header h2 { font-size:1.3rem; font-weight:700; }
            </style>
        </head>
        <body>
            {$content}
            <script>
                // Auto-resize iframe
                function notifyParent() {
                    window.parent.postMessage({
                        type: 'rlsq-resize',
                        token: '{$token}',
                        height: document.body.scrollHeight + 32
                    }, '*');
                }
                window.addEventListener('load', notifyParent);
                new ResizeObserver(notifyParent).observe(document.body);

                // Handle payment buttons
                document.querySelectorAll('[data-action="register"]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var itemId = this.dataset.itemId;
                        var moduleSlug = this.dataset.module;
                        window.parent.postMessage({
                            type: 'rlsq-register',
                            token: '{$token}',
                            module: moduleSlug,
                            item_id: itemId
                        }, '*');
                    });
                });
            </script>
        </body>
        </html>
        HTML;
    }

    private function renderFormations(array $settings, string $color): string
    {
        try {
            $formations = $this->tenantConnection->fetchAll(
                "SELECT * FROM formations WHERE status = 'published' ORDER BY start_date ASC LIMIT 20"
            );
        } catch (\Throwable) {
            return '<div class="embed-empty">Module formations non installé.</div>';
        }

        if (empty($formations)) {
            return '<div class="embed-empty">Aucune formation disponible pour le moment.</div>';
        }

        $html = '<div class="embed-header"><h2>Formations disponibles</h2></div><div class="embed-grid">';

        foreach ($formations as $f) {
            $title = htmlspecialchars($f['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(mb_substr($f['description'] ?? '', 0, 120), ENT_QUOTES, 'UTF-8');
            $price = number_format((float) ($f['price'] ?? 0), 2, ',', ' ');
            $date = $f['start_date'] ? date('d/m/Y', strtotime($f['start_date'])) : '';
            $img = $f['image_url'] ? '<img src="' . htmlspecialchars($f['image_url'], ENT_QUOTES, 'UTF-8') . '" alt="' . $title . '">' : $title;

            $html .= <<<CARD
            <div class="embed-card">
                <div class="embed-card-img">{$img}</div>
                <div class="embed-card-body">
                    <div class="embed-card-title">{$title}</div>
                    <div class="embed-card-meta">{$date}{$this->formatLocation($f)}</div>
                    <div class="embed-card-desc">{$desc}</div>
                    <div class="embed-card-footer">
                        <span class="embed-price">{$price} {$f['currency']}</span>
                        <button class="embed-btn" data-action="register" data-item-id="{$f['id']}" data-module="formations">S'inscrire</button>
                    </div>
                </div>
            </div>
            CARD;
        }

        return $html . '</div>';
    }

    private function renderActivities(array $settings, string $color): string
    {
        try {
            $activities = $this->tenantConnection->fetchAll(
                "SELECT * FROM activities WHERE status = 'published' ORDER BY start_date ASC LIMIT 20"
            );
        } catch (\Throwable) {
            return '<div class="embed-empty">Module activités non installé.</div>';
        }

        if (empty($activities)) {
            return '<div class="embed-empty">Aucune activité disponible.</div>';
        }

        $html = '<div class="embed-header"><h2>Activités</h2></div><div class="embed-grid">';

        foreach ($activities as $a) {
            $title = htmlspecialchars($a['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(mb_substr($a['description'] ?? '', 0, 120), ENT_QUOTES, 'UTF-8');
            $price = (float) ($a['price'] ?? 0);
            $priceStr = $price > 0 ? number_format($price, 2, ',', ' ') . ' ' . ($a['currency'] ?? 'CAD') : 'Gratuit';
            $cat = $a['category'] ? '<span style="color:' . $color . '">' . htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') . '</span> — ' : '';

            $html .= <<<CARD
            <div class="embed-card">
                <div class="embed-card-body">
                    <div class="embed-card-title">{$title}</div>
                    <div class="embed-card-meta">{$cat}{$this->formatLocation($a)}</div>
                    <div class="embed-card-desc">{$desc}</div>
                    <div class="embed-card-footer">
                        <span class="embed-price">{$priceStr}</span>
                        <button class="embed-btn" data-action="register" data-item-id="{$a['id']}" data-module="activities">S'inscrire</button>
                    </div>
                </div>
            </div>
            CARD;
        }

        return $html . '</div>';
    }

    private function renderCalendar(array $settings): string
    {
        try {
            $events = $this->tenantConnection->fetchAll(
                "SELECT * FROM calendar_events WHERE is_public = 1 AND start_at >= date('now') ORDER BY start_at ASC LIMIT 30"
            );
        } catch (\Throwable) {
            return '<div class="embed-empty">Module calendrier non installé.</div>';
        }

        if (empty($events)) {
            return '<div class="embed-empty">Aucun événement à venir.</div>';
        }

        $html = '<div class="embed-header"><h2>Événements à venir</h2></div><div style="display:flex;flex-direction:column;gap:8px;">';

        foreach ($events as $ev) {
            $title = htmlspecialchars($ev['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $date = date('d/m/Y H:i', strtotime($ev['start_at']));
            $color = $ev['color'] ?? '#ff3e00';

            $html .= "<div style='display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #e0e0e0;border-radius:8px;border-left:4px solid {$color}'>";
            $html .= "<div><div style='font-weight:600'>{$title}</div><div style='font-size:0.85rem;color:#888'>{$date}</div></div></div>";
        }

        return $html . '</div>';
    }

    private function renderRoomBooking(array $settings, string $color): string
    {
        try {
            $rooms = $this->tenantConnection->fetchAll("SELECT * FROM rooms WHERE is_active = 1 ORDER BY name");
        } catch (\Throwable) {
            return '<div class="embed-empty">Module location de salles non installé.</div>';
        }

        if (empty($rooms)) {
            return '<div class="embed-empty">Aucune salle disponible.</div>';
        }

        $html = '<div class="embed-header"><h2>Salles disponibles</h2></div><div class="embed-grid">';

        foreach ($rooms as $r) {
            $name = htmlspecialchars($r['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(mb_substr($r['description'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8');
            $rate = number_format((float) ($r['hourly_rate'] ?? 0), 2, ',', ' ');
            $cap = (int) ($r['capacity'] ?? 0);

            $html .= <<<CARD
            <div class="embed-card">
                <div class="embed-card-body">
                    <div class="embed-card-title">{$name}</div>
                    <div class="embed-card-meta">Capacité : {$cap} pers.</div>
                    <div class="embed-card-desc">{$desc}</div>
                    <div class="embed-card-footer">
                        <span class="embed-price">{$rate} $/h</span>
                        <button class="embed-btn" data-action="register" data-item-id="{$r['id']}" data-module="room-booking">Réserver</button>
                    </div>
                </div>
            </div>
            CARD;
        }

        return $html . '</div>';
    }

    private function formatLocation(array $item): string
    {
        $loc = $item['location'] ?? '';
        return $loc ? ' — ' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') : '';
    }
}
