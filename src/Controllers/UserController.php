<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use PDO;

final class UserController extends BaseController
{
    public function __construct(private readonly PDO $db) {}

    /** Painel de administração de usuários (somente administradores). */
    public function index(): void
    {
        $this->requireAdmin();

        $current = Session::get('user');
        $this->view('admin/users', [
            'title'     => 'Administradores',
            'users'     => (new User($this->db))->all(),
            'logs'      => (new AuditLog($this->db))->recent(15),
            'csrf'      => Session::csrfToken(),
            'currentId' => (int)($current['id'] ?? 0),
        ]);
    }

    /** Promove ou remove privilégios de administrador de um usuário. */
    public function updateRole(array $params): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $userId = (int)($params['id'] ?? 0);
        $role   = (string)$this->input('role', '');

        if (!in_array($role, ['user', 'admin'], true)) {
            Session::flash('error', 'Perfil inválido.');
            $this->redirect('/admin/users');
            return;
        }

        // Impede que o administrador altere o próprio perfil, evitando
        // remoção acidental do próprio acesso (auto-lockout).
        $current   = Session::get('user');
        $currentId = (int)($current['id'] ?? 0);
        if ($userId === $currentId) {
            Session::flash('error', 'Por segurança, você não pode alterar o seu próprio perfil de acesso.');
            $this->redirect('/admin/users');
            return;
        }

        $model = new User($this->db);
        $target = $model->findById($userId);
        if ($target === null) {
            Session::flash('error', 'Usuário não encontrado.');
            $this->redirect('/admin/users');
            return;
        }

        $model->setRole($userId, $role);

        // Registra a alteração de privilégio na trilha de auditoria.
        (new AuditLog($this->db))->record(
            actorUserId:  $currentId ?: null,
            actorEmail:   (string)($current['email'] ?? 'desconhecido'),
            targetUserId: $userId,
            targetEmail:  (string)($target['email'] ?? ''),
            action:       $role === 'admin' ? 'grant' : 'revoke',
            ip:           $this->clientIp(),
        );

        Session::flash('success', $role === 'admin'
            ? 'Usuário promovido a administrador.'
            : 'Privilégios de administrador removidos.');
        $this->redirect('/admin/users');
    }

    /** Resolve o IP de origem, considerando proxies reversos (Azure App Service). */
    private function clientIp(): ?string
    {
        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            return trim(explode(',', $xff)[0]) ?: null;
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($remote) && $remote !== '' ? $remote : null;
    }
}
