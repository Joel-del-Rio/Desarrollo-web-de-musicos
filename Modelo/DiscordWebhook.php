<?php
/**
 * DiscordWebhook.php — Cliente mínimo de un Webhook de Discord (solo mensajes de texto)
 *
 * Usado por GameController::createGame() para anunciar partidas públicas
 * en un canal de Discord sin depender de ninguna librería externa.
 */
class DiscordWebhook {
    private string $url;

    public function __construct(string $url) {
        $this->url = $url;
    }

    /** Envía un mensaje de texto al canal del webhook. Devuelve false si falla o no hay URL configurada. */
    public function sendMessage(string $text): bool {
        if (!$this->url) return false;

        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/json',
            'content'       => json_encode(['content' => $text]),
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($this->url, false, $ctx);
        if ($raw === false) return false;

        // Discord responde 204 sin cuerpo cuando el mensaje se publica correctamente
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) $status = (int)$m[1];
        }
        return $status >= 200 && $status < 300;
    }
}
