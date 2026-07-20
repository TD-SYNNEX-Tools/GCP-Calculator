<?php
declare(strict_types=1);

namespace App\Controllers;

final class DashboardController extends BaseController
{
    public function home(): void
    {
        $this->requireAuth();
        $this->redirect('/proposals/create');
    }
}
