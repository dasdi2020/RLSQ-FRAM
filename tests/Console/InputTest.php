<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use RLSQ\Console\Input\ArgvInput;
use RLSQ\Console\Input\ArrayInput;
use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputDefinition;
use RLSQ\Console\Input\InputOption;

class InputTest extends TestCase
{
    // --- ArgvInput ---

    public function testArgvInputParseArguments(): void
    {
        $input = new ArgvInput(['bin/console', 'greet', 'Alice']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('command_name'));
        $def->addArgument(new InputArgument('name'));

        $input->bind($def);

        $this->assertSame('greet', $input->getArgument('command_name'));
        $this->assertSame('Alice', $input->getArgument('name'));
    }

    public function testArgvInputParseLongOption(): void
    {
        $input = new ArgvInput(['bin/console', 'greet', '--yell']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('command_name'));
        $def->addOption(new InputOption('yell'));

        $input->bind($def);

        $this->assertTrue($input->getOption('yell'));
    }

    public function testArgvInputParseLongOptionWithValue(): void
    {
        $input = new ArgvInput(['bin/console', 'greet', '--name=Bob']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('command_name'));
        $def->addOption(new InputOption('name', null, InputOption::VALUE_REQUIRED));

        $input->bind($def);

        $this->assertSame('Bob', $input->getOption('name'));
    }

    public function testArgvInputParseLongOptionWithSeparateValue(): void
    {
        $input = new ArgvInput(['bin/console', 'greet', '--name', 'Charlie']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('command_name'));
        $def->addOption(new InputOption('name', null, InputOption::VALUE_REQUIRED));

        $input->bind($def);

        $this->assertSame('Charlie', $input->getOption('name'));
    }

    public function testArgvInputParseShortOption(): void
    {
        $input = new ArgvInput(['bin/console', 'greet', '-v']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('command_name'));
        $def->addOption(new InputOption('verbose', 'v'));

        $input->bind($def);

        $this->assertTrue($input->getOption('verbose'));
    }

    public function testArgvInputRequiredArgThrows(): void
    {
        $input = new ArgvInput(['bin/console']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('name', InputArgument::REQUIRED));

        $this->expectException(\RuntimeException::class);
        $input->bind($def);
    }

    public function testArgvInputDefaults(): void
    {
        $input = new ArgvInput(['bin/console']);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('name', InputArgument::OPTIONAL, '', 'World'));
        $def->addOption(new InputOption('count', 'c', InputOption::VALUE_OPTIONAL, '', '1'));

        $input->bind($def);

        $this->assertSame('World', $input->getArgument('name'));
        $this->assertSame('1', $input->getOption('count'));
    }

    public function testArgvGetFirstArgument(): void
    {
        $input = new ArgvInput(['bin/console', 'make:controller', '--force']);

        $this->assertSame('make:controller', $input->getFirstArgument());
    }

    public function testArgvGetFirstArgumentSkipsOptions(): void
    {
        $input = new ArgvInput(['bin/console', '--verbose', 'list']);

        $this->assertSame('list', $input->getFirstArgument());
    }

    // --- ArrayInput ---

    public function testArrayInput(): void
    {
        $input = new ArrayInput([
            'name' => 'Alice',
            '--yell' => true,
            '-c' => '3',
        ]);

        $def = new InputDefinition();
        $def->addArgument(new InputArgument('name'));
        $def->addOption(new InputOption('yell'));
        $def->addOption(new InputOption('count', 'c', InputOption::VALUE_REQUIRED));

        $input->bind($def);

        $this->assertSame('Alice', $input->getArgument('name'));
        $this->assertTrue($input->getOption('yell'));
        $this->assertSame('3', $input->getOption('count'));
    }
}
