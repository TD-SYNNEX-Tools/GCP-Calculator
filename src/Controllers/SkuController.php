<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Sku;
use PDO;

final class SkuController extends BaseController
{
    public function __construct(private readonly PDO $db) {}

    public function index(): void
    {
        $this->requireAuth();
        $skuModel = new Sku($this->db);
        $this->view('admin/skus', [
            'title'   => 'SKUs & Preços',
            'skus'    => $skuModel->all(false),
            'csrf'    => Session::csrfToken(),
            'canEdit' => $this->isAdmin(),
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $data = $this->collect();
        if ($data['sku_code'] === '' || $data['name'] === '' || $data['price_usd_tb_year'] <= 0) {
            Session::flash('error', 'Preencha SKU, nome e preço válidos.');
            $this->redirect('/admin/skus');
            return;
        }
        (new Sku($this->db))->create($data);
        Session::flash('success', 'SKU criado com sucesso.');
        $this->redirect('/admin/skus');
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $id = (int)($params['id'] ?? 0);
        (new Sku($this->db))->update($id, $this->collect());
        Session::flash('success', 'SKU atualizado.');
        $this->redirect('/admin/skus');
    }

    public function delete(array $params): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $id = (int)($params['id'] ?? 0);
        (new Sku($this->db))->delete($id);
        Session::flash('success', 'SKU removido.');
        $this->redirect('/admin/skus');
    }

    private function collect(): array
    {
        return [
            'sku_code'          => trim((string)($_POST['sku_code'] ?? '')),
            'name'              => trim((string)($_POST['name'] ?? '')),
            'plan_description'  => trim((string)($_POST['plan_description'] ?? '')),
            'price_usd_tb_year' => (float)($_POST['price_usd_tb_year'] ?? 0),
            'active'            => !empty($_POST['active']),
        ];
    }
}
