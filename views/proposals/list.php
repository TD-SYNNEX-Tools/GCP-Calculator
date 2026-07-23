<?php /** @var array $proposals @var array $filters */
$fmt = fn(float $v, string $ccy) => $ccy . ' ' . number_format($v, 2, ',', '.');
$pageTitle = $title ?? 'Propostas';
$pageSubtitle = $subtitle ?? 'Histórico das propostas.';
$formAction = ($scope ?? 'mine') === 'admin' ? '/admin/proposals' : '/proposals';
$isAdminView = ($scope ?? 'mine') === 'admin' && !empty($stats);
$sort = $sort ?? 'created';
$dir  = $dir ?? 'desc';
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => count($proposals), 'from' => 0, 'to' => 0];

// Monta uma URL preservando os filtros/ordenacao atuais, aplicando overrides.
$qs = fn(array $overrides = []) => $formAction . '?' . http_build_query(array_merge([
    'q'         => $filters['q'] ?? '',
    'reseller'  => $filters['reseller'] ?? '',
    'customer'  => $filters['customer'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to'   => $filters['date_to'] ?? '',
    'sort'      => $sort,
    'dir'       => $dir,
], $overrides));

// Gera um cabeçalho de coluna clicável para ordenacao.
$sortTh = function (string $key, string $label, string $extraClass = '') use ($qs, $sort, $dir) {
    $active   = $sort === $key;
    $nextDir  = ($active && $dir === 'asc') ? 'desc' : 'asc';
    $arrow    = $active ? ($dir === 'asc' ? '▲' : '▼') : '';
    $cls      = 'th-sort' . ($active ? ' is-active' : '');
    $href     = htmlspecialchars($qs(['sort' => $key, 'dir' => $nextDir, 'page' => 1]));
    return '<th class="' . $extraClass . '"><a class="' . $cls . '" href="' . $href . '">'
         . htmlspecialchars($label) . '<span class="sort-ind">' . $arrow . '</span></a></th>';
};
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
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
        <label class="field search-field">
            <span>Busca global</span>
            <input type="search" name="q" placeholder="Revenda, cliente ou #número da proposta…" value="<?= htmlspecialchars($filters['q'] ?? '') ?>">
        </label>
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
            <a href="<?= htmlspecialchars($formAction) ?>" class="btn btn-ghost">Limpar</a>
        </div>
    </form>
</section>

<?php if ($isAdminView):
    $sm   = $stats['summary'];
    $total = (int)($sm['total'] ?? 0);
    $std   = (int)($sm['standard'] ?? 0);
    $non   = (int)($sm['non_standard'] ?? 0);
    $dr    = (int)($sm['deal_reg'] ?? 0);
    $users = (int)($sm['users'] ?? 0);
    $totalUsd = (float)($sm['total_usd'] ?? 0);
    $totalBrl = (float)($sm['total_brl'] ?? 0);
    $ticketUsd = $total > 0 ? $totalUsd / $total : 0;
    $pct   = fn(int $n) => $total > 0 ? round($n * 100 / $total) : 0;
    $stdPct = $pct($std);
    $nonPct = $total > 0 ? 100 - $stdPct : 0;
    $drPct  = $pct($dr);
    $periodo = trim(($filters['date_from'] ?? '') . ' — ' . ($filters['date_to'] ?? ''), ' —');
    $periodoLabel = $periodo !== '' ? $periodo : 'Todo o histórico';
    $maxUserTotal = 0; foreach (($stats['top_users'] ?? []) as $u) { $maxUserTotal = max($maxUserTotal, (int)$u['total']); }
    $maxSkuUses   = 0; foreach (($stats['top_skus'] ?? []) as $s) { $maxSkuUses = max($maxSkuUses, (int)$s['uses']); }
    $medals = ['gold', 'silver', 'bronze'];
?>
<section class="dash">
    <div class="dash-kpis">
        <article class="kpi kpi--blue">
            <div class="kpi-top">
                <span class="kpi-tag"><?= htmlspecialchars($periodoLabel) ?></span>
            </div>
            <span class="kpi-value"><?= number_format($total, 0, ',', '.') ?></span>
            <span class="kpi-label">Propostas no período</span>
            <div class="kpi-foot">
                <span class="dot dot-blue"></span> <?= $users ?> usuário<?= $users === 1 ? '' : 's' ?> ativo<?= $users === 1 ? '' : 's' ?>
            </div>
        </article>

        <article class="kpi kpi--green">
            <div class="kpi-top">
                <span class="kpi-tag">USD</span>
            </div>
            <span class="kpi-value"><?= $fmt($totalUsd, 'USD') ?></span>
            <span class="kpi-label">Volume total contratado</span>
            <div class="kpi-foot">
                <span class="dot dot-green"></span> Mensal: <?= $fmt($totalBrl, 'BRL') ?>
            </div>
        </article>

        <article class="kpi kpi--violet">
            <div class="kpi-top">
                <span class="kpi-tag">Média</span>
            </div>
            <span class="kpi-value"><?= $fmt($ticketUsd, 'USD') ?></span>
            <span class="kpi-label">Ticket médio por proposta</span>
            <div class="kpi-foot">
                <span class="dot dot-violet"></span> <?= $total ?> proposta<?= $total === 1 ? '' : 's' ?> somada<?= $total === 1 ? '' : 's' ?>
            </div>
        </article>

        <article class="kpi kpi--amber">
            <div class="kpi-top">
                <span class="kpi-tag"><?= $drPct ?>%</span>
            </div>
            <span class="kpi-value"><?= $dr ?></span>
            <span class="kpi-label">Com Deal Registration</span>
            <div class="gauge" role="img" aria-label="<?= $drPct ?>% com Deal Registration">
                <span class="gauge-fill" style="width: <?= $drPct ?>%"></span>
            </div>
        </article>
    </div>

    <section class="card mix-card">
        <div class="mix-head">
            <h2 class="card-title">Mix de precificação</h2>
            <span class="muted"><?= $total ?> proposta<?= $total === 1 ? '' : 's' ?> no período</span>
        </div>
        <?php if ($total > 0): ?>
        <div class="mix-bar" role="img" aria-label="Standard <?= $stdPct ?>%, Non-Standard <?= $nonPct ?>%">
            <?php if ($std > 0): ?><span class="mix-seg mix-seg--blue" style="width: <?= $stdPct ?>%"><?= $stdPct >= 8 ? $stdPct.'%' : '' ?></span><?php endif; ?>
            <?php if ($non > 0): ?><span class="mix-seg mix-seg--amber" style="width: <?= $nonPct ?>%"><?= $nonPct >= 8 ? $nonPct.'%' : '' ?></span><?php endif; ?>
        </div>
        <div class="mix-legend">
            <span class="legend-item"><span class="dot dot-blue"></span> Standard <strong><?= $std ?></strong> <span class="muted">(<?= $stdPct ?>%)</span></span>
            <span class="legend-item"><span class="dot dot-amber"></span> Non-Standard <strong><?= $non ?></strong> <span class="muted">(<?= $nonPct ?>%)</span></span>
            <span class="legend-item"><span class="dot dot-green"></span> Deal Registration <strong><?= $dr ?></strong> <span class="muted">(<?= $drPct ?>%)</span></span>
        </div>
        <?php else: ?>
        <p class="muted">Sem dados suficientes para o período selecionado.</p>
        <?php endif; ?>
    </section>

    <div class="stats-columns">
        <section class="card rank-card">
            <h2 class="card-title">Ranking — quem mais gerou</h2>
            <?php if (empty($stats['top_users'])): ?>
                <p class="muted">Sem dados no período.</p>
            <?php else: ?>
            <ul class="rank-list">
                <?php foreach ($stats['top_users'] as $i => $u):
                    $w = $maxUserTotal > 0 ? round((int)$u['total'] * 100 / $maxUserTotal) : 0;
                    $medal = $medals[$i] ?? '';
                ?>
                <li class="rank-item">
                    <span class="rank-pos <?= $medal ?>"><?= $i + 1 ?></span>
                    <div class="rank-body">
                        <div class="rank-line">
                            <strong class="rank-name"><?= htmlspecialchars($u['user_name']) ?></strong>
                            <span class="rank-metric"><?= (int)$u['total'] ?> <small>prop.</small></span>
                        </div>
                        <div class="rank-bar"><span style="width: <?= $w ?>%"></span></div>
                        <div class="rank-sub">
                            <span class="muted"><?= htmlspecialchars($u['user_email']) ?></span>
                            <span class="muted"><?= $fmt((float)$u['total_brl'], 'BRL') ?>/mês</span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="card rank-card">
            <h2 class="card-title">SKUs mais utilizados</h2>
            <?php if (empty($stats['top_skus'])): ?>
                <p class="muted">Sem dados no período.</p>
            <?php else: ?>
            <ul class="rank-list">
                <?php foreach ($stats['top_skus'] as $i => $s):
                    $w = $maxSkuUses > 0 ? round((int)$s['uses'] * 100 / $maxSkuUses) : 0;
                    $medal = $medals[$i] ?? '';
                ?>
                <li class="rank-item">
                    <span class="rank-pos <?= $medal ?>"><?= $i + 1 ?></span>
                    <div class="rank-body">
                        <div class="rank-line">
                            <strong class="rank-name"><?= htmlspecialchars($s['sku_name']) ?></strong>
                            <span class="rank-metric"><?= (int)$s['uses'] ?> <small>usos</small></span>
                        </div>
                        <div class="rank-bar rank-bar--violet"><span style="width: <?= $w ?>%"></span></div>
                        <div class="rank-sub">
                            <span class="muted"><?= htmlspecialchars($s['sku_code']) ?> · <?= (int)$s['proposals'] ?> prop.</span>
                            <span class="muted"><?= $fmt((float)$s['net_usd'], 'USD') ?></span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="table-wrap">
        <table class="data-table data-table--proposals">
            <thead>
                <tr>
                    <?= $sortTh('id', '#') ?>
                    <?= $sortTh('reseller', 'Revenda') ?>
                    <?= $sortTh('customer', 'Cliente Final') ?>
                    <?= $sortTh('type', 'Tipo') ?>
                    <?= $sortTh('years', 'Anos', 'num') ?>
                    <?= $sortTh('total_usd', 'Total (USD)', 'num') ?>
                    <?= $sortTh('monthly_brl', 'Mensal (BRL)', 'num') ?>
                    <?= $sortTh('user', 'Gerado por') ?>
                    <?= $sortTh('created', 'Data') ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proposals)): ?>
                    <tr class="empty-row"><td colspan="10">Nenhuma proposta encontrada.</td></tr>
                <?php else: foreach ($proposals as $p): ?>
                    <tr>
                        <td data-label="#">#<?= (int)$p['id'] ?></td>
                        <td data-label="Revenda"><?= htmlspecialchars($p['reseller_name']) ?></td>
                        <td data-label="Cliente Final"><?= htmlspecialchars($p['end_customer_name']) ?></td>
                        <td data-label="Tipo">
                            <span class="badge <?= $p['pricing_type'] === 'STANDARD' ? 'badge-blue' : 'badge-amber' ?>">
                                <?= $p['pricing_type'] === 'STANDARD' ? 'Standard' : 'Non-Standard' ?>
                            </span>
                            <?php if ((int)$p['deal_registration'] === 1): ?>
                                <span class="badge badge-green">DR</span>
                            <?php endif; ?>
                        </td>
                        <td class="num" data-label="Anos"><?= (int)$p['contract_years'] ?></td>
                        <td class="num" data-label="Total (USD)"><?= $fmt((float)$p['total_usd'], 'USD') ?></td>
                        <td class="num" data-label="Mensal (BRL)"><?= $fmt((float)$p['total_monthly_brl'], 'BRL') ?></td>
                        <td data-label="Gerado por">
                            <div class="user-cell">
                                <span class="avatar sm" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($p['user_name'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= htmlspecialchars($p['user_name']) ?></strong>
                                    <span class="muted"><?= htmlspecialchars($p['user_email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Data"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) ?></td>
                        <td data-label="" style="white-space:nowrap; text-align:right">
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>">Detalhes</a>
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>/edit">Editar</a>
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>/pdf" target="_blank" rel="noopener" title="Baixar PDF">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1 || ($pagination['total'] ?? 0) > 0): ?>
    <nav class="pagination" aria-label="Paginação">
        <span class="pagination-info">
            <?php if (($pagination['total'] ?? 0) > 0): ?>
                Mostrando <strong><?= (int)$pagination['from'] ?>–<?= (int)$pagination['to'] ?></strong> de <strong><?= (int)$pagination['total'] ?></strong>
            <?php else: ?>
                Nenhum resultado
            <?php endif; ?>
        </span>
        <?php if (($pagination['pages'] ?? 1) > 1):
            $cur = (int)$pagination['page'];
            $tot = (int)$pagination['pages'];
            $start = max(1, $cur - 2);
            $end   = min($tot, $cur + 2);
        ?>
        <div class="pagination-pages">
            <a class="page-btn <?= $cur <= 1 ? 'is-disabled' : '' ?>" <?= $cur <= 1 ? 'aria-disabled="true"' : 'href="' . htmlspecialchars($qs(['page' => $cur - 1])) . '"' ?>>‹ Anterior</a>
            <?php if ($start > 1): ?>
                <a class="page-btn" href="<?= htmlspecialchars($qs(['page' => 1])) ?>">1</a>
                <?php if ($start > 2): ?><span class="page-gap">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn <?= $i === $cur ? 'is-current' : '' ?>" href="<?= htmlspecialchars($qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($end < $tot): ?>
                <?php if ($end < $tot - 1): ?><span class="page-gap">…</span><?php endif; ?>
                <a class="page-btn" href="<?= htmlspecialchars($qs(['page' => $tot])) ?>"><?= $tot ?></a>
            <?php endif; ?>
            <a class="page-btn <?= $cur >= $tot ? 'is-disabled' : '' ?>" <?= $cur >= $tot ? 'aria-disabled="true"' : 'href="' . htmlspecialchars($qs(['page' => $cur + 1])) . '"' ?>>Próxima ›</a>
        </div>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>
