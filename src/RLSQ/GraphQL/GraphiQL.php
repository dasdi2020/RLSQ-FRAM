<?php

declare(strict_types=1);

namespace RLSQ\GraphQL;

/**
 * Génère la page HTML GraphiQL pour explorer l'API GraphQL.
 */
class GraphiQL
{
    public static function render(string $endpoint = '/graphql', string $title = 'RLSQ-FRAM GraphQL'): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedEndpoint = htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$escapedTitle}</title>
            <link rel="stylesheet" href="https://unpkg.com/graphiql@3/graphiql.min.css" />
            <style>
                body { margin: 0; height: 100vh; overflow: hidden; }
                #graphiql { height: calc(100vh - 50px); }
                .rlsq-header {
                    background: linear-gradient(135deg, #1a1a2e, #16213e);
                    color: #fff; padding: 12px 24px; height: 50px;
                    display: flex; align-items: center; gap: 12px;
                    border-bottom: 3px solid #e535ab;
                }
                .rlsq-header .logo { font-weight: 800; font-size: 20px; color: #ff3e00; }
                .rlsq-header .title { font-size: 15px; color: #aaa; }
                .rlsq-header .graphql-badge { color: #e535ab; font-weight: 700; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="rlsq-header">
                <span class="logo">R</span>
                <span class="title">{$escapedTitle}</span>
                <span class="graphql-badge">GraphQL</span>
            </div>
            <div id="graphiql"></div>
            <script src="https://unpkg.com/react@18/umd/react.production.min.js" crossorigin></script>
            <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js" crossorigin></script>
            <script src="https://unpkg.com/graphiql@3/graphiql.min.js" crossorigin></script>
            <script>
                const fetcher = GraphiQL.createFetcher({
                    url: '{$escapedEndpoint}',
                });
                const root = ReactDOM.createRoot(document.getElementById('graphiql'));
                root.render(
                    React.createElement(GraphiQL, {
                        fetcher: fetcher,
                        defaultEditorToolsVisibility: true,
                    })
                );
            </script>
        </body>
        </html>
        HTML;
    }
}
