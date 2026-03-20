<?php

declare(strict_types=1);

namespace App\Embed;

use RLSQ\Database\Connection;

/**
 * Gestion des configurations d'embed (iframe) pour les sites externes.
 */
class EmbedService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function createEmbed(array $data): array
    {
        $token = bin2hex(random_bytes(32));

        $this->connection->execute(
            'INSERT INTO embed_configs (name, module_slug, token, allowed_domains, settings, theme)
             VALUES (:n, :ms, :t, :ad, :s, :th)',
            [
                'n' => $data['name'] ?? 'Embed',
                'ms' => $data['module_slug'] ?? throw new \InvalidArgumentException('module_slug requis.'),
                't' => $token,
                'ad' => json_encode($data['allowed_domains'] ?? ['*']),
                's' => json_encode($data['settings'] ?? []),
                'th' => json_encode($data['theme'] ?? []),
            ],
        );

        return $this->getEmbed((int) $this->connection->lastInsertId());
    }

    public function getEmbed(int $id): ?array
    {
        $e = $this->connection->fetchOne('SELECT * FROM embed_configs WHERE id = :id', ['id' => $id]);
        return $e ? $this->hydrateEmbed($e) : null;
    }

    public function getEmbedByToken(string $token): ?array
    {
        $e = $this->connection->fetchOne('SELECT * FROM embed_configs WHERE token = :t AND is_active = 1', ['t' => $token]);
        if ($e) {
            // Incrémenter le compteur de vues
            $this->connection->execute('UPDATE embed_configs SET views_count = views_count + 1 WHERE id = :id', ['id' => $e['id']]);
        }
        return $e ? $this->hydrateEmbed($e) : null;
    }

    /** @return array[] */
    public function getAllEmbeds(): array
    {
        $embeds = $this->connection->fetchAll('SELECT * FROM embed_configs ORDER BY created_at DESC');
        return array_map(fn ($e) => $this->hydrateEmbed($e), $embeds);
    }

    public function updateEmbed(int $id, array $data): ?array
    {
        $sets = [];
        $params = ['id' => $id];

        foreach (['name', 'module_slug', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[$f] = $data[$f];
            }
        }
        foreach (['allowed_domains', 'settings', 'theme'] as $jf) {
            if (array_key_exists($jf, $data)) {
                $sets[] = "{$jf} = :{$jf}";
                $params[$jf] = json_encode($data[$jf]);
            }
        }

        if (!empty($sets)) {
            $sets[] = 'updated_at = :now';
            $params['now'] = date('Y-m-d H:i:s');
            $this->connection->execute('UPDATE embed_configs SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        }

        return $this->getEmbed($id);
    }

    public function deleteEmbed(int $id): void
    {
        $this->connection->execute('DELETE FROM embed_configs WHERE id = :id', ['id' => $id]);
    }

    public function regenerateToken(int $id): ?array
    {
        $newToken = bin2hex(random_bytes(32));
        $this->connection->execute('UPDATE embed_configs SET token = :t, updated_at = :now WHERE id = :id',
            ['t' => $newToken, 'now' => date('Y-m-d H:i:s'), 'id' => $id]);
        return $this->getEmbed($id);
    }

    /**
     * Vérifie si un domaine est autorisé pour cet embed.
     */
    public function isDomainAllowed(array $embed, string $origin): bool
    {
        $allowed = $embed['allowed_domains'] ?? ['*'];

        if (in_array('*', $allowed, true)) {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST) ?? $origin;

        foreach ($allowed as $domain) {
            if ($domain === $originHost) {
                return true;
            }
            // Wildcard subdomain : *.example.com
            if (str_starts_with($domain, '*.')) {
                $baseDomain = substr($domain, 2);
                if (str_ends_with($originHost, $baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Génère le snippet JS à copier-coller.
     */
    public function generateSnippet(array $embed, string $baseUrl): string
    {
        $token = htmlspecialchars($embed['token'], ENT_QUOTES, 'UTF-8');
        $module = htmlspecialchars($embed['module_slug'], ENT_QUOTES, 'UTF-8');
        $url = rtrim($baseUrl, '/');

        return <<<JS
        <!-- RLSQ-FRAM Embed: {$embed['name']} -->
        <div id="rlsq-embed-{$token}"></div>
        <script>
        (function() {
            var c = document.getElementById('rlsq-embed-{$token}');
            var f = document.createElement('iframe');
            f.src = '{$url}/embed/{$token}';
            f.style.cssText = 'width:100%;border:none;min-height:400px;';
            f.setAttribute('allowpaymentrequest', '');
            f.setAttribute('allow', 'payment');
            c.appendChild(f);

            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'rlsq-resize' && e.data.token === '{$token}') {
                    f.style.height = e.data.height + 'px';
                }
                if (e.data && e.data.type === 'rlsq-payment-success') {
                    c.dispatchEvent(new CustomEvent('rlsq:payment', { detail: e.data }));
                }
            });
        })();
        </script>
        JS;
    }

    private function hydrateEmbed(array $e): array
    {
        $e['allowed_domains'] = json_decode($e['allowed_domains'] ?? '[]', true);
        $e['settings'] = json_decode($e['settings'] ?? '{}', true);
        $e['theme'] = json_decode($e['theme'] ?? '{}', true);
        return $e;
    }
}
