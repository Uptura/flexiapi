<?php

if (!function_exists('loadEnv')) {
    function loadEnv($path)
    {
        if (!file_exists($path)) {
            throw new Exception(".env file not found at: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Split into key and value
            [$name, $value] = array_map('trim', explode('=', $line, 2));

            // Handle missing value
            if (!isset($value)) {
                $value = '';
            }

            // Remove quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Support variable expansion e.g. API_URL=http://${HOST}/api
            $value = preg_replace_callback('/\$\{?([A-Z0-9_]+)\}?/i', function ($matches) {
                $var = $matches[1];
                return getenv($var) ?: ($_ENV[$var] ?? '');
            }, $value);

            // Set variable if not already set
            if (!isset($_ENV[$name]) && !getenv($name)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
