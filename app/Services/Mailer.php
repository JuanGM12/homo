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
        $this->mailer->Timeout = (int) Config::env('MAIL_TIMEOUT', 25);
    }

    /**
     * Envía el correo de AoAT devuelta después de responder al cliente (no bloquea la petición HTTP).
     */
    public static function scheduleAoatReturnedNotification(
        string $toEmail,
        string $toName,
        string $observation,
        string $motive,
        int $aoatId,
        string $subregion = '',
        string $municipality = '',
        string $activityDate = ''
    ): void {
        if ($toEmail === '') {
            return;
        }

        register_shutdown_function(static function () use ($toEmail, $toName, $observation, $motive, $aoatId, $subregion, $municipality, $activityDate): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                $mailer = new self();
                $mailer->sendAoatReturnedNotification(
                    $toEmail,
                    $toName,
                    $observation,
                    $motive,
                    $aoatId,
                    $subregion,
                    $municipality,
                    $activityDate
                );
            } catch (\Throwable $e) {
                error_log('[Mailer] scheduleAoatReturnedNotification: ' . $e->getMessage());
            }
        });
    }

    /**
     * Envía una notificación cuando una AoAT es devuelta al profesional.
     */
    public function sendAoatReturnedNotification(
        string $toEmail,
        string $toName,
        string $observation,
        string $motive,
        int $aoatId,
        string $subregion = '',
        string $municipality = '',
        string $activityDate = ''
    ): void {
        if ($toEmail === '') {
            return;
        }

        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($toEmail, $toName);

            $this->mailer->isHTML(true);

            $subjectMunicipality = trim($municipality) !== '' ? trim($municipality) : 'Municipio no registrado';
            $subjectDate = $this->formatHumanDate($activityDate);
            $this->mailer->Subject = sprintf(
                'AoAT devuelta para ajustes - %s y %s',
                $subjectMunicipality,
                $subjectDate !== '' ? $subjectDate : 'Fecha no registrada'
            );

            $logoHomo = dirname(__DIR__, 2) . '/public/assets/img/logoHomo.png';
            $logoAntioquia = dirname(__DIR__, 2) . '/public/assets/img/logoAntioquia.png';

            if (is_readable($logoHomo)) {
                $this->mailer->addEmbeddedImage($logoHomo, 'logo_homo');
            }
            if (is_readable($logoAntioquia)) {
                $this->mailer->addEmbeddedImage($logoAntioquia, 'logo_antioquia');
            }

            $escapedName = htmlspecialchars($toName !== '' ? $toName : 'profesional', ENT_QUOTES, 'UTF-8');
            $escapedSubregion = htmlspecialchars($subregion !== '' ? $subregion : 'No registrada', ENT_QUOTES, 'UTF-8');
            $escapedMunicipality = htmlspecialchars($municipality !== '' ? $municipality : 'No registrado', ENT_QUOTES, 'UTF-8');
            $escapedDate = htmlspecialchars($subjectDate !== '' ? $subjectDate : 'No registrada', ENT_QUOTES, 'UTF-8');
            $escapedMotive = htmlspecialchars($motive !== '' ? $motive : 'No registrado', ENT_QUOTES, 'UTF-8');
            $escapedObservation = nl2br(htmlspecialchars($observation !== '' ? $observation : 'Sin observación registrada.', ENT_QUOTES, 'UTF-8'));
            $escapedAoatId = htmlspecialchars((string) $aoatId, ENT_QUOTES, 'UTF-8');
            $logoHomoSrc = is_readable($logoHomo) ? 'cid:logo_homo' : '';
            $logoAntioquiaSrc = is_readable($logoAntioquia) ? 'cid:logo_antioquia' : '';
            $logoAntioquiaHtml = $logoAntioquiaSrc !== ''
                ? '<img src="' . htmlspecialchars($logoAntioquiaSrc, ENT_QUOTES, 'UTF-8') . '" alt="Gobernación de Antioquia" style="display:block;height:48px;width:auto;">'
                : '<span style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#4b5563;">Gobernación de Antioquia</span>';
            $logoHomoHtml = $logoHomoSrc !== ''
                ? '<img src="' . htmlspecialchars($logoHomoSrc, ENT_QUOTES, 'UTF-8') . '" alt="HOMO" style="display:block;height:46px;width:auto;">'
                : '<span style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#0f766e;">HOMO</span>';

            $this->mailer->Body = '
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>AoAT devuelta para ajustes</title>
</head>
<body style="margin:0;padding:0;background-color:#eef4f3;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:linear-gradient(180deg,#eaf4f1 0%,#f4f1ea 100%);padding:28px 12px;">
        <tr>
            <td align="center">
                <table width="680" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:680px;background-color:#ffffff;border:1px solid #d9e8e3;border-radius:24px;overflow:hidden;font-family:Segoe UI,Arial,sans-serif;box-shadow:0 18px 42px rgba(15,57,51,0.12);">
                    <tr>
                        <td style="padding:22px 26px;background:linear-gradient(135deg,#e4f0ec 0%,#f4efe5 100%);border-bottom:1px solid #d8e5df;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">' . $logoAntioquiaHtml . '</td>
                                    <td align="center" style="padding:0 12px;vertical-align:middle;">
                                        <div style="display:inline-block;padding:8px 16px;border-radius:999px;background-color:#ffffff;border:1px solid #cfe0da;color:#245b52;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">
                                            Equipo de Promoción y Prevención
                                        </div>
                                        <div style="margin-top:10px;font-size:26px;line-height:1.15;font-weight:700;letter-spacing:-0.03em;color:#123b36;">
                                            AoAT devuelta para ajustes
                                        </div>
                                        <div style="margin-top:6px;font-size:13px;line-height:1.5;color:#54736d;">
                                            Revisión de registro en la plataforma Acción en Territorio
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">' . $logoHomoHtml . '</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 30px 20px;color:#173c37;">
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.7;">Hola <strong>' . $escapedName . '</strong>,</p>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.75;color:#375a54;">
                                Tu registro de Asesoría o Asistencia Técnica fue marcado como <strong style="color:#a85c00;">Devuelta</strong> por el especialista. Revisa la información resumida a continuación, realiza los ajustes solicitados y luego actualiza el estado a <strong>Realizado</strong>.
                            </p>
                            <div style="margin:22px 0 20px;padding:18px 20px;border-radius:18px;background:linear-gradient(180deg,#f8fbfa 0%,#f5efe5 100%);border:1px solid #dbe7e2;">
                                <div style="margin:0 0 12px;font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#2d6a60;">
                                    Resumen de la devolución
                                </div>
                                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;font-size:14px;">
                                    <tr>
                                        <td style="width:34%;padding:11px 12px;background-color:#dfece7;border:1px solid #cbded8;color:#24564e;font-weight:700;">Subregión</td>
                                        <td style="padding:11px 12px;background-color:#ffffff;border:1px solid #d9e8e3;color:#2f4d48;">' . $escapedSubregion . '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:34%;padding:11px 12px;background-color:#dfece7;border:1px solid #cbded8;color:#24564e;font-weight:700;">Municipio</td>
                                        <td style="padding:11px 12px;background-color:#ffffff;border:1px solid #d9e8e3;color:#2f4d48;">' . $escapedMunicipality . '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:34%;padding:11px 12px;background-color:#dfece7;border:1px solid #cbded8;color:#24564e;font-weight:700;">Fecha AoAT</td>
                                        <td style="padding:11px 12px;background-color:#ffffff;border:1px solid #d9e8e3;color:#2f4d48;">' . $escapedDate . '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:34%;padding:11px 12px;background-color:#dfece7;border:1px solid #cbded8;color:#24564e;font-weight:700;">Motivo</td>
                                        <td style="padding:11px 12px;background-color:#ffffff;border:1px solid #d9e8e3;color:#2f4d48;">' . $escapedMotive . '</td>
                                    </tr>
                                    <tr>
                                        <td style="width:34%;padding:11px 12px;background-color:#dfece7;border:1px solid #cbded8;color:#24564e;font-weight:700;vertical-align:top;">Observación</td>
                                        <td style="padding:11px 12px;background-color:#ffffff;border:1px solid #d9e8e3;color:#2f4d48;line-height:1.65;">' . $escapedObservation . '</td>
                                    </tr>
                                </table>
                            </div>
                            <div style="padding:16px 18px;border-left:4px solid #2b7a6b;background-color:#f4fbf8;border-radius:0 14px 14px 0;color:#335a53;font-size:14px;line-height:1.7;">
                                Cuando completes las correcciones, ingresa nuevamente al registro y marca la AoAT como <strong>Realizado</strong> para continuar con la revisión.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 30px 24px;background-color:#f7faf9;border-top:1px solid #e1ece8;">
                            <p style="margin:0;font-size:12px;line-height:1.7;color:#62817b;text-align:center;">
                                Este mensaje se generó automáticamente desde la plataforma Equipo de Promoción y Prevención.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

            $plainName = trim($toName) !== '' ? trim($toName) : 'profesional';
            $plainSubregion = trim($subregion) !== '' ? trim($subregion) : 'No registrada';
            $plainMunicipality = trim($municipality) !== '' ? trim($municipality) : 'No registrado';
            $plainDate = $subjectDate !== '' ? $subjectDate : 'No registrada';
            $plainMotive = trim($motive) !== '' ? trim($motive) : 'No registrado';
            $plainObservation = trim($observation) !== '' ? trim($observation) : 'Sin observación registrada.';

            $this->mailer->AltBody =
                "Hola {$plainName},\n\n" .
                "Tu AoAT #{$aoatId} fue devuelta para ajustes.\n" .
                "Subregión: {$plainSubregion}\n" .
                "Municipio: {$plainMunicipality}\n" .
                "Fecha AoAT: {$plainDate}\n" .
                "Motivo: {$plainMotive}\n" .
                "Observación: {$plainObservation}\n\n" .
                "Este mensaje se generó automáticamente desde la plataforma Equipo de Promoción y Prevención.";

            $this->mailer->send();
        } catch (Exception $e) {
            $this->logMailError('aoat_returned', $toEmail, $e, [
                'aoat_id' => $aoatId,
                'motive' => $motive,
                'municipality' => $municipality,
                'activity_date' => $activityDate,
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

    private function formatHumanDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt instanceof \DateTimeImmutable) {
            return $date;
        }

        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $month = $months[(int) $dt->format('n')] ?? $dt->format('m');

        return $dt->format('d') . ' de ' . $month . ' de ' . $dt->format('Y');
    }
}
