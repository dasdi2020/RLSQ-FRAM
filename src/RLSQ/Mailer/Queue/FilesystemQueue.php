<?php

declare(strict_types=1);

namespace RLSQ\Mailer\Queue;

use RLSQ\Mailer\Email;

/**
 * Queue basée sur le filesystem. Chaque email = un fichier JSON.
 */
class FilesystemQueue implements QueueInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function enqueue(Email $email): void
    {
        $filename = sprintf(
            '%s_%d_%s.json',
            $email->getCreatedAt()->format('Ymd_His'),
            $email->getPriority(),
            $email->getId(),
        );

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($path, json_encode($email->serialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function dequeue(): ?Email
    {
        $files = $this->getFiles();

        if (empty($files)) {
            return null;
        }

        // Trier par priorité (chiffre plus bas = plus prioritaire) puis par date
        sort($files);
        $file = $files[0];

        $data = json_decode(file_get_contents($file), true);
        unlink($file);

        return Email::fromArray($data);
    }

    public function count(): int
    {
        return count($this->getFiles());
    }

    public function peek(int $limit = 10): array
    {
        $files = $this->getFiles();
        sort($files);

        $emails = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $data = json_decode(file_get_contents($file), true);
            $emails[] = Email::fromArray($data);
        }

        return $emails;
    }

    public function clear(): void
    {
        foreach ($this->getFiles() as $file) {
            unlink($file);
        }
    }

    /**
     * @return string[]
     */
    private function getFiles(): array
    {
        return glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
    }
}
