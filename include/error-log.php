<?php
declare(strict_types=1);

/**
 * Файловый лог ошибок PHP (без вывода клиенту). Файл: data/logs/app.log
 */
function cms_error_log_path(): string
{
    $dir = (defined('DOC_ROOT') ? DOC_ROOT : dirname(__DIR__)) . '/data/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir . '/app.log';
}

function cms_log_exception(Throwable $e, string $label = 'exception'): void
{
    $line = sprintf(
        "[%s] %s: %s in %s:%d\nStack: %s\n",
        date('Y-m-d H:i:s'),
        $label,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents(cms_error_log_path(), $line, FILE_APPEND | LOCK_EX);
}

/** Регистрирует обработчики один раз за запрос (пропуск в CLI). */
function cms_register_error_logging(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    if (PHP_SAPI === 'cli') {
        return;
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        $text = sprintf("[%s] PHP %s: %s in %s:%d\n", date('Y-m-d H:i:s'), (string) $severity, $message, $file, $line);
        @file_put_contents(cms_error_log_path(), $text, FILE_APPEND | LOCK_EX);

        return false;
    });

    set_exception_handler(static function (Throwable $e): void {
        cms_log_exception($e, 'uncaught');
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (ini_get('display_errors') === '1' || ini_get('display_errors') === 'On') {
            echo 'Внутренняя ошибка. Детали записаны в журнал.';
        }
        exit(1);
    });
}
