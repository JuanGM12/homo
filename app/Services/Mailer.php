<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        $this->mailer->isSMTP();
        $this->mailer->Host = (string) Config::env('MAIL_HOST', '');
        $this->mailer->Port = (int) Config::env('MAIL_PORT', 587);
        $this->mailer->SMTPAuth = (bool) Config::env('MAIL_SMTP_AUTH', true);
        $this->mailer->Username = (string) Config::env('MAIL_USERNAME', '');
        $this->mailer->Password = (string) Config::env('MAIL_PASSWORD', '');
        $this->mailer->SMTPSecure = (string) Config::env('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);

        $fromAddress = (string) Config::env('MAIL_FROM_ADDRESS', '');
        $fromName = (string) Config::env('MAIL_FROM_NAME', 'Acción en Territorio');

        if ($fromAddress !== '') {
            $this->mailer->setFrom($fromAddress, $fromName);
        }

        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Envía una notificación cuando una AoAT es devuelta al profesional.
     * Esta es la estructura base; luego afinamos el contenido según requerimientos.
     */
    public function sendAoatReturnedNotification(
        string $toEmail,
        string $toName,
        string $observation,
        string $motive,
        int $aoatId
    ): void {
        if ($toEmail === '') {
            return;
        }

        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'AoAT devuelta para ajustes';

            $body = sprintf(
                '<p>Hola %s,</p>
<p>Tu registro de Asesoría/Asistencia Técnica (AoAT) con ID <strong>%d</strong> ha sido marcado como <strong>Devuelta</strong> por tu profesional especializado.</p>
<p><strong>Motivo:</strong> %s</p>
<p><strong>Observación:</strong><br>%s</p>
<p>Por favor realiza los ajustes necesarios y actualiza el estado de la AoAT a <strong>Realizado</strong> una vez completes las correcciones.</p>
<p>Este mensaje se generó automáticamente desde la plataforma Acción en Territorio.</p>',
                htmlspecialchars($toName, ENT_QUOTES, 'UTF-8'),
                $aoatId,
                htmlspecialchars($motive, ENT_QUOTES, 'UTF-8'),
                nl2br(htmlspecialchars($observation, ENT_QUOTES, 'UTF-8'))
            );

            $this->mailer->Body = $body;

            $this->mailer->send();
        } catch (Exception) {
            // Por ahora, si el correo falla no detenemos el flujo; se puede loguear más adelante.
        }
    }

    /**
     * Envía un reporte semanal de AoAT a la coordinadora.
     */
    public function sendAoatWeeklyReport(string $htmlBody, string $subject = 'Reporte semanal AoAT'): void
    {
        $toEmail = (string) Config::env('AOAT_COORDINATOR_EMAIL', '');
        $toName = (string) Config::env('AOAT_COORDINATOR_NAME', 'Coordinadora Acción en Territorio');

        if ($toEmail === '') {
            return;
        }

        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;

            $this->mailer->send();
        } catch (Exception) {
            // De momento no detenemos el flujo si falla el envío; se puede loguear más adelante.
        }
    }
}

