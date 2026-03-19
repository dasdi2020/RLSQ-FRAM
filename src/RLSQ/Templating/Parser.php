<?php

declare(strict_types=1);

namespace RLSQ\Templating;

use RLSQ\Templating\Node\BlockNode;
use RLSQ\Templating\Node\ExtendsNode;
use RLSQ\Templating\Node\ForNode;
use RLSQ\Templating\Node\IfNode;
use RLSQ\Templating\Node\IncludeNode;
use RLSQ\Templating\Node\NodeInterface;
use RLSQ\Templating\Node\PrintNode;
use RLSQ\Templating\Node\TextNode;

/**
 * Transforme les tokens en AST (arbre de nœuds).
 */
class Parser
{
    /** @var Token[] */
    private array $tokens;
    private int $cursor;

    /**
     * @return NodeInterface[]
     */
    public function parse(array $tokens): array
    {
        $this->tokens = $tokens;
        $this->cursor = 0;

        return $this->parseBody();
    }

    /**
     * @param string[] $until Tags qui terminent ce bloc
     * @return NodeInterface[]
     */
    private function parseBody(array $until = []): array
    {
        $nodes = [];

        while ($this->cursor < count($this->tokens)) {
            $token = $this->tokens[$this->cursor];

            if ($token->type === Token::TEXT) {
                $nodes[] = new TextNode($token->value);
                $this->cursor++;
                continue;
            }

            if ($token->type === Token::PRINT) {
                $nodes[] = new PrintNode($token->value);
                $this->cursor++;
                continue;
            }

            if ($token->type === Token::COMMENT) {
                $this->cursor++;
                continue;
            }

            if ($token->type === Token::TAG_OPEN) {
                $tag = $this->getTagName($token->value);

                // Vérifier si c'est un tag de fin
                if (in_array($tag, $until, true)) {
                    return $nodes;
                }

                $node = $this->parseTag($token);
                if ($node !== null) {
                    $nodes[] = $node;
                }
                continue;
            }

            $this->cursor++;
        }

        return $nodes;
    }

    private function parseTag(Token $token): ?NodeInterface
    {
        $value = $token->value;
        $tag = $this->getTagName($value);

        return match ($tag) {
            'extends' => $this->parseExtends($value),
            'block' => $this->parseBlock($value),
            'if' => $this->parseIf($value),
            'for' => $this->parseFor($value),
            'include' => $this->parseInclude($value),
            default => throw new \RuntimeException(sprintf('Tag inconnu "%%%s%%" à la ligne %d.', $tag, $token->line)),
        };
    }

    private function parseExtends(string $value): ExtendsNode
    {
        // extends "base.html"
        $parent = trim(substr($value, 7));
        $parent = trim($parent, '"\'');
        $this->cursor++;

        return new ExtendsNode($parent);
    }

    private function parseBlock(string $value): BlockNode
    {
        // block content
        $name = trim(substr($value, 5));
        $this->cursor++;

        $body = $this->parseBody(['endblock']);
        $this->cursor++; // skip endblock

        return new BlockNode($name, $body);
    }

    private function parseIf(string $value): IfNode
    {
        // if condition
        $condition = trim(substr($value, 2));
        $this->cursor++;

        $branches = [];

        // Parse le body du if
        $body = $this->parseBody(['elseif', 'else', 'endif']);
        $branches[] = ['condition' => $condition, 'body' => $body];

        // Boucler sur les elseif/else
        while ($this->cursor < count($this->tokens)) {
            $tag = $this->getTagName($this->tokens[$this->cursor]->value);

            if ($tag === 'endif') {
                $this->cursor++;
                break;
            }

            if ($tag === 'elseif') {
                $cond = trim(substr($this->tokens[$this->cursor]->value, 6));
                $this->cursor++;
                $body = $this->parseBody(['elseif', 'else', 'endif']);
                $branches[] = ['condition' => $cond, 'body' => $body];
                continue;
            }

            if ($tag === 'else') {
                $this->cursor++;
                $body = $this->parseBody(['endif']);
                $branches[] = ['condition' => null, 'body' => $body];
                $this->cursor++; // skip endif
                break;
            }
        }

        return new IfNode($branches);
    }

    private function parseFor(string $value): ForNode
    {
        // for item in items
        // for key, item in items
        $expr = trim(substr($value, 3));

        $keyName = null;
        $valueName = null;
        $iterable = null;

        if (preg_match('/^(\w+)\s*,\s*(\w+)\s+in\s+(.+)$/', $expr, $m)) {
            $keyName = $m[1];
            $valueName = $m[2];
            $iterable = $m[3];
        } elseif (preg_match('/^(\w+)\s+in\s+(.+)$/', $expr, $m)) {
            $valueName = $m[1];
            $iterable = $m[2];
        } else {
            throw new \RuntimeException(sprintf('Syntaxe for invalide : "%s".', $value));
        }

        $this->cursor++;

        $body = $this->parseBody(['else', 'endfor']);
        $elseBody = [];

        if ($this->cursor < count($this->tokens) && $this->getTagName($this->tokens[$this->cursor]->value) === 'else') {
            $this->cursor++;
            $elseBody = $this->parseBody(['endfor']);
        }

        $this->cursor++; // skip endfor

        return new ForNode($valueName, $keyName, trim($iterable), $body, $elseBody);
    }

    private function parseInclude(string $value): IncludeNode
    {
        // include "partial.html"
        $template = trim(substr($value, 7));
        $template = trim($template, '"\'');
        $this->cursor++;

        return new IncludeNode($template);
    }

    private function getTagName(string $value): string
    {
        $parts = preg_split('/\s+/', trim($value), 2);

        return $parts[0] ?? '';
    }
}
