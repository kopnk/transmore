<?php

namespace TransMore\Backend;

class Config
{
    private static bool $loaded = false;

    public static function loadEnv(): void
    {
        if (self::$loaded) {
            return;
        }

        EnvLoader::load(__DIR__ . '/../.env');
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        self::loadEnv();
        $name = strtoupper($key);
        if (array_key_exists($name, $_ENV)) {
            return $_ENV[$name];
        }

        $value = getenv($name);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    public static function db(): array
    {
        return [
            'host' => self::get('DB_HOST', '127.0.0.1'),
            'port' => (int) self::get('DB_PORT', 3306),
            'name' => self::get('DB_NAME', 'transmore'),
            'user' => self::get('DB_USER', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

}
