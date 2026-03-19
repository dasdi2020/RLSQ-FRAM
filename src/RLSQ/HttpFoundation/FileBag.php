<?php

declare(strict_types=1);

namespace RLSQ\HttpFoundation;

class FileBag
{
    /** @var array<string, UploadedFile|array<UploadedFile>> */
    private array $files = [];

    public function __construct(array $files = [])
    {
        $this->replace($files);
    }

    public function replace(array $files): void
    {
        $this->files = [];

        foreach ($files as $key => $file) {
            $this->set($key, $file);
        }
    }

    public function set(string $key, UploadedFile|array $file): void
    {
        if (is_array($file) && isset($file['tmp_name'])) {
            $file = $this->convertFromPhpFiles($file);
        }

        $this->files[$key] = $file;
    }

    public function get(string $key): UploadedFile|array|null
    {
        return $this->files[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->files[$key]);
    }

    public function all(): array
    {
        return $this->files;
    }

    /**
     * Convertit la structure $_FILES de PHP en UploadedFile.
     */
    private function convertFromPhpFiles(array $file): UploadedFile|array
    {
        // Upload simple
        if (!is_array($file['tmp_name'])) {
            return new UploadedFile(
                $file['tmp_name'],
                $file['name'],
                $file['type'] ?? null,
                $file['error'] ?? UPLOAD_ERR_OK,
            );
        }

        // Upload multiple
        $files = [];
        foreach ($file['tmp_name'] as $index => $tmpName) {
            $files[$index] = new UploadedFile(
                $tmpName,
                $file['name'][$index],
                $file['type'][$index] ?? null,
                $file['error'][$index] ?? UPLOAD_ERR_OK,
            );
        }

        return $files;
    }
}
