<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Proposal
{
    public function __construct(private readonly PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO proposals
                (user_id, reseller_name, end_customer_name, pricing_type, deal_registration,
                 contract_years, dollar_rate, discount_total_pct, discount_td_pct,
                 discount_reseller_pct, discount_additional_pct, total_usd, total_monthly_brl)
             VALUES
                (:uid, :res, :cli, :pt, :dr, :yrs, :dollar, :tot, :td, :rev, :add, :tusd, :tbrl)'
        );
        $stmt->execute([
            'uid'    => (int)$data['user_id'],
            'res'    => $data['reseller_name'],
            'cli'    => $data['end_customer_name'],
            'pt'     => $data['pricing_type'],
            'dr'     => !empty($data['deal_registration']) ? 1 : 0,
            'yrs'    => (int)$data['contract_years'],
            'dollar' => $data['dollar_rate'],
            'tot'    => $data['discount_total_pct'],
            'td'     => $data['discount_td_pct'],
            'rev'    => $data['discount_reseller_pct'],
            'add'    => $data['discount_additional_pct'] ?? 0,
            'tusd'   => $data['total_usd'] ?? 0,
            'tbrl'   => $data['total_monthly_brl'] ?? 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE proposals SET
                reseller_name           = :res,
                end_customer_name       = :cli,
                pricing_type            = :pt,
                deal_registration       = :dr,
                contract_years          = :yrs,
                dollar_rate             = :dollar,
                discount_total_pct      = :tot,
                discount_td_pct         = :td,
                discount_reseller_pct   = :rev,
                discount_additional_pct = :add
             WHERE id = :id'
        );
        $stmt->execute([
            'id'     => $id,
            'res'    => $data['reseller_name'],
            'cli'    => $data['end_customer_name'],
            'pt'     => $data['pricing_type'],
            'dr'     => !empty($data['deal_registration']) ? 1 : 0,
            'yrs'    => (int)$data['contract_years'],
            'dollar' => $data['dollar_rate'],
            'tot'    => $data['discount_total_pct'],
            'td'     => $data['discount_td_pct'],
            'rev'    => $data['discount_reseller_pct'],
            'add'    => $data['discount_additional_pct'] ?? 0,
        ]);
    }

    public function updateTotals(int $id, float $totalUsd, float $totalMonthlyBrl): void
    {
        $stmt = $this->db->prepare(
            'UPDATE proposals SET total_usd = :usd, total_monthly_brl = :brl WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'usd' => $totalUsd, 'brl' => $totalMonthlyBrl]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT TOP 1 p.*, u.name AS user_name, u.email AS user_email
             FROM proposals p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function search(array $filters = []): array
    {
        $params = [];
        $where  = $this->buildWhere($filters, $params);

        $sql = 'SELECT p.*, u.name AS user_name, u.email AS user_email
                FROM proposals p
                JOIN users u ON u.id = p.user_id'
                . $where
                . ' ORDER BY p.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Métricas agregadas para o dashboard administrativo, respeitando os
     * mesmos filtros da listagem (período, revenda, cliente).
     *
     * @return array{summary: array, by_type: array, top_users: array, top_skus: array}
     */
    public function stats(array $filters = []): array
    {
        $params = [];
        $where  = $this->buildWhere($filters, $params);

        // Resumo geral do período.
        $sql = 'SELECT
                    COUNT(*)                                                     AS total,
                    SUM(CASE WHEN p.pricing_type = \'STANDARD\' THEN 1 ELSE 0 END) AS standard,
                    SUM(CASE WHEN p.pricing_type = \'NON_STANDARD\' THEN 1 ELSE 0 END) AS non_standard,
                    SUM(CASE WHEN p.deal_registration = 1 THEN 1 ELSE 0 END)     AS deal_reg,
                    COUNT(DISTINCT p.user_id)                                    AS users,
                    COALESCE(SUM(p.total_usd), 0)                                AS total_usd,
                    COALESCE(SUM(p.total_monthly_brl), 0)                        AS total_brl
                FROM proposals p' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch() ?: [
            'total' => 0, 'standard' => 0, 'non_standard' => 0,
            'deal_reg' => 0, 'users' => 0, 'total_usd' => 0, 'total_brl' => 0,
        ];

        // Ranking de quem mais gerou propostas.
        $sql = 'SELECT TOP 10 u.name AS user_name, u.email AS user_email,
                       COUNT(*)                              AS total,
                       COALESCE(SUM(p.total_monthly_brl), 0) AS total_brl
                FROM proposals p
                JOIN users u ON u.id = p.user_id'
                . $where
                . ' GROUP BY p.user_id, u.name, u.email
                    ORDER BY total DESC, total_brl DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topUsers = $stmt->fetchAll();

        // SKUs mais utilizados no período.
        $sql = 'SELECT TOP 10 s.sku_code, s.name AS sku_name,
                       COUNT(pi.id)               AS uses,
                       COUNT(DISTINCT p.id)       AS proposals,
                       COALESCE(SUM(pi.net_total_usd), 0) AS net_usd
                FROM proposal_items pi
                JOIN proposals p ON p.id = pi.proposal_id
                JOIN skus s      ON s.id = pi.sku_id'
                . $where
                . ' GROUP BY pi.sku_id, s.sku_code, s.name
                    ORDER BY uses DESC, net_usd DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topSkus = $stmt->fetchAll();

        return [
            'summary'   => $summary,
            'top_users' => $topUsers,
            'top_skus'  => $topSkus,
        ];
    }

    /**
     * Monta a cláusula WHERE compartilhada entre listagem e métricas.
     * Referencia apenas colunas de "p" (proposals) para permitir reuso.
     */
    private function buildWhere(array $filters, array &$params): string
    {
        $sql = ' WHERE 1=1';

        if (!empty($filters['user_id'])) {
            $sql .= ' AND p.user_id = :uid';
            $params['uid'] = (int)$filters['user_id'];
        }
        if (!empty($filters['reseller'])) {
            $sql .= ' AND p.reseller_name LIKE :res';
            $params['res'] = '%' . $filters['reseller'] . '%';
        }
        if (!empty($filters['customer'])) {
            $sql .= ' AND p.end_customer_name LIKE :cli';
            $params['cli'] = '%' . $filters['customer'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND CAST(p.created_at AS DATE) >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND CAST(p.created_at AS DATE) <= :dt';
            $params['dt'] = $filters['date_to'];
        }

        return $sql;
    }
}
