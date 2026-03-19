<?php

declare(strict_types=1);

namespace RLSQ\Profiler\Collector;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;
use RLSQ\Profiler\DataCollectorInterface;
use RLSQ\Profiler\Profiler;

class PerformanceCollector implements DataCollectorInterface
{
    private array $data = [];

    public function __construct(
        private readonly Profiler $profiler,
    ) {}

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'duration_ms' => round($this->profiler->getDuration(), 2),
            'memory_peak' => $this->profiler->getMemoryUsage(),
            'memory_peak_formatted' => $this->formatBytes($this->profiler->getMemoryUsage()),
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'framework' => 'RLSQ-FRAM',
        ];
    }

    public function getName(): string
    {
        return 'performance';
    }

    public function getData(): array
    {
        return $this->data;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }
}
