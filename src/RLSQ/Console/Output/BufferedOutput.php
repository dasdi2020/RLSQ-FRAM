<?php

declare(strict_types=1);

namespace RLSQ\Console\Output;

/**
 * Capture la sortie dans un buffer. Utile pour les tests.
 */
class BufferedOutput implements OutputInterface
{
    private string $buffer = '';
    private int $verbosity;

    public function __construct(int $verbosity = self::VERBOSITY_NORMAL)
    {
        $this->verbosity = $verbosity;
    }

    public function write(string $message): void
    {
        if ($this->verbosity === self::VERBOSITY_QUIET) {
            return;
        }

        $this->buffer .= $message;
    }

    public function writeln(string $message): void
    {
        $this->write($message . PHP_EOL);
    }

    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';

        return $content;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function isQuiet(): bool
    {
        return $this->verbosity === self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }
}
