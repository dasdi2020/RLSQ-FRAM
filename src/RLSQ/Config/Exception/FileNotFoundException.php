<?php

declare(strict_types=1);

namespace RLSQ\Config\Exception;

class FileNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct(sprintf('Le fichier de configuration "%s" est introuvable.', $path), 0, $previous);
    }
}
