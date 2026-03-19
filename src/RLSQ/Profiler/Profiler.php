<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

class Profiler
{
    /** @var DataCollectorInterface[] */
    private array $collectors = [];

    private float $startTime;
    private float $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }

    public function addCollector(DataCollectorInterface $collector): void
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        foreach ($this->collectors as $collector) {
            $collector->collect($request, $response, $exception);
        }
    }

    public function getCollector(string $name): ?DataCollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * @return DataCollectorInterface[]
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function getDuration(): float
    {
        return (microtime(true) - $this->startTime) * 1000; // ms
    }

    public function getMemoryUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    public function getMemoryDelta(): int
    {
        return memory_get_usage() - (int) $this->startMemory;
    }
}
