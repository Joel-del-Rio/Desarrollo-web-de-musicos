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
                $pinLines[] = '  Cartón ' . ($i + 1) . ': ' . $baseUrl . '/player?pin=' . $pin;
            }
            $body = implode("\r\n", array_merge([
                '¡Tu partida de Hitstoric ha sido creada!',
                '',
                'Modo: PINs individuales (' . count($individualPins) . ' cartones)',
                '',
                'Enlaces de acceso (cada jugador recibe el suyo):',
            ], $pinLines, [
                '',
                'Cada enlace abre el juego con el PIN ya introducido — solo hace falta poner el nombre.',
                '',
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
                'Comparte este enlace con los jugadores (el PIN ya va incluido):',
                $baseUrl . '/player?pin=' . $gamePin,
                '',
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
            'Haz clic en el enlace para unirte — solo necesitas poner tu nombre:',
            $baseUrl . '/player?pin=' . $pin,
            '',
            '(Tu PIN personal es ' . $pin . ' por si lo necesitas manualmente)',
            '',
            '¡Buena suerte!',
        ]);

        return @mail($to, $subject, $body, $headers);
    }
}
