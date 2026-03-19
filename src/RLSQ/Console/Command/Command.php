<?php

declare(strict_types=1);

namespace RLSQ\Console\Command;

use RLSQ\Console\Input\InputArgument;
use RLSQ\Console\Input\InputDefinition;
use RLSQ\Console\Input\InputInterface;
use RLSQ\Console\Input\InputOption;
use RLSQ\Console\Output\OutputInterface;

abstract class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    private ?string $name = null;
    private string $description = '';
    private InputDefinition $definition;
    private ?string $help = null;

    public function __construct(?string $name = null)
    {
        $this->definition = new InputDefinition();

        if ($name !== null) {
            $this->name = $name;
        }

        $this->configure();
    }

    /**
     * Configure la commande : nom, description, arguments, options.
     */
    protected function configure(): void
    {
        // À surcharger
    }

    /**
     * Exécute la commande.
     */
    abstract protected function execute(InputInterface $input, OutputInterface $output): int;

    /**
     * Appelé par Application pour exécuter la commande.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $input->bind($this->definition);

        return $this->execute($input, $output);
    }

    // --- Configuration helpers ---

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setHelp(string $help): static
    {
        $this->help = $help;
        return $this;
    }

    public function getHelp(): string
    {
        return $this->help ?? $this->description;
    }

    public function addArgument(string $name, int $mode = InputArgument::OPTIONAL, string $description = '', mixed $default = null): static
    {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));
        return $this;
    }

    public function addOption(string $name, ?string $shortcut = null, int $mode = InputOption::VALUE_NONE, string $description = '', mixed $default = null): static
    {
        $this->definition->addOption(new InputOption($name, $shortcut, $mode, $description, $default));
        return $this;
    }

    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }
}
