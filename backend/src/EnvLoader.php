<?php

namespace TransMore\Backend;

class EnvLoader
{
    public static function load(string $file): void
    {
        if (!file_exists($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if ($value !== '' && str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            if ($value !== '' && str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }

            if ($name === '') {
                continue;
            }

            $_ENV[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}
