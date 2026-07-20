<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Sku;
use App\Services\DiscountCalculator;
use App\Services\PdfService;
use App\Services\PricingService;
use App\Services\PricingType;
use PDO;
use Throwable;

final class ProposalController extends BaseController
{
    public function __construct(private readonly PDO $db) {}

    public function create(): void
    {
        $this->requireAuth();
        $skuModel = new Sku($this->db);
        $this->view('proposals/create', [
            'title' => 'Nova Proposta',
            'skus'  => $skuModel->all(true),
            'csrf'  => Session::csrfToken(),
        ]);
    }

    public function list(): void
    {
        $this->requireAuth();
        $model = new Proposal($this->db);
        $user  = Session::get('user');

        $filters = [
            'reseller'  => trim((string)$this->input('reseller', '')),
            'customer'  => trim((string)$this->input('customer', '')),
            'date_from' => trim((string)$this->input('date_from', '')),
            'date_to'   => trim((string)$this->input('date_to', '')),
            'user_id'   => (int)($user['id'] ?? 0),
        ];
        $this->view('proposals/list', [
            'title'     => 'Minhas Propostas',
            'subtitle'  => 'Propostas geradas por você.',
            'proposals' => $model->search($filters),
            'filters'   => $filters,
            'isAdmin'   => $this->isAdmin(),
            'scope'     => 'mine',
        ]);
    }

    /** Visão de administrador: todas as propostas do time. */
    public function adminList(): void
    {
        $this->requireAdmin();
        $model = new Proposal($this->db);

        $filters = [
            'reseller'  => trim((string)$this->input('reseller', '')),
            'customer'  => trim((string)$this->input('customer', '')),
            'date_from' => trim((string)$this->input('date_from', '')),
            'date_to'   => trim((string)$this->input('date_to', '')),
        ];
        $this->view('proposals/list', [
            'title'     => 'Todas as Propostas',
            'subtitle'  => 'Histórico completo das propostas geradas pelo time.',
            'proposals' => $model->search($filters),
            'stats'     => $model->stats($filters),
            'filters'   => $filters,
            'isAdmin'   => true,
            'scope'     => 'admin',
        ]);
    }

    /** Garante que o usuário pode acessar/editar a proposta (dono ou admin). */
    private function authorizeProposal(array $proposal): void
    {
        if ($this->isAdmin()) {
            return;
        }
        $user = Session::get('user');
        if ((int)$proposal['user_id'] !== (int)($user['id'] ?? 0)) {
            http_response_code(403);
            echo 'Você não tem permissão para acessar esta proposta.';
            exit;
        }
    }

    public function show(array $params): void
    {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $proposal = (new Proposal($this->db))->find($id);
        if (!$proposal) {
            http_response_code(404);
            echo 'Proposta não encontrada';
            return;
        }
        $this->authorizeProposal($proposal);
        $items = (new ProposalItem($this->db))->listByProposal($id);
        $this->view('proposals/show', [
            'title'    => 'Proposta #' . $id,
            'proposal' => $proposal,
            'items'    => $items,
            'canEdit'  => true,
        ]);
    }

    /** Formulário de edição (reaproveita a view de criação em modo edit). */
    public function edit(array $params): void
    {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $proposal = (new Proposal($this->db))->find($id);
        if (!$proposal) {
            http_response_code(404);
            echo 'Proposta não encontrada';
            return;
        }
        $this->authorizeProposal($proposal);
        $items    = (new ProposalItem($this->db))->listByProposal($id);
        $skuModel = new Sku($this->db);
        $this->view('proposals/create', [
            'title'    => 'Editar Proposta #' . $id,
            'skus'     => $skuModel->all(true),
            'csrf'     => Session::csrfToken(),
            'proposal' => $proposal,
            'items'    => $items,
            'mode'     => 'edit',
        ]);
    }

    /** Gera e faz o stream do PDF da proposta (padrão TD SYNNEX). */
    public function pdf(array $params): void
    {
        $this->requireAuth();
        $id = (int)($params['id'] ?? 0);
        $proposal = (new Proposal($this->db))->find($id);
        if (!$proposal) {
            http_response_code(404);
            echo 'Proposta não encontrada';
            return;
        }
        $this->authorizeProposal($proposal);
        $items = (new ProposalItem($this->db))->listByProposal($id);

        $created  = new \DateTimeImmutable($proposal['created_at']);
        $validity = new \DateTimeImmutable('2026-05-31');

        $pdf = (new PdfService())->renderFromTemplate(
            __DIR__ . '/../../views/proposals/pdf.php',
            [
                'proposal'   => $proposal,
                'items'      => $items,
                'issueDate'  => $created->format('d/m/Y'),
                'validUntil' => $validity->format('d/m/Y'),
                'fmt'        => static fn(float $v, string $ccy) => $ccy . ' ' . number_format($v, 2, ',', '.'),
            ]
        );

        $filename = sprintf(
            'proposta-tdsynnex-%06d-%s.pdf',
            $id,
            preg_replace('/[^a-z0-9]+/i', '-', strtolower($proposal['end_customer_name']))
        );

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf;
    }

    /** Endpoint JSON usado pelo front para pré-visualização em tempo real. */
    public function preview(): void
    {
        $this->requireAuth();

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $this->json(['error' => 'Payload inválido'], 400);
            return;
        }

        try {
            $type = PricingType::from((string)($payload['pricing_type'] ?? 'STANDARD'));
        } catch (Throwable) {
            $this->json(['error' => 'Tipo de precificação inválido'], 400);
            return;
        }

        $dr    = !empty($payload['deal_registration']);
        $years = max(1, min(5, (int)($payload['contract_years'] ?? 1)));
        $rate  = max(0.0, (float)($payload['dollar_rate'] ?? 0));
        $tb    = max(0.0, (float)($payload['tb_per_year'] ?? 0));
        $skuId = (int)($payload['sku_id'] ?? 0);

        $sku = (new Sku($this->db))->find($skuId);
        if (!$sku) {
            $this->json(['error' => 'SKU não encontrado'], 404);
            return;
        }

        $discount = (new DiscountCalculator())->calculate($type, $dr);
        $pricing  = (new PricingService())->computeItem(
            (float)$sku['price_usd_tb_year'], $tb, $years, $discount->totalPct, $rate
        );

        $this->json([
            'discount' => $discount->toArray(),
            'sku'      => [
                'id'    => (int)$sku['id'],
                'name'  => $sku['name'],
                'plan'  => $sku['plan_description'],
                'price' => (float)$sku['price_usd_tb_year'],
            ],
            'pricing'  => $pricing,
        ]);
    }

    /**
     * Valida o payload e calcula os dados da proposta e seus itens.
     * @throws \InvalidArgumentException para dados inválidos.
     */
    private function computeProposal(array $payload): array
    {
        $type = PricingType::from((string)($payload['pricing_type'] ?? 'STANDARD'));

        $reseller = trim((string)($payload['reseller_name'] ?? ''));
        $customer = trim((string)($payload['end_customer_name'] ?? ''));
        $years    = max(1, min(5, (int)($payload['contract_years'] ?? 1)));
        $rate     = (float)($payload['dollar_rate'] ?? 0);
        $dr       = !empty($payload['deal_registration']);
        $items    = $payload['items'] ?? [];

        // Desconto adicional sobre MSRP: aplicável somente para Non-Standard.
        $additional = $type === PricingType::NON_STANDARD
            ? max(0.0, min(100.0, (float)($payload['discount_additional'] ?? 0)))
            : 0.0;

        if ($reseller === '' || $customer === '' || $rate <= 0 || !is_array($items) || count($items) === 0) {
            throw new \InvalidArgumentException('Preencha todos os campos obrigatórios e adicione ao menos um item.');
        }

        $discount          = (new DiscountCalculator())->calculate($type, $dr);
        $effectiveTotalPct = min(100.0, $discount->totalPct + $additional);
        $pricing           = new PricingService();
        $skuModel          = new Sku($this->db);

        $rows        = [];
        $totalUsd    = 0.0;
        $totalMonBrl = 0.0;

        foreach ($items as $it) {
            $sku = $skuModel->find((int)($it['sku_id'] ?? 0));
            if (!$sku) {
                throw new \InvalidArgumentException('SKU inválido em um dos itens.');
            }
            $tb   = max(0.0, (float)($it['tb_per_year'] ?? 0));
            $calc = $pricing->computeItem((float)$sku['price_usd_tb_year'], $tb, $years, $effectiveTotalPct, $rate);

            $rows[] = [
                'sku_id'          => (int)$sku['id'],
                'solution'        => 'SecOps',
                'version'         => $sku['name'],
                'tb_per_year'     => $tb,
                'gb_per_year'     => $calc['gb_per_year'],
                'unit_price_usd'  => (float)$sku['price_usd_tb_year'],
                'gross_total_usd' => $calc['gross_usd'],
                'net_total_usd'   => $calc['net_usd'],
                'monthly_brl'     => $calc['monthly_brl'],
            ];

            $totalUsd    += $calc['net_usd'];
            $totalMonBrl += $calc['monthly_brl'];
        }

        return [
            'fields' => [
                'reseller_name'           => $reseller,
                'end_customer_name'       => $customer,
                'pricing_type'            => $type->value,
                'deal_registration'       => $dr,
                'contract_years'          => $years,
                'dollar_rate'             => $rate,
                'discount_total_pct'      => $discount->totalPct,
                'discount_td_pct'         => $discount->tdPct,
                'discount_reseller_pct'   => $discount->resellerPct,
                'discount_additional_pct' => $additional,
            ],
            'items'    => $rows,
            'totalUsd' => $totalUsd,
            'totalBrl' => $totalMonBrl,
        ];
    }

    /** Persiste uma nova proposta e todos os itens. */
    public function store(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $this->json(['error' => 'Payload inválido'], 400);
            return;
        }

        try {
            $data = $this->computeProposal($payload);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        } catch (Throwable) {
            $this->json(['error' => 'Tipo de precificação inválido'], 400);
            return;
        }

        $user          = Session::get('user');
        $proposalModel = new Proposal($this->db);
        $itemModel     = new ProposalItem($this->db);

        try {
            $this->db->beginTransaction();

            $proposalId = $proposalModel->create(
                array_merge($data['fields'], ['user_id' => (int)$user['id']])
            );
            foreach ($data['items'] as $row) {
                $itemModel->create($proposalId, $row);
            }
            $proposalModel->updateTotals($proposalId, $data['totalUsd'], $data['totalBrl']);

            $this->db->commit();
            $this->json(['id' => $proposalId, 'redirect' => '/proposals/' . $proposalId]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Proposal store: ' . $e->getMessage());
            $this->json(['error' => 'Não foi possível salvar a proposta.'], 500);
        }
    }

    /** Atualiza uma proposta existente e substitui seus itens. */
    public function update(array $params): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $id            = (int)($params['id'] ?? 0);
        $proposalModel = new Proposal($this->db);
        $existing      = $proposalModel->find($id);
        if (!$existing) {
            $this->json(['error' => 'Proposta não encontrada'], 404);
            return;
        }
        $this->authorizeProposal($existing);

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($payload)) {
            $this->json(['error' => 'Payload inválido'], 400);
            return;
        }

        try {
            $data = $this->computeProposal($payload);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 422);
            return;
        } catch (Throwable) {
            $this->json(['error' => 'Tipo de precificação inválido'], 400);
            return;
        }

        $itemModel = new ProposalItem($this->db);

        try {
            $this->db->beginTransaction();

            $proposalModel->update($id, $data['fields']);
            $itemModel->deleteByProposal($id);
            foreach ($data['items'] as $row) {
                $itemModel->create($id, $row);
            }
            $proposalModel->updateTotals($id, $data['totalUsd'], $data['totalBrl']);

            $this->db->commit();
            $this->json(['id' => $id, 'redirect' => '/proposals/' . $id]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Proposal update: ' . $e->getMessage());
            $this->json(['error' => 'Não foi possível atualizar a proposta.'], 500);
        }
    }
}
