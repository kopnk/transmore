<?php

namespace TransMore\Backend;

use PDO;

class Auth
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function getUserById(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function normalizePhone(string $phone): string
    {
        return (string)preg_replace('/[\s().-]+/', '', trim($phone));
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $value=trim($identifier);
        return filter_var($value,FILTER_VALIDATE_EMAIL)?strtolower($value):self::normalizePhone($value);
    }

    public static function login(string $identifier, string $password): array
    {
        $db = Database::connect();
        $identifier=self::normalizeIdentifier($identifier);
        $stmt = $db->prepare('SELECT * FROM users WHERE (LOWER(email) = :email_identifier OR handphone_normalized = :phone_identifier) AND status = :status LIMIT 1');
        $stmt->execute([':email_identifier' => $identifier, ':phone_identifier' => $identifier, ':status' => 'Aktif']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !self::verifyPassword($password, $user['password_hash'])) {
            throw new \InvalidArgumentException('Invalid identifier or password');
        }

        return $user;
    }

    public static function createSession(int $userId): string
    {
        $id = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTime('+8 hours'))->format('Y-m-d H:i:s');
        $db = Database::connect();

        $stmt = $db->prepare('INSERT INTO sessions (id, user_id, data, created_at, expires_at) VALUES (:id, :user_id, :data, :created_at, :expires_at)');
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':data' => json_encode([]),
            ':created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ':expires_at' => $expiresAt,
        ]);

        return $id;
    }

    public static function getSession(string $sessionId): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM sessions WHERE id = :id AND expires_at > :now LIMIT 1');
        $stmt->execute([':id' => $sessionId, ':now' => (new \DateTime())->format('Y-m-d H:i:s')]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function destroySession(string $sessionId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $sessionId]);
    }
}
