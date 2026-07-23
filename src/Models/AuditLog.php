<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

/**
 * Trilha de auditoria das alterações de privilégio de administrador.
 * As gravações nunca interrompem a operação principal (falhas são apenas logadas).
 */
final class AuditLog
{
    private static bool $ensured = false;

    public function __construct(private readonly PDO $db) {}

    /** Cria a tabela de auditoria caso ainda não exista (idempotente). */
    private function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }
        try {
            $this->db->exec(
                "IF OBJECT_ID(N'dbo.admin_role_audit', N'U') IS NULL
                 BEGIN
                    CREATE TABLE dbo.admin_role_audit (
                        id             BIGINT IDENTITY(1,1) PRIMARY KEY,
                        actor_user_id  BIGINT NULL,
                        actor_email    NVARCHAR(255) NOT NULL,
                        target_user_id BIGINT NOT NULL,
                        target_email   NVARCHAR(255) NOT NULL,
                        action         NVARCHAR(10) NOT NULL
                                       CONSTRAINT ck_role_audit_action CHECK (action IN ('grant','revoke')),
                        ip_address     NVARCHAR(64) NULL,
                        created_at     DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                    );
                    CREATE INDEX idx_role_audit_created ON dbo.admin_role_audit (created_at DESC);
                 END;"
            );
            self::$ensured = true;
        } catch (Throwable $e) {
            error_log('AuditLog ensureTable: ' . $e->getMessage());
        }
    }

    /** Registra uma alteração de privilégio ('grant' concede, 'revoke' remove). */
    public function record(
        ?int $actorUserId,
        string $actorEmail,
        int $targetUserId,
        string $targetEmail,
        string $action,
        ?string $ip = null
    ): void {
        $this->ensureTable();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO admin_role_audit
                    (actor_user_id, actor_email, target_user_id, target_email, action, ip_address)
                 VALUES (:actor_id, :actor_email, :target_id, :target_email, :action, :ip)'
            );
            $stmt->execute([
                'actor_id'     => $actorUserId,
                'actor_email'  => $actorEmail,
                'target_id'    => $targetUserId,
                'target_email' => $targetEmail,
                'action'       => $action,
                'ip'           => $ip,
            ]);
        } catch (Throwable $e) {
            error_log('AuditLog record: ' . $e->getMessage());
        }
    }

    /** Retorna as alterações mais recentes para exibição no painel. */
    public function recent(int $limit = 50): array
    {
        $this->ensureTable();
        try {
            $limit = max(1, min(200, $limit));
            $stmt = $this->db->query(
                'SELECT TOP (' . $limit . ') actor_email, target_email, action, ip_address, created_at
                   FROM admin_role_audit
                  ORDER BY created_at DESC, id DESC'
            );
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('AuditLog recent: ' . $e->getMessage());
            return [];
        }
    }
}
