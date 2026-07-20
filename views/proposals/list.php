<?php /** @var array $proposals @var array $filters */
$fmt = fn(float $v, string $ccy) => $ccy . ' ' . number_format($v, 2, ',', '.');
$pageTitle = $title ?? 'Propostas';
$pageSubtitle = $subtitle ?? 'Histórico das propostas.';
$formAction = ($scope ?? 'mine') === 'admin' ? '/admin/proposals' : '/proposals';
$isAdminView = ($scope ?? 'mine') === 'admin' && !empty($stats);
?>
<header class="page-head">
    <div>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="muted"><?= htmlspecialchars($pageSubtitle) ?></p>
    </div>
    <a href="/proposals/create" class="btn btn-primary">＋ Nova Proposta</a>
</header>

<section class="card">
    <form method="get" action="<?= $formAction ?>" class="filters">
        <label class="field">
            <span>Revenda</span>
            <input type="text" name="reseller" value="<?= htmlspecialchars($filters['reseller'] ?? '') ?>">
        </label>
        <label class="field">
            <span>Cliente</span>
            <input type="text" name="customer" value="<?= htmlspecialchars($filters['customer'] ?? '') ?>">
        </label>
        <label class="field">
            <span>De</span>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        </label>
        <label class="field">
            <span>Até</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        </label>
        <div class="filters-actions">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="/proposals" class="btn btn-ghost">Limpar</a>
        </div>
    </form>
</section>

<?php if ($isAdminView):
    $sm   = $stats['summary'];
    $total = (int)($sm['total'] ?? 0);
    $std   = (int)($sm['standard'] ?? 0);
    $non   = (int)($sm['non_standard'] ?? 0);
    $dr    = (int)($sm['deal_reg'] ?? 0);
    $pct   = fn(int $n) => $total > 0 ? round($n * 100 / $total) : 0;
    $periodo = trim(($filters['date_from'] ?? '') . ' — ' . ($filters['date_to'] ?? ''), ' —');
?>
<section class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Propostas no período</span>
        <span class="stat-value"><?= $total ?></span>
        <span class="muted"><?= $periodo !== '' ? htmlspecialchars($periodo) : 'Todo o histórico' ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Standard</span>
        <span class="stat-value"><?= $std ?> <small class="muted">(<?= $pct($std) ?>%)</small></span>
        <span class="muted">Non-Standard: <?= $non ?> (<?= $pct($non) ?>%)</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Com Deal Registration</span>
        <span class="stat-value"><?= $dr ?> <small class="muted">(<?= $pct($dr) ?>%)</small></span>
        <span class="muted">Usuários ativos: <?= (int)($sm['users'] ?? 0) ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Volume total</span>
        <span class="stat-value"><?= $fmt((float)($sm['total_usd'] ?? 0), 'USD') ?></span>
        <span class="muted">Mensal: <?= $fmt((float)($sm['total_brl'] ?? 0), 'BRL') ?></span>
    </div>
</section>

<div class="stats-columns">
    <section class="card">
        <h2 class="card-title">Ranking — quem mais gerou</h2>
        <div class="table-wrap">
            <table class="data-table compact">
                <thead>
                    <tr><th>#</th><th>Usuário</th><th class="num">Propostas</th><th class="num">Mensal (BRL)</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['top_users'])): ?>
                        <tr class="empty-row"><td colspan="4">Sem dados no período.</td></tr>
                    <?php else: foreach ($stats['top_users'] as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($u['user_name']) ?></strong><br>
                                <span class="muted"><?= htmlspecialchars($u['user_email']) ?></span>
                            </td>
                            <td class="num"><?= (int)$u['total'] ?></td>
                            <td class="num"><?= $fmt((float)$u['total_brl'], 'BRL') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title">SKUs mais utilizados</h2>
        <div class="table-wrap">
            <table class="data-table compact">
                <thead>
                    <tr><th>#</th><th>SKU</th><th class="num">Usos</th><th class="num">Propostas</th><th class="num">Líquido (USD)</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($stats['top_skus'])): ?>
                        <tr class="empty-row"><td colspan="5">Sem dados no período.</td></tr>
                    <?php else: foreach ($stats['top_skus'] as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($s['sku_name']) ?></strong><br>
                                <span class="muted"><?= htmlspecialchars($s['sku_code']) ?></span>
                            </td>
                            <td class="num"><?= (int)$s['uses'] ?></td>
                            <td class="num"><?= (int)$s['proposals'] ?></td>
                            <td class="num"><?= $fmt((float)$s['net_usd'], 'USD') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php endif; ?>

<section class="card">
    <div class="table-wrap">
        <table class="data-table data-table--proposals">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Revenda</th>
                    <th>Cliente Final</th>
                    <th>Tipo</th>
                    <th class="num">Anos</th>
                    <th class="num">Total (USD)</th>
                    <th class="num">Mensal (BRL)</th>
                    <th>Gerado por</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proposals)): ?>
                    <tr class="empty-row"><td colspan="10">Nenhuma proposta encontrada.</td></tr>
                <?php else: foreach ($proposals as $p): ?>
                    <tr>
                        <td>#<?= (int)$p['id'] ?></td>
                        <td><?= htmlspecialchars($p['reseller_name']) ?></td>
                        <td><?= htmlspecialchars($p['end_customer_name']) ?></td>
                        <td>
                            <span class="badge <?= $p['pricing_type'] === 'STANDARD' ? 'badge-blue' : 'badge-amber' ?>">
                                <?= $p['pricing_type'] === 'STANDARD' ? 'Standard' : 'Non-Standard' ?>
                            </span>
                            <?php if ((int)$p['deal_registration'] === 1): ?>
                                <span class="badge badge-green">DR</span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int)$p['contract_years'] ?></td>
                        <td class="num"><?= $fmt((float)$p['total_usd'], 'USD') ?></td>
                        <td class="num"><?= $fmt((float)$p['total_monthly_brl'], 'BRL') ?></td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar sm" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($p['user_name'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= htmlspecialchars($p['user_name']) ?></strong>
                                    <span class="muted"><?= htmlspecialchars($p['user_email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
                        <td style="white-space:nowrap; text-align:right">
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>">Detalhes</a>
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>/edit">Editar</a>
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>/pdf" target="_blank" rel="noopener" title="Baixar PDF">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
