<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private readonly PDO $db) {}

    public function upsertFromAzure(string $oid, string $email, string $name): int
    {
        // MERGE = upsert no SQL Server (equivalente ao ON DUPLICATE KEY do MySQL).
        $stmt = $this->db->prepare(
            'MERGE users AS target
             USING (SELECT :oid AS azure_oid, :email AS email, :name AS name) AS src
                ON target.azure_oid = src.azure_oid
             WHEN MATCHED THEN
                UPDATE SET name = src.name, email = src.email
             WHEN NOT MATCHED THEN
                INSERT (azure_oid, email, name)
                VALUES (src.azure_oid, src.email, src.name);'
        );
        $stmt->execute(['oid' => $oid, 'email' => $email, 'name' => $name]);

        $found = $this->db->prepare('SELECT TOP 1 id FROM users WHERE azure_oid = :oid');
        $found->execute(['oid' => $oid]);
        return (int)$found->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT TOP 1 * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Lista todos os usuários, administradores primeiro. */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT id, azure_oid, email, name, role, created_at
               FROM users
              ORDER BY CASE role WHEN \'admin\' THEN 0 ELSE 1 END, name ASC'
        );
        return $stmt->fetchAll();
    }

    /** Retorna o perfil ('admin' ou 'user') do usuário. */
    public function getRole(int $id): string
    {
        $stmt = $this->db->prepare('SELECT TOP 1 role FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() === 'admin' ? 'admin' : 'user';
    }

    /** Define o perfil de acesso do usuário (fonte de verdade dos privilégios). */
    public function setRole(int $id, string $role): void
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            throw new \InvalidArgumentException('Perfil inválido.');
        }
        $stmt = $this->db->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $role, 'id' => $id]);
    }
}
