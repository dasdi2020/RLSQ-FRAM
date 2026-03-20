<?php

declare(strict_types=1);

namespace App\Controller;

use App\Project\FileManagerService;
use RLSQ\Controller\AbstractController;
use RLSQ\Controller\Attribute\Route;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\Request;
use RLSQ\OpenApi\Attribute\ApiRoute;
use RLSQ\Security\Attribute\RequireAuth;

#[Route('/api/p/{projectSlug}/files')]
#[RequireAuth]
class FileEditorController extends AbstractController
{
    private function getFileManager(string $projectSlug): FileManagerService
    {
        $projectDir = $this->get('service_container')->getParameter('kernel.project_dir');
        return new FileManagerService($projectDir, $projectSlug);
    }

    #[Route('', name: 'files_tree', methods: ['GET'])]
    #[ApiRoute(summary: 'Arborescence des fichiers', tags: ['Files'])]
    public function tree(string $projectSlug): JsonResponse
    {
        return $this->json(['data' => $this->getFileManager($projectSlug)->listTree()]);
    }

    #[Route('/read', name: 'files_read', methods: ['GET'])]
    #[ApiRoute(summary: 'Lire un fichier', tags: ['Files'])]
    public function read(string $projectSlug, Request $request): JsonResponse
    {
        $path = $request->query->get('path') ?? '';
        $fm = $this->getFileManager($projectSlug);

        $content = $fm->read($path);
        if ($content === null) {
            return $this->json(['error' => 'Fichier introuvable.'], 404);
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return $this->json([
            'path' => $path,
            'content' => $content,
            'language' => FileManagerService::getLanguage($ext),
        ]);
    }

    #[Route('/write', name: 'files_write', methods: ['PUT'])]
    #[ApiRoute(summary: 'Écrire un fichier', tags: ['Files'])]
    public function write(string $projectSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $path = $data['path'] ?? '';
        $content = $data['content'] ?? '';

        if (!$path) {
            return $this->json(['error' => 'path requis.'], 400);
        }

        $this->getFileManager($projectSlug)->write($path, $content);

        return $this->json(['message' => 'Fichier sauvegardé.', 'path' => $path]);
    }

    #[Route('/create', name: 'files_create', methods: ['POST'])]
    #[ApiRoute(summary: 'Créer un fichier ou dossier', tags: ['Files'])]
    public function create(string $projectSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $path = $data['path'] ?? '';
        $type = $data['type'] ?? 'file';

        if (!$path) {
            return $this->json(['error' => 'path requis.'], 400);
        }

        $fm = $this->getFileManager($projectSlug);

        if ($type === 'directory') {
            $fm->createDirectory($path);
        } else {
            $fm->write($path, $data['content'] ?? '');
        }

        return $this->json(['message' => 'Créé.', 'path' => $path], 201);
    }

    #[Route('/delete', name: 'files_delete', methods: ['DELETE'])]
    #[ApiRoute(summary: 'Supprimer un fichier ou dossier', tags: ['Files'])]
    public function delete(string $projectSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $path = $data['path'] ?? '';

        if (!$path) {
            return $this->json(['error' => 'path requis.'], 400);
        }

        $this->getFileManager($projectSlug)->delete($path);

        return $this->json(['message' => 'Supprimé.']);
    }

    #[Route('/rename', name: 'files_rename', methods: ['PUT'])]
    #[ApiRoute(summary: 'Renommer', tags: ['Files'])]
    public function rename(string $projectSlug, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->getFileManager($projectSlug)->rename($data['path'] ?? '', $data['new_name'] ?? '');

        return $this->json(['message' => 'Renommé.']);
    }
}
