<?php

declare(strict_types=1);

namespace RLSQ\Profiler;

use RLSQ\HttpFoundation\Request;
use RLSQ\HttpFoundation\Response;

interface DataCollectorInterface
{
    /**
     * Collecte les données pour cette requête.
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void;

    /**
     * Nom unique du collector (utilisé comme clé).
     */
    public function getName(): string;

    /**
     * Données collectées.
     */
    public function getData(): array;
}
