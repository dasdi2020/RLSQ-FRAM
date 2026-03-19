<?php

declare(strict_types=1);

namespace RLSQ\GraphQL;

/**
 * Exécute des requêtes GraphQL contre un Schema.
 */
class Executor
{
    public function __construct(
        private readonly Schema $schema,
    ) {}

    /**
     * Exécute une requête GraphQL.
     *
     * @param string $query     La requête GraphQL
     * @param array  $variables Les variables
     * @param mixed  $context   Contexte partagé (ex: utilisateur connecté)
     */
    public function execute(string $query, array $variables = [], mixed $context = null): array
    {
        $query = trim($query);

        try {
            $parsed = $this->parse($query);

            if ($parsed === null) {
                return ['errors' => [['message' => 'Requête invalide.']]];
            }

            $data = $this->resolve($parsed['type'], $parsed['selections'], $variables, $context);

            return ['data' => $data];
        } catch (\Throwable $e) {
            return ['errors' => [['message' => $e->getMessage()]]];
        }
    }

    /**
     * Parse une requête GraphQL simplifiée.
     * Supporte: query { field(arg: val) { subfield } } et mutation { ... }
     */
    private function parse(string $query): ?array
    {
        // Retirer le nom de l'opération si présent : query MyQuery { ... } → query { ... }
        $query = preg_replace('/^(query|mutation)\s+\w+\s*/', '$1 ', $query);

        // Déterminer le type d'opération
        $type = 'query';
        if (str_starts_with($query, 'mutation')) {
            $type = 'mutation';
            $query = substr($query, 8);
        } elseif (str_starts_with($query, 'query')) {
            $query = substr($query, 5);
        }

        $query = trim($query);

        // Extraire le contenu entre { }
        if (!str_starts_with($query, '{')) {
            return null;
        }

        $content = substr($query, 1, -1);
        $selections = $this->parseSelections(trim($content));

        return ['type' => $type, 'selections' => $selections];
    }

    /**
     * Parse les sélections : field(args) { subfields }
     */
    private function parseSelections(string $content): array
    {
        $selections = [];
        $pos = 0;
        $len = strlen($content);

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && ctype_space($content[$pos])) {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            // Lire le nom du champ
            $nameStart = $pos;
            while ($pos < $len && preg_match('/[\w]/', $content[$pos])) {
                $pos++;
            }
            $fieldName = substr($content, $nameStart, $pos - $nameStart);
            if ($fieldName === '') {
                $pos++;
                continue;
            }

            // Skip whitespace
            while ($pos < $len && ctype_space($content[$pos])) {
                $pos++;
            }

            // Arguments (arg: val, ...)
            $args = [];
            if ($pos < $len && $content[$pos] === '(') {
                $end = strpos($content, ')', $pos);
                if ($end !== false) {
                    $argsStr = substr($content, $pos + 1, $end - $pos - 1);
                    $args = $this->parseArgs($argsStr);
                    $pos = $end + 1;
                }
            }

            // Skip whitespace
            while ($pos < $len && ctype_space($content[$pos])) {
                $pos++;
            }

            // Sub-selections { ... }
            $subSelections = [];
            if ($pos < $len && $content[$pos] === '{') {
                $depth = 1;
                $subStart = $pos + 1;
                $pos++;
                while ($pos < $len && $depth > 0) {
                    if ($content[$pos] === '{') {
                        $depth++;
                    } elseif ($content[$pos] === '}') {
                        $depth--;
                    }
                    $pos++;
                }
                $subContent = substr($content, $subStart, $pos - $subStart - 1);
                $subSelections = $this->parseSelections(trim($subContent));
            }

            $selections[] = [
                'field' => $fieldName,
                'args' => $args,
                'selections' => $subSelections,
            ];
        }

        return $selections;
    }

    private function parseArgs(string $argsStr): array
    {
        $args = [];

        foreach (explode(',', $argsStr) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1], ' "\'');

                // Cast
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif ($value === 'null') {
                    $value = null;
                }

                $args[$key] = $value;
            }
        }

        return $args;
    }

    /**
     * Résout les sélections.
     */
    private function resolve(string $operationType, array $selections, array $variables, mixed $context): array
    {
        $fields = $operationType === 'mutation'
            ? $this->schema->getMutations()
            : $this->schema->getQueries();

        $result = [];

        foreach ($selections as $sel) {
            $fieldName = $sel['field'];

            if (!isset($fields[$fieldName])) {
                throw new \RuntimeException(sprintf('Champ "%s" inconnu dans %s.', $fieldName, $operationType));
            }

            $fieldDef = $fields[$fieldName];
            $args = array_merge($variables, $sel['args']);

            // Appeler le resolver
            $resolved = ($fieldDef->resolver)($context, $args);

            // Appliquer les sous-sélections
            if (!empty($sel['selections']) && is_array($resolved)) {
                $resolved = $this->applySelections($resolved, $sel['selections']);
            }

            $result[$fieldName] = $resolved;
        }

        return $result;
    }

    /**
     * Filtre les champs d'un résultat selon les sélections.
     */
    private function applySelections(array $data, array $selections): array
    {
        $fieldNames = array_map(fn ($s) => $s['field'], $selections);

        // Tableau indexé (liste) → appliquer à chaque élément
        if (array_is_list($data)) {
            return array_map(fn ($item) => is_array($item) ? $this->filterFields($item, $fieldNames, $selections) : $item, $data);
        }

        // Objet/map unique
        return $this->filterFields($data, $fieldNames, $selections);
    }

    private function filterFields(array $item, array $fieldNames, array $selections): array
    {
        $result = [];
        foreach ($selections as $sel) {
            $name = $sel['field'];
            if (array_key_exists($name, $item)) {
                $value = $item[$name];
                if (!empty($sel['selections']) && is_array($value)) {
                    $value = $this->applySelections($value, $sel['selections']);
                }
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
