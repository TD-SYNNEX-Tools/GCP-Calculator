<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Sku
{
    public function __construct(private readonly PDO $db) {}

    /** @var array<int, array|null> cache por requisicao para evitar buscas repetidas */
    private array $findCache = [];

    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM skus' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY price_usd_tb_year ASC';
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        if (array_key_exists($id, $this->findCache)) {
            return $this->findCache[$id];
        }
        $stmt = $this->db->prepare('SELECT TOP 1 * FROM skus WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $this->findCache[$id] = ($row ?: null);
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT TOP 1 * FROM skus WHERE name = :n');
        $stmt->execute(['n' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO skus (sku_code, name, plan_description, price_usd_tb_year, active)
             VALUES (:code, :name, :plan, :price, :active)'
        );
        $stmt->execute([
            'code'   => $data['sku_code'],
            'name'   => $data['name'],
            'plan'   => $data['plan_description'],
            'price'  => $data['price_usd_tb_year'],
            'active' => !empty($data['active']) ? 1 : 0,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE skus SET sku_code = :code, name = :name, plan_description = :plan,
             price_usd_tb_year = :price, active = :active WHERE id = :id'
        );
        $stmt->execute([
            'id'     => $id,
            'code'   => $data['sku_code'],
            'name'   => $data['name'],
            'plan'   => $data['plan_description'],
            'price'  => $data['price_usd_tb_year'],
            'active' => !empty($data['active']) ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM skus WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
