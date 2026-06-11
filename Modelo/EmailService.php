<?php
class EmailService {

    public static function sendGameCreated(
        string $to,
        string $gamePin,
        string $baseUrl,
        string $pinMode,
        array  $individualPins = []
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $host    = parse_url($baseUrl, PHP_URL_HOST) ?: 'hitstoric.nite.black';
        $from    = "noreply@{$host}";
        $subject = '=?UTF-8?B?' . base64_encode('Hitstoric — Partida creada') . '?=';
        $headers = implode("\r\n", [
            "From: Hitstoric <{$from}>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);

        if ($pinMode === 'individual') {
            $pinLines = [];
            foreach ($individualPins as $i => $pin) {
                $pinLines[] = '  Cartón ' . ($i + 1) . ': ' . $pin;
            }
            $body = implode("\r\n", array_merge([
                '¡Tu partida de Hitstoric ha sido creada!',
                '',
                'Modo: PINs individuales (' . count($individualPins) . ' cartones)',
                '',
                'PINs de los participantes:',
            ], $pinLines, [
                '',
                'Reparte cada PIN a su jugador correspondiente antes de iniciar.',
                '',
                'Enlace para jugadores: ' . $baseUrl . '/player',
                'Panel de control (admin): ' . $baseUrl . '/admin',
                '',
                'Guarda este correo — contiene todos los códigos.',
            ]));
        } else {
            $body = implode("\r\n", [
                '¡Tu partida de Hitstoric ha sido creada!',
                '',
                'PIN de la partida: ' . $gamePin,
                '',
                'Comparte este PIN con los jugadores para que puedan unirse.',
                '',
                'Enlace para jugadores: ' . $baseUrl . '/player',
                'Panel de control (admin): ' . $baseUrl . '/admin',
            ]);
        }

        return @mail($to, $subject, $body, $headers);
    }

    public static function sendPlayerPin(
        string $to, string $pin, string $baseUrl, int $playerNum
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $host    = parse_url($baseUrl, PHP_URL_HOST) ?: 'hitstoric.nite.black';
        $from    = "noreply@{$host}";
        $subject = '=?UTF-8?B?' . base64_encode('Hitstoric — Tu PIN de acceso') . '?=';
        $headers = implode("\r\n", [
            "From: Hitstoric <{$from}>",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);
        $body = implode("\r\n", [
            '¡Tu cartón de Hitstoric está listo!',
            '',
            'Tu PIN personal: ' . $pin,
            '',
            'Introdúcelo en la pantalla de jugador para unirte a la partida.',
            '',
            'Enlace: ' . $baseUrl . '/player',
            '',
            '¡Buena suerte!',
        ]);

        return @mail($to, $subject, $body, $headers);
    }
}
