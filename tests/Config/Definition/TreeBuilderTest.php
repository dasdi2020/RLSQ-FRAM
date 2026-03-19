<?php

declare(strict_types=1);

namespace Tests\Config\Definition;

use PHPUnit\Framework\TestCase;
use RLSQ\Config\Definition\ArrayNode;
use RLSQ\Config\Definition\ConfigurationInterface;
use RLSQ\Config\Definition\Processor;
use RLSQ\Config\Definition\ScalarNode;
use RLSQ\Config\Definition\TreeBuilder;
use RLSQ\Config\Exception\ParseException;

class TreeBuilderTest extends TestCase
{
    // --- Scalar node ---

    public function testScalarDefault(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('debug'))->defaultValue(false));

        $result = $tree->process([]);

        $this->assertFalse($result['debug']);
    }

    public function testScalarOverridesDefault(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('debug'))->defaultValue(false));

        $result = $tree->process(['debug' => true]);

        $this->assertTrue($result['debug']);
    }

    public function testScalarRequired(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('secret'))->isRequired());

        $this->expectException(ParseException::class);
        $tree->process([]);
    }

    public function testScalarRequiredProvided(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('secret'))->isRequired());

        $result = $tree->process(['secret' => 'abc']);

        $this->assertSame('abc', $result['secret']);
    }

    public function testScalarAllowedValues(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('env'))->allowedValues(['dev', 'prod', 'test']));

        $result = $tree->process(['env' => 'prod']);
        $this->assertSame('prod', $result['env']);

        $this->expectException(ParseException::class);
        $tree->process(['env' => 'staging']);
    }

    public function testScalarTypeValidation(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('port'))->type('int'));

        $result = $tree->process(['port' => 3306]);
        $this->assertSame(3306, $result['port']);

        $this->expectException(ParseException::class);
        $tree->process(['port' => 'abc']);
    }

    // --- Array node ---

    public function testNestedArrayNode(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild(
                (new ArrayNode('database'))
                    ->addChild((new ScalarNode('host'))->defaultValue('localhost'))
                    ->addChild((new ScalarNode('port'))->defaultValue(3306))
                    ->addChild((new ScalarNode('name'))->isRequired())
            );

        $result = $tree->process([
            'database' => ['name' => 'rlsq_db'],
        ]);

        $this->assertSame('localhost', $result['database']['host']);
        $this->assertSame(3306, $result['database']['port']);
        $this->assertSame('rlsq_db', $result['database']['name']);
    }

    public function testUnknownKeysPreserved(): void
    {
        $tree = new TreeBuilder('root');
        $tree->getRootNode()
            ->addChild((new ScalarNode('known'))->defaultValue('x'));

        $result = $tree->process(['known' => 'y', 'unknown' => 'z']);

        $this->assertSame('y', $result['known']);
        $this->assertSame('z', $result['unknown']);
    }

    // --- Processor avec fusion ---

    public function testProcessorMergesConfigs(): void
    {
        $config = new FrameworkConfiguration();
        $processor = new Processor();

        $result = $processor->processConfiguration($config, [
            ['debug' => false, 'charset' => 'ISO-8859-1'],
            ['debug' => true], // override
        ]);

        $this->assertTrue($result['debug']);
        $this->assertSame('ISO-8859-1', $result['charset']);
        $this->assertSame('change_me', $result['secret']); // default
    }

    public function testProcessorDeepMerge(): void
    {
        $config = new FrameworkConfiguration();
        $processor = new Processor();

        $result = $processor->processConfiguration($config, [
            ['database' => ['host' => '10.0.0.1']],
            ['database' => ['port' => 5432]],
        ]);

        $this->assertSame('10.0.0.1', $result['database']['host']);
        $this->assertSame(5432, $result['database']['port']);
    }
}

// --- Fixture ---

class FrameworkConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('framework');
        $root = $tree->getRootNode();

        $root->addChild((new ScalarNode('secret'))->defaultValue('change_me'));
        $root->addChild((new ScalarNode('debug'))->defaultValue(false));
        $root->addChild((new ScalarNode('charset'))->defaultValue('UTF-8'));
        $root->addChild(
            (new ArrayNode('database'))
                ->addChild((new ScalarNode('host'))->defaultValue('localhost'))
                ->addChild((new ScalarNode('port'))->defaultValue(3306))
        );

        return $tree;
    }
}
