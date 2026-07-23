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

    public function search(array $filters = [], array $options = []): array
    {
        $params = [];
        $where  = $this->buildWhere($filters, $params);

        [$sortCol, $sortDir] = $this->resolveSort($options['sort'] ?? null, $options['dir'] ?? null);

        $sql = 'SELECT p.*, u.name AS user_name, u.email AS user_email
                FROM proposals p
                JOIN users u ON u.id = p.user_id'
                . $where
                . ' ORDER BY ' . $sortCol . ' ' . $sortDir . ', p.id DESC';

        // Paginação (SQL Server): OFFSET/FETCH com inteiros já sanitizados.
        if (!empty($options['per_page'])) {
            $perPage = max(1, (int)$options['per_page']);
            $page    = max(1, (int)($options['page'] ?? 1));
            $offset  = ($page - 1) * $perPage;
            $sql .= ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $perPage . ' ROWS ONLY';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Total de propostas que atendem aos filtros (para paginação). */
    public function count(array $filters = []): int
    {
        $params = [];
        $where  = $this->buildWhere($filters, $params);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM proposals p' . $where);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Traduz a chave de ordenação recebida (vinda da URL) para uma coluna
     * segura via whitelist, prevenindo SQL injection na cláusula ORDER BY.
     *
     * @return array{0:string,1:string} [coluna, direção]
     */
    private function resolveSort(?string $sort, ?string $dir): array
    {
        $map = [
            'id'          => 'p.id',
            'reseller'    => 'p.reseller_name',
            'customer'    => 'p.end_customer_name',
            'type'        => 'p.pricing_type',
            'years'       => 'p.contract_years',
            'total_usd'   => 'p.total_usd',
            'monthly_brl' => 'p.total_monthly_brl',
            'user'        => 'u.name',
            'created'     => 'p.created_at',
        ];
        $col = $map[$sort] ?? 'p.created_at';
        $dir = strtoupper((string)$dir) === 'ASC' ? 'ASC' : 'DESC';
        return [$col, $dir];
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
     * Métricas completas para o Dashboard executivo. Reúne indicadores de
     * performance (KPIs), série histórica dos últimos 12 meses, distribuição
     * por tipo/duração de contrato e rankings de usuários, SKUs e revendas.
     *
     * @return array{
     *   summary: array, trend: array, by_year: array,
     *   top_users: array, top_skus: array, top_resellers: array, recent: array
     * }
     */
    public function metrics(array $filters = []): array
    {
        $params = [];
        $where  = $this->buildWhere($filters, $params);

        // ---- KPIs consolidados do período ----
        $sql = 'SELECT
                    COUNT(*)                                                          AS total,
                    SUM(CASE WHEN p.pricing_type = \'STANDARD\' THEN 1 ELSE 0 END)      AS standard,
                    SUM(CASE WHEN p.pricing_type = \'NON_STANDARD\' THEN 1 ELSE 0 END)  AS non_standard,
                    SUM(CASE WHEN p.deal_registration = 1 THEN 1 ELSE 0 END)          AS deal_reg,
                    COUNT(DISTINCT p.user_id)                                         AS users,
                    COUNT(DISTINCT p.reseller_name)                                   AS resellers,
                    COUNT(DISTINCT p.end_customer_name)                               AS customers,
                    COALESCE(SUM(p.total_usd), 0)                                     AS total_usd,
                    COALESCE(SUM(p.total_monthly_brl), 0)                             AS total_brl,
                    COALESCE(AVG(NULLIF(p.discount_total_pct, 0)), 0)                 AS avg_discount,
                    COALESCE(AVG(p.total_usd), 0)                                     AS avg_ticket_usd,
                    COALESCE(MAX(p.total_usd), 0)                                     AS max_ticket_usd,
                    COALESCE(AVG(CAST(p.contract_years AS FLOAT)), 0)                 AS avg_years
                FROM proposals p' . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch() ?: [];

        // ---- Série histórica (últimos 12 meses) ----
        $sql = 'SELECT FORMAT(p.created_at, \'yyyy-MM\')      AS ym,
                       COUNT(*)                              AS total,
                       COALESCE(SUM(p.total_monthly_brl), 0) AS total_brl,
                       COALESCE(SUM(p.total_usd), 0)         AS total_usd
                FROM proposals p'
                . $where
                . ' AND p.created_at >= DATEADD(MONTH, -11,
                        DATEFROMPARTS(YEAR(SYSUTCDATETIME()), MONTH(SYSUTCDATETIME()), 1))
                    GROUP BY FORMAT(p.created_at, \'yyyy-MM\')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $trendRaw = [];
        foreach ($stmt->fetchAll() as $row) {
            $trendRaw[$row['ym']] = $row;
        }

        $trend  = [];
        $anchor = new \DateTimeImmutable('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $d  = $anchor->sub(new \DateInterval('P' . $i . 'M'));
            $ym = $d->format('Y-m');
            $trend[] = [
                'ym'        => $ym,
                'label'     => $d->format('m/y'),
                'total'     => (int)($trendRaw[$ym]['total'] ?? 0),
                'total_brl' => (float)($trendRaw[$ym]['total_brl'] ?? 0),
                'total_usd' => (float)($trendRaw[$ym]['total_usd'] ?? 0),
            ];
        }

        // ---- Distribuição por duração de contrato ----
        $sql = 'SELECT p.contract_years AS yrs, COUNT(*) AS total
                FROM proposals p' . $where
                . ' GROUP BY p.contract_years ORDER BY p.contract_years';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $byYear = $stmt->fetchAll();

        // ---- Ranking de usuários ----
        $sql = 'SELECT TOP 8 u.name AS user_name, u.email AS user_email,
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

        // ---- SKUs mais utilizados ----
        $sql = 'SELECT TOP 8 s.sku_code, s.name AS sku_name,
                       COUNT(pi.id)                       AS uses,
                       COUNT(DISTINCT p.id)               AS proposals,
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

        // ---- Ranking de revendas ----
        $sql = 'SELECT TOP 8 p.reseller_name,
                       COUNT(*)                              AS total,
                       COALESCE(SUM(p.total_monthly_brl), 0) AS total_brl
                FROM proposals p'
                . $where
                . ' GROUP BY p.reseller_name
                    ORDER BY total DESC, total_brl DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topResellers = $stmt->fetchAll();

        // ---- Propostas recentes ----
        $sql = 'SELECT TOP 8 p.id, p.reseller_name, p.end_customer_name, p.pricing_type,
                       p.deal_registration, p.contract_years, p.total_usd,
                       p.total_monthly_brl, p.created_at, u.name AS user_name
                FROM proposals p
                JOIN users u ON u.id = p.user_id'
                . $where
                . ' ORDER BY p.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $recent = $stmt->fetchAll();

        return [
            'summary'       => $summary,
            'trend'         => $trend,
            'by_year'       => $byYear,
            'top_users'     => $topUsers,
            'top_skus'      => $topSkus,
            'top_resellers' => $topResellers,
            'recent'        => $recent,
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
        if (!empty($filters['q'])) {
            // Busca global: revenda, cliente final ou número (#id) da proposta.
            $sql .= ' AND (p.reseller_name LIKE :q1 OR p.end_customer_name LIKE :q2 OR CAST(p.id AS NVARCHAR(20)) LIKE :q3)';
            $term = '%' . $filters['q'] . '%';
            $params['q1'] = $term;
            $params['q2'] = $term;
            $params['q3'] = $term;
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
