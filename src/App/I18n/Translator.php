<?php

declare(strict_types=1);

namespace App\I18n;

use RLSQ\Database\Connection;

class Translator
{
    private array $cache = [];

    public function __construct(
        private readonly Connection $connection,
        private string $locale = 'fr',
    ) {
        $this->ensureTable();
    }

    public function setLocale(string $locale): void { $this->locale = $locale; $this->cache = []; }
    public function getLocale(): string { return $this->locale; }

    public function trans(string $key, array $params = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $cacheKey = "{$locale}.{$key}";

        if (!isset($this->cache[$cacheKey])) {
            $parts = explode('.', $key, 2);
            $group = $parts[0] ?? '';
            $name = $parts[1] ?? $key;

            $row = $this->connection->fetchOne(
                'SELECT value FROM translations WHERE locale = :l AND key_group = :g AND key_name = :n',
                ['l' => $locale, 'g' => $group, 'n' => $name],
            );

            $this->cache[$cacheKey] = $row ? $row['value'] : $key;
        }

        $result = $this->cache[$cacheKey];
        foreach ($params as $k => $v) { $result = str_replace("{{$k}}", (string) $v, $result); }

        return $result;
    }

    public function setTranslation(string $locale, string $group, string $name, string $value): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM translations WHERE locale = :l AND key_group = :g AND key_name = :n',
            ['l' => $locale, 'g' => $group, 'n' => $name],
        );

        if ($existing) {
            $this->connection->execute('UPDATE translations SET value = :v, updated_at = :now WHERE id = :id',
                ['v' => $value, 'now' => date('Y-m-d H:i:s'), 'id' => $existing['id']]);
        } else {
            $this->connection->execute('INSERT INTO translations (locale, key_group, key_name, value) VALUES (:l, :g, :n, :v)',
                ['l' => $locale, 'g' => $group, 'n' => $name, 'v' => $value]);
        }

        $this->cache = [];
    }

    /** @return array[] */
    public function getAllForLocale(string $locale): array
    {
        return $this->connection->fetchAll('SELECT * FROM translations WHERE locale = :l ORDER BY key_group, key_name', ['l' => $locale]);
    }

    /** @return string[] */
    public function getAvailableLocales(): array
    {
        $rows = $this->connection->fetchAll('SELECT DISTINCT locale FROM translations ORDER BY locale');
        return array_column($rows, 'locale');
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS translations (id INTEGER PRIMARY KEY AUTOINCREMENT, locale VARCHAR(5) NOT NULL, key_group VARCHAR(100) NOT NULL, key_name VARCHAR(100) NOT NULL, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(locale, key_group, key_name))');
    }
}
