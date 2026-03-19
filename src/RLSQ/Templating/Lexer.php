<?php

declare(strict_types=1);

namespace RLSQ\Templating;

/**
 * Tokenise un template en tokens TEXT, PRINT ({{ }}), TAG_OPEN ({% %}), COMMENT ({# #}).
 */
class Lexer
{
    /**
     * @return Token[]
     */
    public function tokenize(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        $cursor = 0;
        $line = 1;

        while ($cursor < $length) {
            // Chercher la prochaine balise
            $nextPos = $this->findNextTag($source, $cursor);

            if ($nextPos === false) {
                // Plus de balises, tout le reste est du texte
                $text = substr($source, $cursor);
                if ($text !== '') {
                    $tokens[] = new Token(Token::TEXT, $text, $line);
                    $line += substr_count($text, "\n");
                }
                break;
            }

            // Texte avant la balise
            if ($nextPos > $cursor) {
                $text = substr($source, $cursor, $nextPos - $cursor);
                $tokens[] = new Token(Token::TEXT, $text, $line);
                $line += substr_count($text, "\n");
            }

            $cursor = $nextPos;
            $tag = $source[$cursor] . $source[$cursor + 1];

            // Commentaire {# ... #}
            if ($tag === '{#') {
                $end = strpos($source, '#}', $cursor + 2);
                if ($end === false) {
                    throw new \RuntimeException(sprintf('Commentaire non fermé à la ligne %d.', $line));
                }
                $content = substr($source, $cursor + 2, $end - $cursor - 2);
                $tokens[] = new Token(Token::COMMENT, trim($content), $line);
                $line += substr_count($content, "\n");
                $cursor = $end + 2;
                continue;
            }

            // Print {{ ... }}
            if ($tag === '{{') {
                $end = strpos($source, '}}', $cursor + 2);
                if ($end === false) {
                    throw new \RuntimeException(sprintf('Expression {{ non fermée à la ligne %d.', $line));
                }
                $content = substr($source, $cursor + 2, $end - $cursor - 2);
                $tokens[] = new Token(Token::PRINT, trim($content), $line);
                $line += substr_count($content, "\n");
                $cursor = $end + 2;
                continue;
            }

            // Tag {% ... %}
            if ($tag === '{%') {
                $end = strpos($source, '%}', $cursor + 2);
                if ($end === false) {
                    throw new \RuntimeException(sprintf('Tag {%% non fermé à la ligne %d.', $line));
                }
                $content = substr($source, $cursor + 2, $end - $cursor - 2);
                $tokens[] = new Token(Token::TAG_OPEN, trim($content), $line);
                $line += substr_count($content, "\n");
                $cursor = $end + 2;
                continue;
            }

            // Si on arrive ici, c'est un faux positif (juste un { isolé)
            $tokens[] = new Token(Token::TEXT, $tag, $line);
            $cursor += 2;
        }

        return $tokens;
    }

    /**
     * Trouve la position de la prochaine balise {{ , {% ou {#.
     */
    private function findNextTag(string $source, int $offset): int|false
    {
        $positions = [];

        foreach (['{{', '{%', '{#'] as $tag) {
            $pos = strpos($source, $tag, $offset);
            if ($pos !== false) {
                $positions[] = $pos;
            }
        }

        return empty($positions) ? false : min($positions);
    }
}
