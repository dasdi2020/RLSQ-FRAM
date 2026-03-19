<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use RLSQ\Console\Command\Command;
use RLSQ\Console\Input\ArrayInput;
use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\BufferedOutput;
use RLSQ\Console\Output\OutputInterface;

class CommandTest extends TestCase
{
    public function testBasicCommand(): void
    {
        $command = new GreetCommand();
        $output = new BufferedOutput();

        $exitCode = $command->run(
            new ArrayInput(['name' => 'Alice']),
            $output,
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Hello Alice', $output->getBuffer());
    }

    public function testCommandWithOption(): void
    {
        $command = new GreetCommand();
        $output = new BufferedOutput();

        $command->run(
            new ArrayInput(['name' => 'Bob', '--yell' => true]),
            $output,
        );

        $this->assertStringContainsString('HELLO BOB', $output->getBuffer());
    }

    public function testCommandDefaultArgument(): void
    {
        $command = new GreetCommand();
        $output = new BufferedOutput();

        $command->run(new ArrayInput([]), $output);

        $this->assertStringContainsString('Hello World', $output->getBuffer());
    }

    public function testCommandMetadata(): void
    {
        $command = new GreetCommand();

        $this->assertSame('greet', $command->getName());
        $this->assertSame('Salue quelqu\'un', $command->getDescription());
    }
}

class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('greet');
        $this->setDescription('Salue quelqu\'un');
        $this->addArgument('name', InputArgument::OPTIONAL, 'Nom de la personne', 'World');
        $this->addOption('yell', 'y', InputOption::VALUE_NONE, 'Crier en majuscules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $message = 'Hello ' . $name;

        if ($input->getOption('yell')) {
            $message = strtoupper($message);
        }

        $output->writeln($message);

        return self::SUCCESS;
    }
}
