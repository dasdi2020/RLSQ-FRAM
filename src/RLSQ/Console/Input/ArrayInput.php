<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

/**
 * Input basé sur un tableau. Utile pour les tests.
 */
class ArrayInput implements InputInterface
{
    private array $arguments = [];
    private array $options = [];

    /**
     * @param array $parameters ['argument' => 'value', '--option' => 'value', '-v' => true]
     */
    public function __construct(
        private readonly array $parameters = [],
    ) {}

    public function bind(InputDefinition $definition): void
    {
        // Defaults
        foreach ($definition->getArguments() as $arg) {
            $this->arguments[$arg->getName()] = $arg->getDefault();
        }
        foreach ($definition->getOptions() as $opt) {
            $this->options[$opt->getName()] = $opt->getDefault();
        }

        foreach ($this->parameters as $key => $value) {
            if (str_starts_with($key, '--')) {
                $name = substr($key, 2);
                $this->options[$name] = $value;
            } elseif (str_starts_with($key, '-')) {
                $shortcut = substr($key, 1);
                $option = $definition->getOptionForShortcut($shortcut);
                $this->options[$option->getName()] = $value;
            } else {
                $this->arguments[$key] = $value;
            }
        }
    }

    public function getArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }
}
