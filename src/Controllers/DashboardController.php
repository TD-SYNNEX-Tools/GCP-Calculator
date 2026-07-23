<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Proposal;
use PDO;

final class DashboardController extends BaseController
{
    public function __construct(private readonly ?PDO $db = null) {}

    /** Rota "/": encaminha admins para o dashboard executivo. */
    public function home(): void
    {
        $this->requireAuth();
        $this->redirect($this->isAdmin() ? '/dashboard' : '/proposals/create');
    }

    /** Dashboard executivo: visão consolidada do time (somente admin). */
    public function index(): void
    {
        $this->requireAdmin();

        $filters = [
            'reseller'  => trim((string)$this->input('reseller', '')),
            'customer'  => trim((string)$this->input('customer', '')),
            'date_from' => trim((string)$this->input('date_from', '')),
            'date_to'   => trim((string)$this->input('date_to', '')),
        ];

        $metrics = (new Proposal($this->db))->metrics($filters);

        $this->view('dashboard/index', [
            'title'    => 'Dashboard Executivo',
            'metrics'  => $metrics,
            'filters'  => $filters,
            'userName' => (string)(Session::get('user')['name'] ?? ''),
        ]);
    }
}
