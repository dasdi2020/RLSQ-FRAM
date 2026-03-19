<?php

declare(strict_types=1);

namespace RLSQ\Config\Yaml;

use RLSQ\Config\Exception\ParseException;

/**
 * Parser YAML minimaliste.
 *
 * Supporte : maps, listes, scalaires (string, int, float, bool, null),
 * chaînes entre guillemets, commentaires (#), chaînes multi-lignes (|, >).
 * Ne supporte PAS : ancres (&), alias (*), tags (!!), documents multiples (---).
 */
class YamlParser
{
    public function parseFile(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new ParseException('Fichier illisible', $file);
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new ParseException('Impossible de lire le fichier', $file);
        }

        return $this->parse($content);
    }

    public function parse(string $input): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $input));

        return $this->parseLines($lines, 0, 0, count($lines));
    }

    private function parseLines(array $lines, int $baseIndent, int $start, int $end): array
    {
        $result = [];
        $isList = null; // null = pas encore déterminé, true = liste, false = map
        $i = $start;

        while ($i < $end) {
            $raw = $lines[$i];
            $trimmed = ltrim($raw);

            // Ligne vide ou commentaire
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $i++;
                continue;
            }

            $indent = strlen($raw) - strlen($trimmed);

            // Si l'indentation est inférieure à la base, on est sorti du bloc
            if ($indent < $baseIndent) {
                break;
            }

            // Élément de liste : "- ..."
            if (str_starts_with($trimmed, '- ')) {
                $isList = true;
                $value = substr($trimmed, 2);
                $value = $this->stripComment($value);

                // "- key: value" → liste de maps
                if (str_contains($value, ': ') || (str_contains($value, ':') && str_ends_with(rtrim($value), ':'))) {
                    // Chercher le bloc enfant de cet élément de liste
                    $childEnd = $this->findBlockEnd($lines, $indent + 2, $i + 1, $end);

                    // Construire les lignes virtuelles : la première ligne + les enfants
                    $childLines = [$value];
                    for ($j = $i + 1; $j < $childEnd; $j++) {
                        // Ré-indenter relativement
                        $childLines[] = substr($lines[$j], min(strlen($lines[$j]), $indent + 2));
                    }

                    $result[] = $this->parseLines(
                        $childLines,
                        0,
                        0,
                        count($childLines),
                    );
                    $i = $childEnd;
                } else {
                    $result[] = $this->castValue($value);
                    $i++;
                }
                continue;
            }

            // "- " seul (valeur sur la ligne suivante indentée)
            if ($trimmed === '-') {
                $isList = true;
                $childEnd = $this->findBlockEnd($lines, $indent + 2, $i + 1, $end);
                $result[] = $this->parseLines($lines, $indent + 2, $i + 1, $childEnd);
                $i = $childEnd;
                continue;
            }

            // Map : "key: value"
            $colonPos = strpos($trimmed, ':');
            if ($colonPos !== false) {
                $isList = false;
                $key = substr($trimmed, 0, $colonPos);
                $key = trim($key, " \t\"'");
                $rest = substr($trimmed, $colonPos + 1);
                $rest = ltrim($rest);
                $rest = $this->stripComment($rest);

                if ($rest === '' || $rest === '|' || $rest === '>') {
                    $isLiteral = $rest === '|';
                    $isFolded = $rest === '>';

                    // Valeur = bloc enfant
                    $childIndent = $this->detectChildIndent($lines, $i + 1, $end, $indent);

                    if ($childIndent > $indent) {
                        $childEnd = $this->findBlockEnd($lines, $childIndent, $i + 1, $end);

                        if ($isLiteral || $isFolded) {
                            $result[$key] = $this->parseScalarBlock($lines, $childIndent, $i + 1, $childEnd, $isFolded);
                        } else {
                            $result[$key] = $this->parseLines($lines, $childIndent, $i + 1, $childEnd);
                        }

                        $i = $childEnd;
                    } else {
                        $result[$key] = null;
                        $i++;
                    }
                } else {
                    // Valeur inline
                    $result[$key] = $this->castValue($rest);
                    $i++;
                }
                continue;
            }

            $i++;
        }

        return $result;
    }

    /**
     * Supprime un commentaire inline (#) en tenant compte des guillemets.
     */
    private function stripComment(string $value): string
    {
        $value = rtrim($value);
        if ($value === '') {
            return '';
        }

        // Si la valeur est entre guillemets, ne pas toucher
        if (($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            return $value;
        }

        // Chercher # non à l'intérieur de guillemets
        $inQuote = false;
        $quoteChar = '';
        for ($i = 0; $i < strlen($value); $i++) {
            $c = $value[$i];
            if (!$inQuote && ($c === '"' || $c === "'")) {
                $inQuote = true;
                $quoteChar = $c;
            } elseif ($inQuote && $c === $quoteChar) {
                $inQuote = false;
            } elseif (!$inQuote && $c === '#' && $i > 0 && $value[$i - 1] === ' ') {
                return rtrim(substr($value, 0, $i));
            }
        }

        return $value;
    }

    private function castValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // String entre guillemets
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        // Inline array [a, b, c]
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $inner = substr($value, 1, -1);
            if (trim($inner) === '') {
                return [];
            }
            return array_map(fn (string $v) => $this->castValue(trim($v)), explode(',', $inner));
        }

        // Inline map {key: value, ...}
        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $inner = substr($value, 1, -1);
            if (trim($inner) === '') {
                return [];
            }
            $map = [];
            foreach (explode(',', $inner) as $pair) {
                $parts = explode(':', $pair, 2);
                if (count($parts) === 2) {
                    $map[trim($parts[0])] = $this->castValue(trim($parts[1]));
                }
            }
            return $map;
        }

        // Boolean
        $lower = strtolower($value);
        if (in_array($lower, ['true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['false', 'no', 'off'], true)) {
            return false;
        }

        // Null
        if (in_array($lower, ['null', '~'], true)) {
            return null;
        }

        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        // Float
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Détecte le niveau d'indentation du premier enfant.
     */
    private function detectChildIndent(array $lines, int $start, int $end, int $parentIndent): int
    {
        for ($i = $start; $i < $end; $i++) {
            $trimmed = ltrim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            return strlen($lines[$i]) - strlen($trimmed);
        }

        return $parentIndent;
    }

    /**
     * Trouve la fin d'un bloc indenté.
     */
    private function findBlockEnd(array $lines, int $minIndent, int $start, int $end): int
    {
        for ($i = $start; $i < $end; $i++) {
            $trimmed = ltrim($lines[$i]);

            // Les lignes vides et commentaires font partie du bloc
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $indent = strlen($lines[$i]) - strlen($trimmed);
            if ($indent < $minIndent) {
                return $i;
            }
        }

        return $end;
    }

    /**
     * Parse un bloc scalaire littéral (|) ou plié (>).
     */
    private function parseScalarBlock(array $lines, int $indent, int $start, int $end, bool $folded): string
    {
        $result = [];

        for ($i = $start; $i < $end; $i++) {
            $trimmed = ltrim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $result[] = '';
                continue;
            }
            $lineIndent = strlen($lines[$i]) - strlen($trimmed);
            if ($lineIndent < $indent) {
                break;
            }
            $result[] = substr($lines[$i], $indent);
        }

        // Supprimer les lignes vides de fin
        while (!empty($result) && $result[array_key_last($result)] === '') {
            array_pop($result);
        }

        if ($folded) {
            return implode(' ', $result);
        }

        return implode("\n", $result);
    }
}
