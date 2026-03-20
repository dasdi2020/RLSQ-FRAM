<?php

declare(strict_types=1);

namespace App\ImportExport;

use RLSQ\Database\Connection;

class ImportExportService
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Exporte une table en CSV.
     */
    public function exportCsv(string $tableName, array $columns = []): string
    {
        $colStr = !empty($columns) ? '"' . implode('", "', $columns) . '"' : '*';
        $rows = $this->connection->fetchAll("SELECT {$colStr} FROM \"{$tableName}\" ORDER BY id ASC");

        if (empty($rows)) { return ''; }

        $headers = array_keys($rows[0]);
        $csv = implode(',', $headers) . "\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', $row)) . "\n";
        }

        return $csv;
    }

    /**
     * Exporte une table en JSON.
     */
    public function exportJson(string $tableName): string
    {
        $rows = $this->connection->fetchAll("SELECT * FROM \"{$tableName}\" ORDER BY id ASC");
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Importe des données CSV dans une table.
     * @return array{imported: int, errors: string[]}
     */
    public function importCsv(string $tableName, string $csvContent, array $columnMapping = []): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", trim($csvContent)));
        if (count($lines) < 2) { return ['imported' => 0, 'errors' => ['Fichier vide ou sans données.']]; }

        $headers = str_getcsv($lines[0]);
        $imported = 0;
        $errors = [];

        // Appliquer le mapping si fourni
        if (!empty($columnMapping)) {
            $headers = array_map(fn ($h) => $columnMapping[$h] ?? $h, $headers);
        }

        // Filtrer les colonnes système
        $validHeaders = array_filter($headers, fn ($h) => !in_array($h, ['id', 'created_at', 'updated_at'], true));

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') { continue; }

            $values = str_getcsv($line);
            $data = [];

            foreach ($headers as $j => $header) {
                if (in_array($header, ['id', 'created_at', 'updated_at'], true)) { continue; }
                $data[$header] = $values[$j] ?? null;
            }

            if (empty($data)) { continue; }

            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');

            $cols = array_keys($data);
            $placeholders = array_map(fn ($c) => ':' . $c, $cols);

            try {
                $this->connection->execute(
                    sprintf('INSERT INTO "%s" (%s) VALUES (%s)', $tableName, implode(', ', $cols), implode(', ', $placeholders)),
                    $data,
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$i} : {$e->getMessage()}";
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }
}
