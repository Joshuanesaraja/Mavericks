<?php

require_once __DIR__ . '/../Helpers/Response.php';

class RateLimit
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 60;

    public static function handle(
        string $key
    ): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $fileName = hash(
            'sha256',
            $key . '_' . $ip
        );

        $directory = __DIR__ . '/../../storage/rate_limit';

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = $directory . '/' . $fileName . '.json';

        $now = time();

        $data = [
            'attempts' => [],
        ];

        if (file_exists($file)) {
            $contents = file_get_contents($file);

            if ($contents !== false) {
                $decoded = json_decode($contents, true);

                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        // Keep only attempts from the current time window.
        $data['attempts'] = array_values(
            array_filter(
                $data['attempts'] ?? [],
                function ($timestamp) use ($now) {
                    return ($now - $timestamp)
                        < self::WINDOW_SECONDS;
                }
            )
        );

        if (count($data['attempts']) >= self::MAX_ATTEMPTS) {
            Response::error(
                'Too many requests. Please try again later.',
                429
            );

            return false;
        }

        $data['attempts'][] = $now;

        file_put_contents(
            $file,
            json_encode($data),
            LOCK_EX
        );

        return true;
    }
}
