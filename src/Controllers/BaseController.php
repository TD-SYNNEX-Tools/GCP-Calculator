<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;

abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = __DIR__ . '/../../views/' . $template . '.php';
        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo 'View não encontrada: ' . htmlspecialchars($template);
            return;
        }
        require __DIR__ . '/../../views/layouts/header.php';
        require $viewPath;
        require __DIR__ . '/../../views/layouts/footer.php';
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function requireAuth(): void
    {
        if (!Session::has('user')) {
            $this->redirect('/login');
        }
    }

    protected function isAdmin(): bool
    {
        $user = Session::get('user');
        return is_array($user) && !empty($user['is_admin']);
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo 'Acesso restrito a administradores.';
            exit;
        }
    }

    protected function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Session::verifyCsrf($token)) {
            http_response_code(419);
            echo 'CSRF token inválido';
            exit;
        }
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
