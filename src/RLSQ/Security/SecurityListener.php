<?php

declare(strict_types=1);

namespace RLSQ\Security;

use RLSQ\EventDispatcher\EventSubscriberInterface;
use RLSQ\HttpFoundation\RedirectResponse;
use RLSQ\HttpFoundation\Response;
use RLSQ\HttpKernel\Event\ControllerEvent;
use RLSQ\HttpKernel\Exception\AccessDeniedHttpException;
use RLSQ\HttpKernel\Exception\HttpException;
use RLSQ\HttpKernel\KernelEvents;
use RLSQ\Security\Attribute\IsGranted;
use RLSQ\Security\Attribute\RequireAuth;
use RLSQ\Security\Authentication\TokenStorage;

/**
 * Écoute kernel.controller pour vérifier les attributs de sécurité #[IsGranted] et #[RequireAuth].
 */
class SecurityListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorage $tokenStorage,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();
        $reflMethod = $this->getReflectionMethod($controller);

        if ($reflMethod === null) {
            return;
        }

        $reflClass = $reflMethod->getDeclaringClass();

        // Collecter les attributs de la classe ET de la méthode
        $this->checkRequireAuth($reflClass, $reflMethod, $event);
        $this->checkIsGranted($reflClass, $reflMethod);
    }

    private function checkRequireAuth(\ReflectionClass $class, \ReflectionMethod $method, ControllerEvent $event): void
    {
        $attrs = array_merge(
            $class->getAttributes(RequireAuth::class),
            $method->getAttributes(RequireAuth::class),
        );

        if (empty($attrs)) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if ($token === null || !$token->isAuthenticated()) {
            $requireAuth = $attrs[0]->newInstance();

            if ($requireAuth->redirectTo !== null) {
                $event->setController(function () use ($requireAuth): Response {
                    return new RedirectResponse($requireAuth->redirectTo);
                });
                return;
            }

            throw new HttpException(
                401,
                $requireAuth->message ?? 'Authentification requise.',
            );
        }
    }

    private function checkIsGranted(\ReflectionClass $class, \ReflectionMethod $method): void
    {
        $attrs = array_merge(
            $class->getAttributes(IsGranted::class),
            $method->getAttributes(IsGranted::class),
        );

        if (empty($attrs)) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if ($token === null || !$token->isAuthenticated()) {
            throw new HttpException(401, 'Authentification requise.');
        }

        $userRoles = $token->getRoles();

        foreach ($attrs as $attr) {
            $isGranted = $attr->newInstance();
            $required = $isGranted->attribute;

            if (!in_array($required, $userRoles, true)) {
                throw new AccessDeniedHttpException(
                    $isGranted->message ?? sprintf('Accès refusé. Rôle "%s" requis.', $required),
                );
            }
        }
    }

    private function getReflectionMethod(callable $controller): ?\ReflectionMethod
    {
        if (is_array($controller) && count($controller) === 2) {
            return new \ReflectionMethod($controller[0], $controller[1]);
        }

        if (is_object($controller) && method_exists($controller, '__invoke')) {
            return new \ReflectionMethod($controller, '__invoke');
        }

        return null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }
}
