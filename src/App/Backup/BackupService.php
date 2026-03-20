<?php

declare(strict_types=1);

namespace App\Backup;

use RLSQ\Database\Connection;

class BackupService
{
    public function __construct(
        private readonly string $backupDir,
    ) {
        if (!is_dir($this->backupDir)) { mkdir($this->backupDir, 0777, true); }
    }

    /**
     * Crée un backup complet d'une DB tenant (dump SQL + données JSON).
     */
    public function backup(Connection $connection, string $tenantSlug): array
    {
        $timestamp = date('Ymd_His');
        $dir = "{$this->backupDir}/{$tenantSlug}";
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }

        $filename = "{$tenantSlug}_{$timestamp}";

        // Schema SQL
        $tables = $connection->fetchAll("SELECT sql FROM sqlite_master WHERE type='table' AND sql IS NOT NULL");
        $schema = implode(";\n\n", array_column($tables, 'sql')) . ";\n";
        file_put_contents("{$dir}/{$filename}_schema.sql", $schema);

        // Data JSON
        $allTables = $connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $data = [];
        foreach ($allTables as $t) {
            try { $data[$t['name']] = $connection->fetchAll("SELECT * FROM \"{$t['name']}\""); }
            catch (\Throwable) { $data[$t['name']] = []; }
        }
        file_put_contents("{$dir}/{$filename}_data.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [
            'tenant' => $tenantSlug,
            'timestamp' => $timestamp,
            'schema_file' => "{$filename}_schema.sql",
            'data_file' => "{$filename}_data.json",
            'size' => filesize("{$dir}/{$filename}_schema.sql") + filesize("{$dir}/{$filename}_data.json"),
        ];
    }

    /**
     * Liste les backups disponibles pour un tenant.
     */
    public function listBackups(string $tenantSlug): array
    {
        $dir = "{$this->backupDir}/{$tenantSlug}";
        if (!is_dir($dir)) { return []; }

        $files = glob("{$dir}/*_schema.sql") ?: [];
        $backups = [];

        foreach ($files as $f) {
            $base = basename($f, '_schema.sql');
            $dataFile = "{$dir}/{$base}_data.json";
            $backups[] = [
                'name' => $base,
                'schema_file' => basename($f),
                'data_file' => basename($dataFile),
                'size' => filesize($f) + (file_exists($dataFile) ? filesize($dataFile) : 0),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }

        usort($backups, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $backups;
    }

    /**
     * Restaure un backup.
     */
    public function restore(Connection $connection, string $tenantSlug, string $backupName): array
    {
        $dir = "{$this->backupDir}/{$tenantSlug}";
        $dataFile = "{$dir}/{$backupName}_data.json";

        if (!file_exists($dataFile)) {
            throw new \RuntimeException("Backup \"{$backupName}\" introuvable.");
        }

        $data = json_decode(file_get_contents($dataFile), true);
        if (!$data) { throw new \RuntimeException('Fichier de backup corrompu.'); }

        $restored = 0;

        foreach ($data as $table => $rows) {
            try { $connection->exec("DELETE FROM \"{$table}\""); } catch (\Throwable) { continue; }

            foreach ($rows as $row) {
                if (empty($row)) { continue; }
                $cols = array_keys($row);
                $placeholders = array_map(fn ($c) => ':' . $c, $cols);
                try {
                    $connection->execute(sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $table, implode(', ', $cols), implode(', ', $placeholders)), $row);
                    $restored++;
                } catch (\Throwable) {}
            }
        }

        return ['status' => 'restored', 'backup' => $backupName, 'rows_restored' => $restored];
    }
}
