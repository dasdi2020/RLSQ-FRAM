<?php

declare(strict_types=1);

namespace Tests\Config;

use PHPUnit\Framework\TestCase;
use RLSQ\Config\Yaml\YamlParser;

class YamlParserTest extends TestCase
{
    private YamlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlParser();
    }

    // --- Types scalaires ---

    public function testScalarTypes(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/scalar_types.yaml');

        $this->assertSame('hello', $result['string_val']);
        $this->assertSame(42, $result['int_val']);
        $this->assertSame(-7, $result['negative_int']);
        $this->assertSame(3.14, $result['float_val']);
        $this->assertTrue($result['bool_true']);
        $this->assertFalse($result['bool_false']);
        $this->assertTrue($result['bool_yes']);
        $this->assertFalse($result['bool_no']);
        $this->assertNull($result['null_tilde']);
        $this->assertNull($result['null_word']);
        $this->assertSame('hello world', $result['quoted_string']);
        $this->assertSame('hello world', $result['single_quoted']);
    }

    public function testInlineCollections(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/scalar_types.yaml');

        $this->assertSame(['a', 'b', 'c'], $result['inline_list']);
        $this->assertSame(['key1' => 'val1', 'key2' => 'val2'], $result['inline_map']);
        $this->assertSame([], $result['empty_list']);
        $this->assertSame([], $result['empty_map']);
    }

    public function testCommentStripping(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/scalar_types.yaml');

        $this->assertSame('actual_value', $result['comment_value']);
    }

    // --- Config complexe ---

    public function testNestedMaps(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/config.yaml');

        $this->assertSame('my_secret_key', $result['framework']['secret']);
        $this->assertTrue($result['framework']['debug']);
        $this->assertSame('UTF-8', $result['framework']['charset']);

        $this->assertSame('localhost', $result['database']['host']);
        $this->assertSame(3306, $result['database']['port']);
    }

    public function testListValues(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/config.yaml');

        $this->assertCount(3, $result['allowed_hosts']);
        $this->assertSame('localhost', $result['allowed_hosts'][0]);
        $this->assertSame('example.com', $result['allowed_hosts'][1]);
        $this->assertSame('*.example.com', $result['allowed_hosts'][2]);
    }

    public function testDeeplyNested(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/config.yaml');

        $this->assertSame('user@example.com', $result['mailer']['credentials']['username']);
        $this->assertSame('secret123', $result['mailer']['credentials']['password']);
    }

    // --- Routes YAML ---

    public function testRoutesYaml(): void
    {
        $result = $this->parser->parseFile(__DIR__ . '/Fixtures/routes.yaml');

        $this->assertArrayHasKey('home', $result);
        $this->assertSame('/', $result['home']['path']);
        $this->assertSame('App\\Controller\\HomeController::index', $result['home']['controller']);

        $this->assertSame('/article/{id}', $result['article_show']['path']);
        $this->assertSame('\\d+', $result['article_show']['requirements']['id']);
    }

    // --- Parse string directe ---

    public function testParseString(): void
    {
        $yaml = <<<YAML
        name: RLSQ
        version: 1
        YAML;

        $result = $this->parser->parse($yaml);

        $this->assertSame('RLSQ', $result['name']);
        $this->assertSame(1, $result['version']);
    }

    public function testEmptyDocument(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame([], $result);
    }

    public function testCommentsOnly(): void
    {
        $result = $this->parser->parse("# just a comment\n# another one");

        $this->assertSame([], $result);
    }

    // --- Bloc littéral et plié ---

    public function testLiteralBlock(): void
    {
        $yaml = "description: |\n  line one\n  line two\n  line three";

        $result = $this->parser->parse($yaml);

        $this->assertSame("line one\nline two\nline three", $result['description']);
    }

    public function testFoldedBlock(): void
    {
        $yaml = "description: >\n  line one\n  line two\n  line three";

        $result = $this->parser->parse($yaml);

        $this->assertSame('line one line two line three', $result['description']);
    }
}
