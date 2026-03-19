<?php

declare(strict_types=1);

namespace RLSQ\Templating;

/**
 * Compile une expression template en code PHP.
 *
 * Supporte :
 *  - Variables simples : name → $name
 *  - Accès propriété/clé : user.name → $user['name'] (ou ->name)
 *  - Filtres : name|upper → strtoupper($name), value|escape → htmlspecialchars(...)
 *  - Opérateurs : and/or/not → &&/||/!
 *  - Comparaisons : ==, !=, <, >, <=, >=
 *  - Littéraux : 'string', 42, true, false, null
 */
class ExpressionCompiler
{
    private static array $filters = [
        'escape' => 'htmlspecialchars(%s, ENT_QUOTES, \'UTF-8\')',
        'e' => 'htmlspecialchars(%s, ENT_QUOTES, \'UTF-8\')',
        'upper' => 'strtoupper(%s)',
        'lower' => 'strtolower(%s)',
        'capitalize' => 'ucfirst(%s)',
        'title' => 'ucwords(%s)',
        'trim' => 'trim(%s)',
        'length' => 'strlen(%s)',
        'nl2br' => 'nl2br(%s)',
        'reverse' => 'strrev(%s)',
        'json' => 'json_encode(%s)',
        'abs' => 'abs(%s)',
        'keys' => 'array_keys(%s)',
        'first' => 'reset(%s)',
        'last' => 'end(%s)',
        'join' => 'implode(\', \', %s)',
        'raw' => '%s',
        'default' => '(%s ?? %s)',
    ];

    public static function compile(string $expr, bool $autoEscape = false): string
    {
        $expr = trim($expr);

        // Séparer l'expression et les filtres
        $parts = self::splitFilters($expr);
        $baseExpr = array_shift($parts);

        $php = self::compileExpression($baseExpr);

        // Appliquer les filtres
        $hasRaw = false;
        $hasEscape = false;

        foreach ($parts as $filter) {
            $filterParts = explode('(', $filter, 2);
            $filterName = trim($filterParts[0]);
            $filterArg = isset($filterParts[1]) ? rtrim($filterParts[1], ')') : null;

            if ($filterName === 'raw') {
                $hasRaw = true;
                continue;
            }

            if ($filterName === 'escape' || $filterName === 'e') {
                $hasEscape = true;
            }

            if ($filterName === 'default') {
                $defaultVal = $filterArg !== null ? self::compileExpression(trim($filterArg)) : "''";
                $php = '(' . $php . ' ?? ' . $defaultVal . ')';
            } elseif ($filterName === 'join' && $filterArg !== null) {
                $php = 'implode(' . self::compileExpression(trim($filterArg)) . ', ' . $php . ')';
            } elseif ($filterName === 'date' && $filterArg !== null) {
                $php = '(new \\DateTimeImmutable(' . $php . '))->format(' . self::compileExpression(trim($filterArg)) . ')';
            } elseif (isset(self::$filters[$filterName])) {
                $php = sprintf(self::$filters[$filterName], $php);
            }
        }

        // Auto-escape si demandé et pas de |raw ni |escape explicite
        if ($autoEscape && !$hasRaw && !$hasEscape) {
            $php = "htmlspecialchars((string)($php), ENT_QUOTES, 'UTF-8')";
        }

        return $php;
    }

    /**
     * Sépare expression|filter1|filter2 en tenant compte des parenthèses.
     */
    private static function splitFilters(string $expr): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($expr); $i++) {
            $c = $expr[$i];

            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            }

            if ($c === '|' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $c;
            }
        }

        $parts[] = trim($current);

        return $parts;
    }

    private static function compileExpression(string $expr): string
    {
        $expr = trim($expr);

        // Littéral string
        if ((str_starts_with($expr, "'") && str_ends_with($expr, "'"))
            || (str_starts_with($expr, '"') && str_ends_with($expr, '"'))) {
            return $expr;
        }

        // Littéral numérique
        if (is_numeric($expr)) {
            return $expr;
        }

        // Booléens et null
        $lower = strtolower($expr);
        if (in_array($lower, ['true', 'false', 'null'], true)) {
            return $lower;
        }

        // Opérateurs logiques
        $expr = preg_replace('/\bnot\s+/', '!', $expr);
        $expr = preg_replace('/\band\b/', '&&', $expr);
        $expr = preg_replace('/\bor\b/', '||', $expr);

        // Opérateur ternaire : a ? b : c → déjà valide en PHP

        // Remplacer les variables et accès par point
        $expr = preg_replace_callback(
            '/\b([a-zA-Z_]\w*(?:\.\w+)*)\b/',
            function (array $m) {
                $var = $m[1];

                // Ne pas toucher aux mots-clés PHP
                if (in_array(strtolower($var), ['true', 'false', 'null', 'empty', 'isset'], true)) {
                    return $var;
                }

                // Accès par point : user.name.first → $user['name']['first']
                $segments = explode('.', $var);
                $result = '$' . $segments[0];

                for ($i = 1; $i < count($segments); $i++) {
                    $result .= "['" . $segments[$i] . "']";
                }

                return $result;
            },
            $expr,
        );

        return $expr;
    }
}
