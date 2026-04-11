<?php

namespace App\Modules\WhatsApp\Support;

use Illuminate\Support\Facades\Log;

class WhatsAppLog
{
    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('whatsapp')->{$level}($message, $context);
        } catch (\Throwable $exception) {
            self::writeFallbackWarning($level, $message, $exception, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function writeFallbackWarning(
        string $level,
        string $message,
        \Throwable $exception,
        array $context = [],
    ): void {
        try {
            Log::channel((string) config('logging.default', 'stack'))->warning(
                'WhatsApp log channel failed',
                [
                    'original_level' => $level,
                    'original_message' => $message,
                    'logging_error' => $exception->getMessage(),
                    'context' => $context,
                ],
            );
        } catch (\Throwable) {
            // Logging is non-critical for the WhatsApp pipeline and must never
            // become the reason a send, intake, or status update fails.
        }
    }
}
