<?php
/**
 * TelegramBot.php — Cliente mínimo de la API de Telegram (solo sendMessage)
 *
 * Usado por Cron/telegram_runner.php para anunciar partidas y resultados
 * en un grupo/canal de Telegram sin depender de ninguna librería externa.
 */
class TelegramBot {
    private string $token;
    private string $chatId;

    public function __construct(string $token, string $chatId) {
        $this->token  = $token;
        $this->chatId = $chatId;
    }

    /** Envía un mensaje de texto (Markdown) al chat configurado. Devuelve false si falla o no hay credenciales. */
    public function sendMessage(string $text): bool {
        if (!$this->token || !$this->chatId) return false;

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/x-www-form-urlencoded',
            'content'       => http_build_query([
                'chat_id'    => $this->chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]),
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return false;

        $data = json_decode($raw, true);
        return !empty($data['ok']);
    }
}
