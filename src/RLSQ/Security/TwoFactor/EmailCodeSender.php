<?php

declare(strict_types=1);

namespace RLSQ\Security\TwoFactor;

use RLSQ\Mailer\Email;
use RLSQ\Mailer\Mailer;

/**
 * Envoie le code 2FA par email.
 */
class EmailCodeSender
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly string $appName = 'RLSQ-FRAM',
    ) {}

    public function send(string $email, string $code): bool
    {
        $message = (new Email())
            ->to($email)
            ->subject(sprintf('%s — Code de vérification', $this->appName))
            ->text(sprintf("Votre code de vérification : %s\n\nCe code expire dans 10 minutes.\n\nSi vous n'avez pas demandé ce code, ignorez cet email.", $code))
            ->html(sprintf(
                '<div style="font-family:system-ui,sans-serif;max-width:400px;margin:0 auto;padding:20px;">'
                . '<h2 style="color:#ff3e00;">%s</h2>'
                . '<p>Votre code de vérification :</p>'
                . '<div style="background:#f5f5f5;padding:20px;text-align:center;font-size:32px;font-weight:700;letter-spacing:8px;border-radius:8px;margin:20px 0;">%s</div>'
                . '<p style="color:#888;font-size:14px;">Ce code expire dans 10 minutes.</p>'
                . '<p style="color:#888;font-size:12px;">Si vous n\'avez pas demandé ce code, ignorez cet email.</p>'
                . '</div>',
                htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8'),
                $code,
            ))
            ->priority(1);

        return $this->mailer->send($message);
    }
}
