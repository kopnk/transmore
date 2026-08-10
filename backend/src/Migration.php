<?php

namespace TransMore\Backend;

use PDO;

class Migration
{
    public static function run(string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException("Migration file not found: $file");
        }

        $db = Database::connect();
        $statements = array_filter(array_map('trim', preg_split('/;\s*/', trim($sql))));

        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }

            try {
                $db->exec($statement);
            } catch (\PDOException $exception) {
                $code = $exception->errorInfo[1] ?? null;
                if (in_array($code, [1050, 1060, 1061, 1062], true)) {
                    // Ignore duplicate table/column/index errors during repeated migrations
                    continue;
                }

                throw $exception;
            }
        }
    }
}
