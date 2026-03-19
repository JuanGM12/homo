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
        } catch (Exception $e) {
            $this->logMailError('aoat_returned', $toEmail, $e, [
                'aoat_id' => $aoatId,
                'motive' => $motive,
            ]);
        }
    }

    /**
     * Envía un reporte semanal de AoAT a la coordinadora.
     */
    public function sendAoatWeeklyReport(
        string $htmlBody,
        string $subject = 'Reporte semanal AoAT',
        ?string $pdfBinary = null,
        ?string $pdfFilename = null
    ): void
    {
        $toEmail = (string) Config::env('AOAT_COORDINATOR_EMAIL', '');
        $toName = (string) Config::env('AOAT_COORDINATOR_NAME', 'Coordinadora Acción en Territorio');

        if ($toEmail === '') {
            return;
        }

        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($toEmail, $toName);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;

            $logoHomo = dirname(__DIR__, 2) . '/public/assets/img/logoHomo.png';
            $logoAntioquia = dirname(__DIR__, 2) . '/public/assets/img/logoAntioquia.png';
            if (is_readable($logoHomo)) {
                $this->mailer->addEmbeddedImage($logoHomo, 'logo_homo');
            }
            if (is_readable($logoAntioquia)) {
                $this->mailer->addEmbeddedImage($logoAntioquia, 'logo_antioquia');
            }

            $this->mailer->Body = $htmlBody;

            if ($pdfBinary !== null && $pdfBinary !== '') {
                $this->mailer->addStringAttachment(
                    $pdfBinary,
                    $pdfFilename ?: ('reporte_semanal_aoat_' . date('Ymd_His') . '.pdf'),
                    PHPMailer::ENCODING_BASE64,
                    'application/pdf'
                );
            }

            $this->mailer->send();
        } catch (Exception $e) {
            $this->logMailError('aoat_weekly_report', $toEmail, $e, [
                'subject' => $subject,
            ]);
        }
    }

    /**
     * Envía un correo de restablecimiento de contraseña con un enlace único.
     */
    public function sendPasswordResetEmail(string $toEmail, string $toName, string $resetUrl): void
    {
        if ($toEmail === '') {
            return;
        }

        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Restablece tu contraseña · Acción en Territorio';

            $escapedName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
            $escapedUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

            $body = '
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Restablecer contraseña</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f3f4f6;padding:24px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#ffffff;border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;box-shadow:0 18px 45px rgba(15,23,42,0.15);">
                    <tr>
                        <td style="padding:20px 24px 0;background:linear-gradient(135deg,#0f172a,#0b1120);color:#e5f9f6;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="left" style="padding-bottom:18px;">
                                        <span style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;background-color:rgba(15,23,42,0.7);border:1px solid rgba(148,163,184,0.45);">
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:radial-gradient(circle at 30% 0%,#22c55e,#14b8a6);color:#ecfeff;margin-right:8px;">
                                                ♥
                                            </span>
                                            <span style="font-size:13px;font-weight:600;letter-spacing:0.03em;text-transform:uppercase;">Acción en Territorio</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom:26px;">
                                        <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;letter-spacing:-0.03em;">Solicitud para restablecer tu contraseña</h1>
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#cbd5f5;">
                                            Hola ' . $escapedName . ', recibimos una solicitud para restablecer la contraseña de tu cuenta.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 24px 8px;color:#0f172a;">
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.7;color:#111827;">
                                Para definir una nueva contraseña, haz clic en el botón siguiente. Por seguridad, este enlace estará disponible solo durante las próximas <strong>2 horas</strong>.
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#4b5563;">
                                Si tú no realizaste esta solicitud, puedes ignorar este correo. Tu contraseña actual seguirá funcionando.
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom:24px;">
                                <tr>
                                    <td align="center" style="border-radius:999px;background:linear-gradient(135deg,#0d9488,#0f766e);">
                                        <a href="' . $escapedUrl . '" style="display:inline-block;padding:11px 26px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            Definir nueva contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#6b7280;">
                                También puedes copiar y pegar este enlace en tu navegador:
                            </p>
                            <p style="margin:0 0 24px;font-size:12px;line-height:1.6;color:#0f766e;word-break:break-all;">
                                ' . $escapedUrl . '
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px 20px;border-top:1px solid #e5e7eb;background-color:#f9fafb;">
                            <p style="margin:0;font-size:11px;line-height:1.6;color:#9ca3af;">
                                Este mensaje fue enviado automáticamente por la plataforma Acción en Territorio. No respondas a este correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

            $this->mailer->Body = $body;

            $this->mailer->send();
        } catch (Exception $e) {
            $this->logMailError('password_reset', $toEmail, $e);
        }
    }

    /**
     * Registra errores de envío de correo en un log dedicado y en el error_log estándar.
     *
     * @param array<string,mixed> $extra
     */
    private function logMailError(string $context, string $toEmail, Exception $e, array $extra = []): void
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone((string) Config::timezone())))
            ->format('Y-m-d H:i:s');

        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        $logFile = $logDir . '/mail.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $extraJson = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
        $line = sprintf(
            "[%s] context=%s to=%s error=\"%s\" extra=%s%s",
            $timestamp,
            $context,
            $toEmail,
            $e->getMessage(),
            $extraJson,
            PHP_EOL
        );

        @file_put_contents($logFile, $line, FILE_APPEND);

        error_log('[MAIL][' . $context . '] Error enviando correo a ' . $toEmail . ': ' . $e->getMessage());
    }
}

