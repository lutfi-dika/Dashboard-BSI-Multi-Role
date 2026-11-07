<?php
/**
 * config/env.php
 * Loader sederhana untuk file .env (tanpa dependency composer).
 * Semua kredensial sensitif (DB, SMTP, token API) HARUS diisi lewat .env,
 * bukan hardcode di source code.
 */

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        static $loaded = false;

        if (!$loaded) {
            $envFile = __DIR__ . '/../.env';
            if (is_file($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Buang BOM UTF-8 (sering muncul di file yang disimpan dari Notepad)
                    $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));
                    if ($line === '' || strpos($line, '#') === 0) {
                        continue;
                    }
                    if (strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    // Buang quote pembungkus jika ada
                    if (strlen($v) >= 2 && (
                        ($v[0] === '"' && $v[-1] === '"') ||
                        ($v[0] === "'" && $v[-1] === "'")
                    )) {
                        $v = substr($v, 1, -1);
                    }
                    if (getenv($k) === false) {
                        putenv("$k=$v");
                    }
                    $_ENV[$k] = $v;
                }
            }
            $loaded = true;
        }

        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        return $value !== null && $value !== '' ? $value : $default;
    }
}
