<?php

declare(strict_types=1);

namespace RLSQ\Console\Input;

class ArgvInput implements InputInterface
{
    private array $tokens;
    private array $arguments = [];
    private array $options = [];

    /**
     * @param string[]|null $argv Si null, utilise $_SERVER['argv']
     */
    public function __construct(?array $argv = null)
    {
        $argv ??= $_SERVER['argv'] ?? [];

        // Le premier élément est le script, le retirer
        array_shift($argv);

        $this->tokens = $argv;
    }

    public function bind(InputDefinition $definition): void
    {
        // Initialiser les defaults
        foreach ($definition->getArguments() as $arg) {
            $this->arguments[$arg->getName()] = $arg->getDefault();
        }
        foreach ($definition->getOptions() as $opt) {
            $this->options[$opt->getName()] = $opt->getDefault();
        }

        $argIndex = 0;
        $argDefs = array_values($definition->getArguments());
        $i = 0;

        while ($i < count($this->tokens)) {
            $token = $this->tokens[$i];

            // Option longue : --name ou --name=value
            if (str_starts_with($token, '--')) {
                $this->parseLongOption($token, $definition, $i);
                $i++;
                continue;
            }

            // Option courte : -v ou -v value
            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $this->parseShortOption($token, $definition, $i);
                $i++;
                continue;
            }

            // Argument positionnel
            if ($argIndex < count($argDefs)) {
                $this->arguments[$argDefs[$argIndex]->getName()] = $token;
                $argIndex++;
            }

            $i++;
        }

        // Valider les arguments requis
        foreach ($definition->getArguments() as $arg) {
            if ($arg->isRequired() && $this->arguments[$arg->getName()] === null) {
                throw new \RuntimeException(sprintf('L\'argument "%s" est requis.', $arg->getName()));
            }
        }
    }

    private function parseLongOption(string $token, InputDefinition $definition, int &$i): void
    {
        $token = substr($token, 2);

        if (str_contains($token, '=')) {
            [$name, $value] = explode('=', $token, 2);
        } else {
            $name = $token;
            $value = null;
        }

        if (!$definition->hasOption($name)) {
            throw new \RuntimeException(sprintf('L\'option "--%s" n\'existe pas.', $name));
        }

        $option = $definition->getOption($name);

        if (!$option->acceptsValue()) {
            $this->options[$name] = true;
        } elseif ($value !== null) {
            $this->options[$name] = $value;
        } elseif (isset($this->tokens[$i + 1]) && !str_starts_with($this->tokens[$i + 1], '-')) {
            $i++;
            $this->options[$name] = $this->tokens[$i];
        } elseif ($option->isValueRequired()) {
            throw new \RuntimeException(sprintf('L\'option "--%s" nécessite une valeur.', $name));
        } else {
            $this->options[$name] = $option->getDefault();
        }
    }

    private function parseShortOption(string $token, InputDefinition $definition, int &$i): void
    {
        $shortcut = substr($token, 1);

        if (!$definition->hasShortcut($shortcut)) {
            throw new \RuntimeException(sprintf('Le raccourci "-%s" n\'existe pas.', $shortcut));
        }

        $option = $definition->getOptionForShortcut($shortcut);
        $name = $option->getName();

        if (!$option->acceptsValue()) {
            $this->options[$name] = true;
        } elseif (isset($this->tokens[$i + 1]) && !str_starts_with($this->tokens[$i + 1], '-')) {
            $i++;
            $this->options[$name] = $this->tokens[$i];
        } elseif ($option->isValueRequired()) {
            throw new \RuntimeException(sprintf('L\'option "-%s" nécessite une valeur.', $shortcut));
        } else {
            $this->options[$name] = $option->getDefault();
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

    /**
     * Retourne le premier token (nom de commande).
     */
    public function getFirstArgument(): ?string
    {
        foreach ($this->tokens as $token) {
            if (!str_starts_with($token, '-')) {
                return $token;
            }
        }

        return null;
    }
}
