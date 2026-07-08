<?php
/**
 * Загрузка переменных из .env в корне проекта.
 */
if (function_exists('env')) {
    return;
}

function load_dotenv(string $root): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $name = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($name === '') {
            continue;
        }

        if (
            (isset($value[0]) && $value[0] === '"' && substr($value, -1) === '"')
            || (isset($value[0]) && $value[0] === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/**
 * @param mixed $default
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    $lower = strtolower((string) $value);
    if ($lower === 'true' || $lower === '(true)') {
        return true;
    }
    if ($lower === 'false' || $lower === '(false)') {
        return false;
    }
    if ($lower === 'null' || $lower === '(null)') {
        return null;
    }

    return $value;
}
