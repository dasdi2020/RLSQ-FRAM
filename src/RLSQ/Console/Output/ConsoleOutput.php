<?php

declare(strict_types=1);

namespace RLSQ\Console\Output;

class ConsoleOutput implements OutputInterface
{
    private int $verbosity;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    public function __construct(int $verbosity = self::VERBOSITY_NORMAL)
    {
        $this->verbosity = $verbosity;
        $this->stdout = fopen('php://stdout', 'w');
        $this->stderr = fopen('php://stderr', 'w');
    }

    public function write(string $message): void
    {
        if ($this->verbosity === self::VERBOSITY_QUIET) {
            return;
        }

        fwrite($this->stdout, $message);
    }

    public function writeln(string $message): void
    {
        $this->write($message . PHP_EOL);
    }

    public function writeError(string $message): void
    {
        fwrite($this->stderr, $message . PHP_EOL);
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
