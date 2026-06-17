<?php
class StripeHelper {

    private static function request(string $method, string $path, array $params = []): array {
        $ch = curl_init('https://api.stripe.com/v1/' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $resp = curl_exec($ch);
        curl_close($ch);
        return json_decode($resp ?: '{}', true) ?? [];
    }

    public static function createCheckoutSession(string $pin, string $playerName): array {
        $successUrl = BASE_URL . '/Vista/player.php?stripe_session={CHECKOUT_SESSION_ID}';
        $cancelUrl  = BASE_URL . '/Vista/player.php?stripe_cancel=1&pin=' . urlencode($pin);

        return self::request('POST', 'checkout/sessions', [
            'mode'                                          => 'payment',
            'payment_method_types[]'                        => 'card',
            'line_items[0][price_data][currency]'           => 'eur',
            'line_items[0][price_data][unit_amount]'        => 100,
            'line_items[0][price_data][product_data][name]' => 'Hitstoric — Entrada a la partida',
            'line_items[0][quantity]'                       => 1,
            'success_url'                                   => $successUrl,
            'cancel_url'                                    => $cancelUrl,
            'metadata[pin]'                                 => $pin,
            'metadata[player_name]'                         => $playerName,
            'expires_at'                                    => time() + 1800,
        ]);
    }

    public static function getSession(string $sessionId): array {
        return self::request('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    }
}
