<?php

declare(strict_types=1);

namespace App\Media;

use RLSQ\Database\Connection;

class MediaService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $uploadDir,
    ) {
        $this->ensureTable();
    }

    public function upload(array $file, ?int $folderId = null, ?int $uploadedBy = null): array
    {
        $originalName = $file['name'] ?? 'unnamed';
        $tmpPath = $file['tmp_name'] ?? '';
        $mimeType = $file['type'] ?? mime_content_type($tmpPath) ?: 'application/octet-stream';
        $size = $file['size'] ?? filesize($tmpPath);

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
        $subDir = date('Y/m');
        $storagePath = "{$subDir}/{$filename}";

        $fullDir = "{$this->uploadDir}/{$subDir}";
        if (!is_dir($fullDir)) { mkdir($fullDir, 0777, true); }

        if (!empty($tmpPath) && file_exists($tmpPath)) {
            copy($tmpPath, "{$this->uploadDir}/{$storagePath}");
        }

        $this->connection->execute(
            'INSERT INTO media_files (filename, original_name, mime_type, size, storage_path, folder_id, uploaded_by) VALUES (:f, :on, :mt, :s, :sp, :fid, :ub)',
            ['f' => $filename, 'on' => $originalName, 'mt' => $mimeType, 's' => $size, 'sp' => $storagePath, 'fid' => $folderId, 'ub' => $uploadedBy],
        );

        return $this->getFile((int) $this->connection->lastInsertId());
    }

    public function getFile(int $id): ?array
    {
        return $this->connection->fetchOne('SELECT * FROM media_files WHERE id = :id', ['id' => $id]) ?: null;
    }

    /** @return array[] */
    public function listFiles(?int $folderId = null, int $page = 1, int $perPage = 30): array
    {
        $where = $folderId !== null ? 'WHERE folder_id = :fid' : 'WHERE folder_id IS NULL';
        $params = $folderId !== null ? ['fid' => $folderId] : [];
        $offset = ($page - 1) * $perPage;

        return $this->connection->fetchAll(
            "SELECT * FROM media_files {$where} ORDER BY created_at DESC LIMIT :l OFFSET :o",
            array_merge($params, ['l' => $perPage, 'o' => $offset]),
        );
    }

    public function deleteFile(int $id): void
    {
        $file = $this->getFile($id);
        if ($file) {
            $fullPath = "{$this->uploadDir}/{$file['storage_path']}";
            if (file_exists($fullPath)) { unlink($fullPath); }
            $this->connection->execute('DELETE FROM media_files WHERE id = :id', ['id' => $id]);
        }
    }

    public function createFolder(string $name, ?int $parentId = null): array
    {
        $this->connection->execute('INSERT INTO media_folders (name, parent_id) VALUES (:n, :pid)', ['n' => $name, 'pid' => $parentId]);
        return $this->connection->fetchOne('SELECT * FROM media_folders WHERE id = :id', ['id' => (int) $this->connection->lastInsertId()]);
    }

    /** @return array[] */
    public function listFolders(?int $parentId = null): array
    {
        $where = $parentId !== null ? 'WHERE parent_id = :pid' : 'WHERE parent_id IS NULL';
        $params = $parentId !== null ? ['pid' => $parentId] : [];
        return $this->connection->fetchAll("SELECT * FROM media_folders {$where} ORDER BY name", $params);
    }

    private function ensureTable(): void
    {
        $this->connection->exec('CREATE TABLE IF NOT EXISTS media_files (id INTEGER PRIMARY KEY AUTOINCREMENT, filename VARCHAR(255), original_name VARCHAR(255), mime_type VARCHAR(100), size INTEGER, storage_path VARCHAR(500), thumbnail_path VARCHAR(500), alt_text VARCHAR(255), folder_id INTEGER, uploaded_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $this->connection->exec('CREATE TABLE IF NOT EXISTS media_folders (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), parent_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    }
}
