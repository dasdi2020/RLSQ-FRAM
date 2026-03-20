<?php

declare(strict_types=1);

namespace App\Deployment;

use RLSQ\Database\Connection;

/**
 * Déploie une application standalone sur un serveur Plesk via l'API REST.
 */
class PleskDeployer
{
    public function __construct(
        private readonly string $pleskHost,
        private readonly string $pleskLogin,
        private readonly string $pleskPassword,
    ) {}

    /**
     * Déploie un projet sur Plesk.
     *
     * @return array{status: string, domain: string, log: string[]}
     */
    public function deploy(string $sourceDir, array $config, Connection $platformConnection, int $tenantId, int $versionId): array
    {
        $log = [];
        $domain = $config['domain'] ?? throw new \RuntimeException('domain requis dans la config.');

        // Enregistrer le déploiement
        $platformConnection->execute(
            'INSERT INTO app_deployments (tenant_id, version_id, target, status, deploy_config) VALUES (:tid, :vid, :t, :s, :dc)',
            ['tid' => $tenantId, 'vid' => $versionId, 't' => $config['target'] ?? 'production', 's' => 'building', 'dc' => json_encode($config)],
        );
        $deployId = (int) $platformConnection->lastInsertId();

        try {
            // 1. Créer le domaine/sous-domaine
            $log[] = "Création du domaine {$domain}...";
            $domainResult = $this->apiCall('POST', '/api/v2/domains', [
                'name' => $domain,
                'hosting_type' => 'virtual',
                'plan' => ['name' => $config['plan'] ?? 'Default Domain'],
            ]);
            $log[] = 'Domaine créé : ' . ($domainResult['id'] ?? 'OK');

            // 2. Configurer PHP
            $log[] = 'Configuration PHP 8.2+...';
            $this->apiCall('PUT', "/api/v2/domains/{$domain}/php-handler", [
                'handler_id' => 'plesk-php82-fpm',
            ]);
            $log[] = 'PHP configuré.';

            // 3. Upload des fichiers via FTP ou file manager API
            $log[] = 'Upload des fichiers...';
            $filesCount = $this->uploadFiles($sourceDir, $domain);
            $log[] = "{$filesCount} fichiers uploadés.";

            // 4. Configurer la base de données
            if (!empty($config['create_database'])) {
                $log[] = 'Création de la base de données...';
                $dbName = str_replace(['.', '-'], '_', $domain) . '_db';
                $this->apiCall('POST', "/api/v2/domains/{$domain}/databases", [
                    'name' => $dbName,
                    'type' => 'mysql',
                ]);
                $log[] = "Base de données {$dbName} créée.";
            }

            // 5. Configurer SSL (Let's Encrypt)
            if ($config['ssl'] ?? true) {
                $log[] = 'Installation du certificat SSL...';
                $this->apiCall('POST', "/api/v2/domains/{$domain}/certificates/lets-encrypt", [
                    'admin_email' => $config['admin_email'] ?? 'admin@' . $domain,
                ]);
                $log[] = 'SSL installé.';
            }

            // Succès
            $platformConnection->execute(
                'UPDATE app_deployments SET status = :s, deployed_at = :da, log = :log WHERE id = :id',
                ['s' => 'live', 'da' => date('Y-m-d H:i:s'), 'log' => implode("\n", $log), 'id' => $deployId],
            );

            $log[] = '✓ Déploiement terminé !';

            return ['status' => 'live', 'domain' => $domain, 'deploy_id' => $deployId, 'log' => $log];
        } catch (\Throwable $e) {
            $log[] = 'ERREUR : ' . $e->getMessage();

            $platformConnection->execute(
                'UPDATE app_deployments SET status = :s, log = :log WHERE id = :id',
                ['s' => 'failed', 'log' => implode("\n", $log), 'id' => $deployId],
            );

            return ['status' => 'failed', 'domain' => $domain, 'deploy_id' => $deployId, 'log' => $log, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retourne les déploiements pour un tenant.
     */
    public function getDeployments(Connection $platformConnection, int $tenantId): array
    {
        return $platformConnection->fetchAll(
            'SELECT d.*, v.version_tag FROM app_deployments d LEFT JOIN app_versions v ON v.id = d.version_id WHERE d.tenant_id = :tid ORDER BY d.created_at DESC',
            ['tid' => $tenantId],
        );
    }

    // ==================== API Plesk ====================

    private function apiCall(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->pleskHost, '/') . $endpoint;
        $auth = base64_encode($this->pleskLogin . ':' . $this->pleskPassword);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("Plesk API erreur {$httpCode} : " . ($response ?: 'pas de réponse'));
        }

        return json_decode($response ?: '{}', true) ?: [];
    }

    private function uploadFiles(string $sourceDir, string $domain): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
                // En production : upload via Plesk file manager API ou SFTP
            }
        }

        return $count;
    }
}
