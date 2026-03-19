<?php

declare(strict_types=1);

namespace RLSQ\Console\Output;

interface OutputInterface
{
    public const VERBOSITY_QUIET = 0;
    public const VERBOSITY_NORMAL = 1;
    public const VERBOSITY_VERBOSE = 2;
    public const VERBOSITY_VERY_VERBOSE = 3;
    public const VERBOSITY_DEBUG = 4;

    public function write(string $message): void;

    public function writeln(string $message): void;

    public function getVerbosity(): int;

    public function setVerbosity(int $level): void;

    public function isQuiet(): bool;

    public function isVerbose(): bool;
}
