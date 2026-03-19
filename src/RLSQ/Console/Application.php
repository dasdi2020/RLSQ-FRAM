<?php

declare(strict_types=1);

namespace RLSQ\Console;

use RLSQ\Console\Command\Command;
use RLSQ\Console\Command\HelpCommand;
use RLSQ\Console\Command\ListCommand;
use RLSQ\Console\Input\ArgvInput;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Output\ConsoleOutput;
use RLSQ\Console\Output\OutputInterface;

class Application
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function __construct(
        private readonly string $name = 'RLSQ-FRAM',
        private readonly string $version = '0.1.0',
    ) {
        $this->addBuiltinCommands();
    }

    public function add(Command $command): void
    {
        $name = $command->getName();

        if ($name === null) {
            throw new \LogicException('La commande doit avoir un nom.');
        }

        $this->commands[$name] = $command;
    }

    public function get(string $name): Command
    {
        if (!isset($this->commands[$name])) {
            throw new \InvalidArgumentException(sprintf('La commande "%s" n\'existe pas.', $name));
        }

        return $this->commands[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * @return array<string, Command>
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * Exécute l'application.
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput();
        $output ??= new ConsoleOutput();

        $commandName = null;
        if ($input instanceof ArgvInput) {
            $commandName = $input->getFirstArgument();
        }

        if ($commandName === null || $commandName === '') {
            $commandName = 'list';
        }

        if (!$this->has($commandName)) {
            $output->writeln(sprintf('<error>Commande "%s" introuvable.</error>', $commandName));
            $output->writeln('');
            $commandName = 'list';
        }

        $command = $this->get($commandName);

        try {
            return $command->run($input, $output);
        } catch (\Throwable $e) {
            $output->writeln(sprintf('Erreur : %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    private function addBuiltinCommands(): void
    {
        $this->add(new ListCommand($this));
        $this->add(new HelpCommand($this));
    }
}
