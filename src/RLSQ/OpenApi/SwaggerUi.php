<?php

declare(strict_types=1);

namespace RLSQ\OpenApi;

/**
 * Génère la page HTML Swagger UI pour visualiser la spec OpenAPI.
 */
class SwaggerUi
{
    public static function render(string $specUrl = '/api/openapi.json', string $title = 'RLSQ-FRAM API'): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedUrl = htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$escapedTitle} — Swagger UI</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            <style>
                body { margin: 0; background: #fafafa; }
                .swagger-ui .topbar { display: none; }
                .rlsq-header {
                    background: linear-gradient(135deg, #1a1a2e, #16213e);
                    color: #fff; padding: 16px 24px;
                    display: flex; align-items: center; gap: 12px;
                    border-bottom: 3px solid #ff3e00;
                }
                .rlsq-header .logo { font-weight: 800; font-size: 20px; color: #ff3e00; }
                .rlsq-header .title { font-size: 16px; color: #aaa; }
            </style>
        </head>
        <body>
            <div class="rlsq-header">
                <span class="logo">R</span>
                <span class="title">{$escapedTitle}</span>
            </div>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: "{$escapedUrl}",
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIBundle.SwaggerUIStandalonePreset
                    ],
                    layout: "BaseLayout",
                });
            </script>
        </body>
        </html>
        HTML;
    }
}
