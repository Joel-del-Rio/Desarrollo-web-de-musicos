<?php
/**
 * EmailService.php — Servicio de envío de emails
 *
 * Usa SMTP autenticado de SiteGround con PHPMailer y TLS.
 * El remitente debe coincidir con el dominio que tiene los registros SPF/DKIM
 * configurados (hitstoric.nite.black) para evitar que los correos vayan a spam.
 */
class EmailService {

    /**
     * Envía un email HTML por SMTP autenticado, con credenciales fuera de la web.
     * El asunto y el cuerpo van codificados en Base64 UTF-8 para
     * soportar tildes y caracteres especiales sin problemas.
     */
    private static function sendSmtp(string $to, string $subject, string $htmlBody): bool {
        if (!SMTP_ENABLED) return true;
        require_once __DIR__.'/../Lib/PHPMailer/Exception.php';
        require_once __DIR__.'/../Lib/PHPMailer/SMTP.php';
        require_once __DIR__.'/../Lib/PHPMailer/PHPMailer.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Credentials stay outside public_html and source control.
            $path = getenv('HITSTORIC_SMTP_CONFIG') ?: dirname(__DIR__,4).'/hitstoric-ops/smtp-private.json';
            if (!is_readable($path)) throw new RuntimeException('SMTP configuration missing');
            $config = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            foreach (['host','username','password'] as $key) {
                if (empty($config[$key]) || !is_string($config[$key])) throw new RuntimeException('SMTP configuration invalid');
            }
            if (($config['port'] ?? 0) !== 465) throw new RuntimeException('SMTP must use implicit TLS on port 465');
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = 465;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPDebug = 0;
            $mail->Timeout = 15;
            $mail->getSMTPInstance()->Timelimit = 25;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = html_entity_decode(strip_tags(preg_replace('/<\/(?:p|div|tr)>/i',"\n",$htmlBody)),ENT_QUOTES,'UTF-8');
            $ok = $mail->send();
            error_log('[Hitstoric SMTP] accepted message '.$mail->getLastMessageID());
            return $ok;
        } catch (Throwable $e) {
            // Never put passwords or the SMTP exchange in application responses/logs.
            error_log('[Hitstoric SMTP] delivery failed: '.get_class($e));
            return false;
        } finally {
            $mail->smtpClose();
        }
    }

    /**
     * Plantilla HTML base para todos los emails.
     * Inserta el contenido pasado como parámetro dentro del bloque central.
     */
    private static function html(string $content): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f1923;font-family:Arial,sans-serif;color:#d0d7de">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1923;padding:32px 0">
    <tr><td align="center">
      <table width="520" cellpadding="0" cellspacing="0" style="background:#16213e;border-radius:16px;overflow:hidden;max-width:100%">
        <tr><td style="background:#e94560;padding:24px 32px;text-align:center">
          <span style="color:#fff;font-size:24px;font-weight:900;letter-spacing:-0.5px">🎵 Hitstoric</span>
        </td></tr>
        <tr><td style="padding:32px">
          {$content}
        </td></tr>
        <tr><td style="padding:16px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
          <span style="font-size:12px;color:#6b7280">© Hitstoric — El juego musical de las fechas</span>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    }

    /** Genera un botón CTA con enlace para incluir en el cuerpo del email */
    private static function btn(string $url, string $label): string {
        return "<div style=\"text-align:center;margin:24px 0\">
          <a href=\"{$url}\" style=\"background:#e94560;color:#fff;text-decoration:none;
             padding:14px 32px;border-radius:50px;font-weight:700;font-size:16px;display:inline-block\">
            {$label}
          </a>
        </div>";
    }

    // ── Emails públicos ───────────────────────────────────

    /**
     * Envía el resumen de la partida al organizador tras crearla.
     * - Modo compartido: muestra el PIN grande y el enlace de sala.
     * - Modo individual: tabla con los enlaces personales de cada jugador.
     */
    public static function sendGameCreated(
        string $to, string $gamePin, string $baseUrl,
        string $pinMode, array $individualPins = []
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        if ($pinMode === 'individual') {
            // Construir tabla de cartones con un enlace directo por jugador
            $rows = '';
            foreach ($individualPins as $i => $pin) {
                $url   = $baseUrl . '/player?pin=' . $pin;
                $rows .= "<tr>
                  <td style=\"padding:8px 12px;color:#9ca3af;font-size:14px\">Cartón " . ($i + 1) . "</td>
                  <td style=\"padding:8px 12px\">
                    <a href=\"{$url}\" style=\"color:#e94560;font-size:13px\">{$url}</a>
                  </td>
                </tr>";
            }
            $content = "
              <p style=\"font-size:18px;font-weight:700;margin:0 0 8px\">¡Partida creada con éxito! 🎉</p>
              <p style=\"color:#9ca3af;margin:0 0 24px\">Modo PINs individuales — " . count($individualPins) . " cartones</p>
              <table width=\"100%\" style=\"border-collapse:collapse;background:#0f1923;border-radius:10px;overflow:hidden\">
                <thead><tr style=\"background:rgba(233,69,96,.15)\">
                  <th style=\"padding:10px 12px;text-align:left;font-size:12px;color:#e94560;text-transform:uppercase\">Cartón</th>
                  <th style=\"padding:10px 12px;text-align:left;font-size:12px;color:#e94560;text-transform:uppercase\">Enlace</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
              </table>
              <p style=\"color:#9ca3af;font-size:13px;margin:20px 0 0\">Cada jugador solo tiene que clicar su enlace y poner su nombre.</p>
              " . self::btn($baseUrl . '/admin', 'Ir al panel de control');
        } else {
            // Modo compartido: un PIN para todos
            $url     = $baseUrl . '/player?pin=' . $gamePin;
            $content = "
              <p style=\"font-size:18px;font-weight:700;margin:0 0 8px\">¡Partida creada con éxito! 🎉</p>
              <p style=\"color:#9ca3af;margin:0 0 4px\">PIN de la partida:</p>
              <div style=\"font-size:48px;font-weight:900;color:#e94560;letter-spacing:8px;text-align:center;margin:16px 0\">{$gamePin}</div>
              <p style=\"color:#9ca3af;font-size:14px;margin:0 0 4px\">O comparte el enlace directo:</p>
              <p style=\"text-align:center;margin:0 0 8px\"><a href=\"{$url}\" style=\"color:#e94560\">{$url}</a></p>
              " . self::btn($baseUrl . '/admin', 'Ir al panel de control');
        }

        return self::sendSmtp($to, 'Hitstoric — Partida creada', self::html($content));
    }

    /**
     * Envía el PIN individual a un jugador invitado.
     * El enlace ya incluye el PIN como parámetro para que no tenga que escribirlo.
     */
    public static function sendPlayerPin(
        string $to, string $pin, string $baseUrl, int $playerNum, int $gameId
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || $gameId < 1) return false;

        // The event ID prevents a saved player session from opening another event.
        $url     = $baseUrl . '/player?game=' . $gameId . '&pin=' . rawurlencode($pin);
        $content = "
          <p style=\"font-size:18px;font-weight:700;margin:0 0 8px\">¡Te han invitado a jugar! 🎵</p>
          <p style=\"color:#9ca3af;margin:0 0 24px\">Tu código personal está preparado. Solo necesitas hacer clic y poner tu nombre.</p>
          " . self::btn($url, 'Entrar a la partida →') . "
          <p style=\"text-align:center;color:#6b7280;font-size:13px;margin:0\">
            Tu PIN personal es <strong style=\"color:#e94560\">{$pin}</strong> por si lo necesitas manualmente.
          </p>";

        return self::sendSmtp($to, 'Hitstoric — Acceso a la partida ' . $gameId, self::html($content));
    }
}

