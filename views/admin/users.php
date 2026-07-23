<?php /** @var array $users @var array $logs @var string $csrf @var int $currentId */
$fmtDate = static function ($v): string {
    try {
        return $v ? (new DateTimeImmutable((string)$v))->format('d/m/Y') : '—';
    } catch (\Throwable) {
        return '—';
    }
};
$fmtDateTime = static function ($v): string {
    try {
        return $v ? (new DateTimeImmutable((string)$v))->format('d/m/Y H:i') : '—';
    } catch (\Throwable) {
        return '—';
    }
};
$logs = $logs ?? [];
$adminCount = 0;
foreach ($users as $u) {
    if (($u['role'] ?? 'user') === 'admin') {
        $adminCount++;
    }
}
?>
<header class="page-head">
    <div>
        <h1>Administradores</h1>
        <p class="muted">
            Gerencie quem tem acesso administrativo à plataforma. Promova ou remova
            privilégios de administrador diretamente por aqui — sem editar arquivos ou variáveis de ambiente.
        </p>
    </div>
    <span class="badge badge-blue"><?= $adminCount ?> admin(s) · <?= count($users) ?> usuário(s)</span>
</header>

<section class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Desde</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr class="empty-row"><td colspan="5">Nenhum usuário cadastrado.</td></tr>
            <?php else: foreach ($users as $u):
                $isAdmin = ($u['role'] ?? 'user') === 'admin';
                $isSelf  = (int)$u['id'] === $currentId;
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['name']) ?></strong>
                        <?php if ($isSelf): ?><span class="badge badge-blue" style="margin-left:6px">Você</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $isAdmin ? 'badge-green' : 'badge-amber' ?>">
                            <?= $isAdmin ? 'Administrador' : 'Usuário' ?>
                        </span>
                    </td>
                    <td><?= $fmtDate($u['created_at'] ?? null) ?></td>
                    <td class="actions">
                        <?php if ($isSelf): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/role" style="display:inline"
                                  data-confirm="<?= htmlspecialchars(($isAdmin
                                      ? 'Remover privilégios de administrador de '
                                      : 'Promover a administrador: ') . $u['name'] . '?', ENT_QUOTES) ?>">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="role" value="<?= $isAdmin ? 'user' : 'admin' ?>">
                                <button type="submit" class="btn btn-sm <?= $isAdmin ? 'btn-ghost text-danger' : 'btn-primary' ?>">
                                    <?= $isAdmin ? 'Remover admin' : 'Tornar admin' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2 class="card-title">Histórico de alterações de acesso</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Responsável</th>
                    <th>Ação</th>
                    <th>Usuário alvo</th>
                    <th>Origem (IP)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr class="empty-row"><td colspan="5">Nenhuma alteração de acesso registrada ainda.</td></tr>
            <?php else: foreach ($logs as $log):
                $granted = ($log['action'] ?? '') === 'grant';
            ?>
                <tr>
                    <td><?= $fmtDateTime($log['created_at'] ?? null) ?></td>
                    <td><?= htmlspecialchars($log['actor_email'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $granted ? 'badge-green' : 'badge-amber' ?>">
                            <?= $granted ? 'Promoveu a admin' : 'Removeu admin' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['target_email'] ?? '—') ?></td>
                    <td class="muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
