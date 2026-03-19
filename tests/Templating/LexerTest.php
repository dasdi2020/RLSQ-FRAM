<?php

declare(strict_types=1);

namespace Tests\Templating;

use PHPUnit\Framework\TestCase;
use RLSQ\Templating\Lexer;
use RLSQ\Templating\Token;

class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testPlainText(): void
    {
        $tokens = $this->lexer->tokenize('Hello world');

        $this->assertCount(1, $tokens);
        $this->assertSame(Token::TEXT, $tokens[0]->type);
        $this->assertSame('Hello world', $tokens[0]->value);
    }

    public function testPrintTag(): void
    {
        $tokens = $this->lexer->tokenize('Hello {{ name }}!');

        $this->assertCount(3, $tokens);
        $this->assertSame(Token::TEXT, $tokens[0]->type);
        $this->assertSame(Token::PRINT, $tokens[1]->type);
        $this->assertSame('name', $tokens[1]->value);
        $this->assertSame(Token::TEXT, $tokens[2]->type);
    }

    public function testBlockTag(): void
    {
        $tokens = $this->lexer->tokenize('{% if true %}yes{% endif %}');

        $this->assertCount(3, $tokens);
        $this->assertSame(Token::TAG_OPEN, $tokens[0]->type);
        $this->assertSame('if true', $tokens[0]->value);
        $this->assertSame(Token::TEXT, $tokens[1]->type);
        $this->assertSame(Token::TAG_OPEN, $tokens[2]->type);
        $this->assertSame('endif', $tokens[2]->value);
    }

    public function testComment(): void
    {
        $tokens = $this->lexer->tokenize('A{# hidden #}B');

        $this->assertCount(3, $tokens);
        $this->assertSame(Token::TEXT, $tokens[0]->type);
        $this->assertSame(Token::COMMENT, $tokens[1]->type);
        $this->assertSame('hidden', $tokens[1]->value);
        $this->assertSame(Token::TEXT, $tokens[2]->type);
    }

    public function testMultipleTags(): void
    {
        $tokens = $this->lexer->tokenize('{% for x in list %}{{ x }}{% endfor %}');

        $this->assertCount(3, $tokens);
        $this->assertSame(Token::TAG_OPEN, $tokens[0]->type);
        $this->assertSame(Token::PRINT, $tokens[1]->type);
        $this->assertSame(Token::TAG_OPEN, $tokens[2]->type);
    }

    public function testLineTracking(): void
    {
        $tokens = $this->lexer->tokenize("line1\n{{ var }}\nline3");

        $this->assertSame(1, $tokens[0]->line);
        $this->assertSame(2, $tokens[1]->line);
        $this->assertSame(2, $tokens[2]->line);
    }

    public function testUnclosedPrintThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->lexer->tokenize('{{ unclosed');
    }

    public function testUnclosedTagThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->lexer->tokenize('{% unclosed');
    }

    public function testUnclosedCommentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->lexer->tokenize('{# unclosed');
    }
}
