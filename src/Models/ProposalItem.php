<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class ProposalItem
{
    public function __construct(private readonly PDO $db) {}

    public function create(int $proposalId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO proposal_items
                (proposal_id, sku_id, solution, version, tb_per_year, gb_per_year,
                 unit_price_usd, gross_total_usd, net_total_usd, monthly_brl)
             VALUES
                (:pid, :sid, :sol, :ver, :tb, :gb, :unit, :gross, :net, :monthly)'
        );
        $stmt->execute([
            'pid'     => $proposalId,
            'sid'     => (int)$data['sku_id'],
            'sol'     => $data['solution'] ?? 'SecOps',
            'ver'     => $data['version'],
            'tb'      => $data['tb_per_year'],
            'gb'      => $data['gb_per_year'],
            'unit'    => $data['unit_price_usd'],
            'gross'   => $data['gross_total_usd'],
            'net'     => $data['net_total_usd'],
            'monthly' => $data['monthly_brl'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteByProposal(int $proposalId): void
    {
        $stmt = $this->db->prepare('DELETE FROM proposal_items WHERE proposal_id = :pid');
        $stmt->execute(['pid' => $proposalId]);
    }

    public function listByProposal(int $proposalId): array
    {
        $stmt = $this->db->prepare(
            'SELECT pi.*, s.name AS sku_name, s.plan_description
             FROM proposal_items pi
             JOIN skus s ON s.id = pi.sku_id
             WHERE pi.proposal_id = :pid
             ORDER BY pi.id ASC'
        );
        $stmt->execute(['pid' => $proposalId]);
        return $stmt->fetchAll();
    }
}
