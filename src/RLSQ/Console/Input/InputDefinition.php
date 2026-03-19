<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

class InputDefinition
{
    /** @var InputArgument[] */
    private array $arguments = [];

    /** @var InputOption[] */
    private array $options = [];

    /** @var array<string, string> shortcut → option name */
    private array $shortcuts = [];

    public function addArgument(InputArgument $argument): void
    {
        $this->arguments[$argument->getName()] = $argument;
    }

    public function getArgument(string $name): InputArgument
    {
        if (!isset($this->arguments[$name])) {
            throw new \InvalidArgumentException(sprintf('L\'argument "%s" n\'existe pas.', $name));
        }

        return $this->arguments[$name];
    }

    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    /**
     * @return InputArgument[]
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function addOption(InputOption $option): void
    {
        $this->options[$option->getName()] = $option;

        if ($option->getShortcut() !== null) {
            $this->shortcuts[$option->getShortcut()] = $option->getName();
        }
    }

    public function getOption(string $name): InputOption
    {
        if (!isset($this->options[$name])) {
            throw new \InvalidArgumentException(sprintf('L\'option "--%s" n\'existe pas.', $name));
        }

        return $this->options[$name];
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function hasShortcut(string $shortcut): bool
    {
        return isset($this->shortcuts[$shortcut]);
    }

    public function getOptionForShortcut(string $shortcut): InputOption
    {
        if (!isset($this->shortcuts[$shortcut])) {
            throw new \InvalidArgumentException(sprintf('Le raccourci "-%s" n\'existe pas.', $shortcut));
        }

        return $this->options[$this->shortcuts[$shortcut]];
    }

    /**
     * @return InputOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
