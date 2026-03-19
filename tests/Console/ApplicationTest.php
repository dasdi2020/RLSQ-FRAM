<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use RLSQ\Console\Application;
use RLSQ\Console\Command\Command;
use RLSQ\Console\Input\ArgvInput;
use RLSQ\Console\Input\ArrayInput;
use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\BufferedOutput;
use RLSQ\Console\Output\OutputInterface;

class ApplicationTest extends TestCase
{
    public function testHasBuiltinCommands(): void
    {
        $app = new Application();

        $this->assertTrue($app->has('list'));
        $this->assertTrue($app->has('help'));
    }

    public function testListCommand(): void
    {
        $app = new Application('TestApp', '1.0');
        $app->add(new FooCommand());

        $output = new BufferedOutput();
        $input = new ArgvInput(['console', 'list']);
        $app->run($input, $output);

        $content = $output->getBuffer();

        $this->assertStringContainsString('TestApp 1.0', $content);
        $this->assertStringContainsString('foo', $content);
        $this->assertStringContainsString('list', $content);
        $this->assertStringContainsString('help', $content);
    }

    public function testRunCommand(): void
    {
        $app = new Application();
        $app->add(new FooCommand());

        $output = new BufferedOutput();
        $input = new ArgvInput(['console', 'foo']);
        $exitCode = $app->run($input, $output);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Foo executed', $output->getBuffer());
    }

    public function testRunDefaultsToList(): void
    {
        $app = new Application();

        $output = new BufferedOutput();
        $input = new ArgvInput(['console']);
        $app->run($input, $output);

        $this->assertStringContainsString('Commandes disponibles', $output->getBuffer());
    }

    public function testRunUnknownCommandShowsList(): void
    {
        $app = new Application();

        $output = new BufferedOutput();
        $input = new ArgvInput(['console', 'nonexistent']);
        $app->run($input, $output);

        $content = $output->getBuffer();
        $this->assertStringContainsString('introuvable', $content);
        $this->assertStringContainsString('Commandes disponibles', $content);
    }

    public function testHelpCommand(): void
    {
        $app = new Application();
        $app->add(new FooCommand());

        // Exécuter help directement avec le bon argument
        $output = new BufferedOutput();
        $helpCmd = $app->get('help');
        $helpCmd->run(new ArrayInput(['command_name' => 'foo']), $output);

        $content = $output->getBuffer();
        $this->assertStringContainsString('foo', $content);
        $this->assertStringContainsString('Une commande de test', $content);
    }

    public function testAddAndGet(): void
    {
        $app = new Application();
        $cmd = new FooCommand();

        $app->add($cmd);

        $this->assertTrue($app->has('foo'));
        $this->assertSame($cmd, $app->get('foo'));
    }

    public function testGetThrowsOnMissing(): void
    {
        $app = new Application();

        $this->expectException(\InvalidArgumentException::class);
        $app->get('nope');
    }

    public function testNameAndVersion(): void
    {
        $app = new Application('MyApp', '2.0');

        $this->assertSame('MyApp', $app->getName());
        $this->assertSame('2.0', $app->getVersion());
    }
}

class FooCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('foo');
        $this->setDescription('Une commande de test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Foo executed');

        return self::SUCCESS;
    }
}
