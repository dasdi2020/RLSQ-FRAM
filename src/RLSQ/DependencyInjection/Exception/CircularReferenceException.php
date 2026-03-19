<?php

declare(strict_types=1);

namespace RLSQ\DependencyInjection\Exception;

class CircularReferenceException extends \LogicException
{
    public function __construct(string $id, array $path, ?\Throwable $previous = null)
    {
        $pathStr = implode(' -> ', [...$path, $id]);
        parent::__construct(sprintf('Référence circulaire détectée : %s.', $pathStr), 0, $previous);
    }
}
