<?php
/**
 * EmailService.php — Servicio de envío de emails
 *
 * Usa la función PHP mail() del servidor (SiteGround) sin credenciales SMTP.
 * El remitente debe coincidir con el dominio que tiene los registros SPF/DKIM
 * configurados (hitstoric.nite.black) para evitar que los correos vayan a spam.
 */
class EmailService {

    /**
     * Envía un email HTML usando la función mail() nativa de PHP.
     * El asunto y el cuerpo van codificados en Base64 UTF-8 para
     * soportar tildes y caracteres especiales sin problemas.
     */
    private static function sendSmtp(string $to, string $subject, string $htmlBody): bool {
        // SMTP_ENABLED es false en Windows (desarrollo local), true en producción
        if (!SMTP_ENABLED) return true;

        $from     = SMTP_FROM;      // noreply@hitstoric.nite.black
        $fromName = SMTP_FROM_NAME; // Hitstoric

        // Cabeceras necesarias para email HTML con codificación UTF-8
        $headers = implode("\r\n", [
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>",
            "Reply-To: {$from}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
        ]);

        // Codificación Base64 del asunto y del cuerpo para soportar UTF-8
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $encodedBody    = chunk_split(base64_encode($htmlBody));

        return @mail($to, $encodedSubject, $encodedBody, $headers);
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
        string $to, string $pin, string $baseUrl, int $playerNum
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $url     = $baseUrl . '/player?pin=' . $pin;
        $content = "
          <p style=\"font-size:18px;font-weight:700;margin:0 0 8px\">¡Te han invitado a jugar! 🎵</p>
          <p style=\"color:#9ca3af;margin:0 0 24px\">Tienes reservado el Cartón {$playerNum}. Solo necesitas hacer clic y poner tu nombre.</p>
          " . self::btn($url, 'Entrar a la partida →') . "
          <p style=\"text-align:center;color:#6b7280;font-size:13px;margin:0\">
            Tu PIN personal es <strong style=\"color:#e94560\">{$pin}</strong> por si lo necesitas manualmente.
          </p>";

        return self::sendSmtp($to, 'Hitstoric — Tu acceso a la partida', self::html($content));
    }
}
