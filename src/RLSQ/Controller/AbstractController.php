<?php

declare(strict_types=1);

namespace RLSQ\Controller;

use RLSQ\DependencyInjection\ContainerInterface;
use RLSQ\HttpFoundation\JsonResponse;
use RLSQ\HttpFoundation\RedirectResponse;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Exception\AccessDeniedHttpException;
use RLSQ\HttpKernel\Exception\NotFoundHttpException;
use RLSQ\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractController implements ContainerAwareInterface
{
    protected ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Rendu d'un template (délègue au moteur de templates).
     */
    protected function render(string $template, array $parameters = [], ?Response $response = null): Response
    {
        $engine = $this->container->get('templating');
        $content = $engine->render($template, $parameters);

        $response ??= new Response();
        $response->setContent($content);

        if (!$response->headers->has('content-type')) {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        return $response;
    }

    /**
     * Retourne une réponse JSON.
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Redirige vers une URL.
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Redirige vers une route nommée.
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return $this->redirect($this->generateUrl($route, $parameters), $status);
    }

    /**
     * Génère une URL à partir d'un nom de route.
     */
    protected function generateUrl(string $route, array $parameters = []): string
    {
        return $this->container->get('url_generator')->generate($route, $parameters);
    }

    /**
     * Récupère un paramètre du Container.
     */
    protected function getParameter(string $name): mixed
    {
        return $this->container->getParameter($name);
    }

    /**
     * Ajoute un message flash.
     */
    protected function addFlash(string $type, string $message): void
    {
        $session = $this->container->get('session');
        $session->setFlash($type, $message);
    }

    /**
     * Crée une exception 404.
     */
    protected function createNotFoundException(string $message = 'Not Found'): NotFoundHttpException
    {
        return new NotFoundHttpException($message);
    }

    /**
     * Crée une exception 403.
     */
    protected function createAccessDeniedException(string $message = 'Access Denied'): AccessDeniedHttpException
    {
        return new AccessDeniedHttpException($message);
    }

    /**
     * Récupère un service depuis le Container.
     */
    protected function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    /**
     * Vérifie si un service existe dans le Container.
     */
    protected function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
