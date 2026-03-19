<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class ServerBag extends ParameterBag
{
    /**
     * Extrait les headers HTTP depuis les variables serveur.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->parameters as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
