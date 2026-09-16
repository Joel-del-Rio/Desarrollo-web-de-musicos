<?php
/**
 * BotBrain.php — Lógica de decisión de los jugadores bot
 *
 * Modelo orientativo (no una simulación exacta): asume que la "cultura
 * musical" de una persona se forma sobre todo entre los 12 y los 25 años,
 * con pico alrededor de los 18. Un bot de más edad acertará más con
 * canciones antiguas; uno joven, con las recientes. Sirve para poder
 * probar la jugabilidad de una partida sin necesitar gente real.
 */
class BotBrain {
    public const MIN_AGE = 14;
    public const MAX_AGE = 90;

    /**
     * Familiaridad estimada (0-1) de una persona de $age años con una
     * canción del año $songYear. Cae en forma de campana alrededor del año
     * en que esa persona tenía ~18 años.
     */
    public static function familiarity(int $age, int $songYear): float {
        $currentYear = (int)date('Y');
        $birthYear   = $currentYear - $age;
        $center      = $birthYear + 18;
        $sigma       = 11.0;
        $diff        = $songYear - $center;
        return exp(-($diff * $diff) / (2 * $sigma * $sigma));
    }

    /**
     * Elige la posición donde el bot coloca la canción en su línea del tiempo.
     * Cuanta más familiaridad tiene con la época, más probable es que acierte
     * la posición cronológicamente correcta; si falla, elige otra al azar.
     *
     * @param array $timelineYears  Años ya colocados en la línea del bot
     * @param int   $songYear       Año real de la canción de esta ronda
     * @param int   $age            Edad orientativa del bot
     */
    public static function choosePosition(array $timelineYears, int $songYear, int $age): int {
        sort($timelineYears);
        $n = count($timelineYears);

        $correctPosition = 0;
        foreach ($timelineYears as $y) { if ($y < $songYear) $correctPosition++; }

        $accuracy = 0.30 + 0.55 * self::familiarity($age, $songYear); // 30%-85% según la época

        if ($n === 0 || (mt_rand() / mt_getrandmax()) < $accuracy) {
            return $correctPosition;
        }

        $candidates = array_values(array_diff(range(0, $n), [$correctPosition]));
        return $candidates ? $candidates[array_rand($candidates)] : $correctPosition;
    }

    /** Emoji de reacción del bot tras responder, según si acertó o no */
    public static function reactionEmoji(bool $correct): string {
        $pool = $correct ? ['🔥', '👏', '❤️', '😮'] : ['😮', '👍', '😂'];
        return $pool[array_rand($pool)];
    }
}
