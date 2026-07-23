<?php
/**
 * Dashboard Executivo — visão consolidada do time.
 * @var array $metrics @var array $filters @var string $userName
 */
$sm    = $metrics['summary'] ?? [];
$trend = $metrics['trend'] ?? [];

$total = (int)($sm['total'] ?? 0);
$std   = (int)($sm['standard'] ?? 0);
$non   = (int)($sm['non_standard'] ?? 0);
$dr    = (int)($sm['deal_reg'] ?? 0);

$pct    = fn(int $n) => $total > 0 ? round($n * 100 / $total) : 0;
$money  = fn(float $v, string $ccy) => $ccy . ' ' . number_format($v, 2, ',', '.');
$compact = function (float $v, string $ccy): string {
    $abs = abs($v);
    if ($abs >= 1_000_000) { return $ccy . ' ' . number_format($v / 1_000_000, 1, ',', '.') . 'M'; }
    if ($abs >= 1_000)     { return $ccy . ' ' . number_format($v / 1_000, 1, ',', '.') . 'k'; }
    return $ccy . ' ' . number_format($v, 0, ',', '.');
};

$periodo = trim(($filters['date_from'] ?? '') . ' — ' . ($filters['date_to'] ?? ''), ' —');
$firstName = trim(explode(' ', trim($userName))[0] ?? '');

// ---- Geometria do gráfico de barras (SVG, gerado no servidor) ----
$maxBrl = 0.0;
foreach ($trend as $t) { $maxBrl = max($maxBrl, (float)$t['total_brl']); }
$vbW = 1200; $vbH = 320; $padL = 8; $padR = 8; $padB = 46; $padT = 30;
$plotW = $vbW - $padL - $padR;
$plotH = $vbH - $padT - $padB;
$slots = max(1, count($trend));
$slotW = $plotW / $slots;
$barW  = min(64, $slotW * 0.56);
?>
<header class="page-head dash-head">
    <div>
        <h1>Dashboard Executivo</h1>
        <p class="muted">
            <?= $firstName !== '' ? 'Olá, ' . htmlspecialchars($firstName) . '. ' : '' ?>
            Visão consolidada do time · <?= $periodo !== '' ? htmlspecialchars($periodo) : 'todo o histórico' ?>
        </p>
    </div>
    <a href="/proposals/create" class="btn btn-primary">＋ Nova Proposta</a>
</header>

<section class="card dash-filters">
    <form method="get" action="/dashboard" class="filters">
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
            <button type="submit" class="btn btn-primary">Aplicar</button>
            <a href="/dashboard" class="btn btn-ghost">Limpar</a>
        </div>
    </form>
</section>

<!-- ================= KPIs ================= -->
<section class="kpi-grid">
    <div class="kpi kpi--primary">
        <span class="kpi-label">Propostas</span>
        <span class="kpi-value"><?= number_format($total, 0, ',', '.') ?></span>
        <span class="kpi-foot"><?= (int)($sm['users'] ?? 0) ?> usuários · <?= (int)($sm['customers'] ?? 0) ?> clientes</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Volume total (USD)</span>
        <span class="kpi-value"><?= $compact((float)($sm['total_usd'] ?? 0), 'US$') ?></span>
        <span class="kpi-foot"><?= $money((float)($sm['total_usd'] ?? 0), 'USD') ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Recorrente mensal (BRL)</span>
        <span class="kpi-value"><?= $compact((float)($sm['total_brl'] ?? 0), 'R$') ?></span>
        <span class="kpi-foot"><?= $money((float)($sm['total_brl'] ?? 0), 'BRL') ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Ticket médio (USD)</span>
        <span class="kpi-value"><?= $compact((float)($sm['avg_ticket_usd'] ?? 0), 'US$') ?></span>
        <span class="kpi-foot">Maior: <?= $compact((float)($sm['max_ticket_usd'] ?? 0), 'US$') ?></span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Desconto médio</span>
        <span class="kpi-value"><?= number_format((float)($sm['avg_discount'] ?? 0), 1, ',', '.') ?>%</span>
        <span class="kpi-foot">Contrato médio: <?= number_format((float)($sm['avg_years'] ?? 0), 1, ',', '.') ?> anos</span>
    </div>
    <div class="kpi">
        <span class="kpi-label">Deal Registration</span>
        <span class="kpi-value"><?= $pct($dr) ?>%</span>
        <span class="kpi-foot"><?= $dr ?> de <?= $total ?> propostas</span>
    </div>
</section>

<!-- ================= Tendência ================= -->
<section class="card chart-card">
    <div class="chart-head">
        <h2 class="card-title" style="border:0;padding:0;margin:0">Evolução — últimos 12 meses</h2>
        <div class="chart-legend">
            <span class="lg lg-bar"></span> Recorrente mensal (BRL)
            <span class="lg lg-dot"></span> Nº de propostas
        </div>
    </div>
    <?php if ($maxBrl <= 0): ?>
        <p class="muted" style="padding:32px 0;text-align:center">Sem dados no período selecionado.</p>
    <?php else: ?>
    <svg class="chart-svg" viewBox="0 0 <?= $vbW ?> <?= $vbH ?>" preserveAspectRatio="none" role="img"
         aria-label="Recorrente mensal por mês nos últimos 12 meses">
        <defs>
            <linearGradient id="barGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#1D4E89"/>
                <stop offset="100%" stop-color="#163E6E"/>
            </linearGradient>
        </defs>
        <?php // linhas de grade
        for ($g = 0; $g <= 3; $g++):
            $gy = $padT + $plotH - ($plotH * $g / 3);
            $gv = $maxBrl * $g / 3; ?>
            <line x1="<?= $padL ?>" y1="<?= round($gy, 1) ?>" x2="<?= $vbW - $padR ?>" y2="<?= round($gy, 1) ?>"
                  stroke="#E1E5EC" stroke-width="1"/>
            <text x="<?= $padL + 4 ?>" y="<?= round($gy - 5, 1) ?>" fill="#9AA5B4" font-size="16"><?= $compact($gv, 'R$') ?></text>
        <?php endfor; ?>
        <?php foreach ($trend as $i => $t):
            $val = (float)$t['total_brl'];
            $h   = $maxBrl > 0 ? ($plotH * $val / $maxBrl) : 0;
            $x   = $padL + $i * $slotW + ($slotW - $barW) / 2;
            $y   = $padT + $plotH - $h;
            $cx  = $x + $barW / 2; ?>
            <?php if ($h > 0): ?>
            <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($barW, 1) ?>" height="<?= round($h, 1) ?>"
                  rx="4" fill="url(#barGrad)"><title><?= htmlspecialchars($t['label']) ?>: <?= $money($val, 'BRL') ?> · <?= (int)$t['total'] ?> propostas</title></rect>
            <?php endif; ?>
            <?php if ((int)$t['total'] > 0): ?>
            <circle cx="<?= round($cx, 1) ?>" cy="<?= round($y - 12, 1) ?>" r="12" fill="#C79A3A"/>
            <text x="<?= round($cx, 1) ?>" y="<?= round($y - 7, 1) ?>" fill="#fff" font-size="15" font-weight="700"
                  text-anchor="middle"><?= (int)$t['total'] ?></text>
            <?php endif; ?>
            <text x="<?= round($cx, 1) ?>" y="<?= $vbH - 16 ?>" fill="#5B6675" font-size="16"
                  text-anchor="middle"><?= htmlspecialchars($t['label']) ?></text>
        <?php endforeach; ?>
    </svg>
    <?php endif; ?>
</section>

<!-- ================= Distribuições ================= -->
<div class="dash-cols">
    <section class="card">
        <h2 class="card-title">Mix de precificação</h2>
        <div class="meter-row">
            <div class="meter-head"><span>Standard</span><strong><?= $std ?> · <?= $pct($std) ?>%</strong></div>
            <div class="meter"><span class="meter-fill meter-blue" style="width:<?= $pct($std) ?>%"></span></div>
        </div>
        <div class="meter-row">
            <div class="meter-head"><span>Non-Standard</span><strong><?= $non ?> · <?= $pct($non) ?>%</strong></div>
            <div class="meter"><span class="meter-fill meter-amber" style="width:<?= $pct($non) ?>%"></span></div>
        </div>
        <div class="meter-row">
            <div class="meter-head"><span>Com Deal Registration</span><strong><?= $dr ?> · <?= $pct($dr) ?>%</strong></div>
            <div class="meter"><span class="meter-fill meter-green" style="width:<?= $pct($dr) ?>%"></span></div>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title">Duração de contrato</h2>
        <?php
        $byYear = $metrics['by_year'] ?? [];
        $maxYearCount = 0;
        foreach ($byYear as $y) { $maxYearCount = max($maxYearCount, (int)$y['total']); }
        if (empty($byYear)): ?>
            <p class="muted">Sem dados no período.</p>
        <?php else: foreach ($byYear as $y):
            $c = (int)$y['total'];
            $w = $maxYearCount > 0 ? round($c * 100 / $maxYearCount) : 0; ?>
            <div class="meter-row">
                <div class="meter-head">
                    <span><?= (int)$y['yrs'] ?> <?= (int)$y['yrs'] === 1 ? 'ano' : 'anos' ?></span>
                    <strong><?= $c ?> · <?= $pct($c) ?>%</strong>
                </div>
                <div class="meter"><span class="meter-fill meter-navy" style="width:<?= $w ?>%"></span></div>
            </div>
        <?php endforeach; endif; ?>
    </section>
</div>

<!-- ================= Rankings ================= -->
<div class="dash-cols">
    <section class="card">
        <h2 class="card-title">Top consultores</h2>
        <div class="table-wrap">
            <table class="data-table compact">
                <thead><tr><th>#</th><th>Consultor</th><th class="num">Propostas</th><th class="num">Mensal (BRL)</th></tr></thead>
                <tbody>
                    <?php if (empty($metrics['top_users'])): ?>
                        <tr class="empty-row"><td colspan="4">Sem dados no período.</td></tr>
                    <?php else: foreach ($metrics['top_users'] as $i => $u): ?>
                        <tr>
                            <td><span class="rank rank-<?= $i < 3 ? $i + 1 : 'n' ?>"><?= $i + 1 ?></span></td>
                            <td><strong><?= htmlspecialchars($u['user_name']) ?></strong><br>
                                <span class="muted"><?= htmlspecialchars($u['user_email']) ?></span></td>
                            <td class="num"><?= (int)$u['total'] ?></td>
                            <td class="num"><?= $money((float)$u['total_brl'], 'BRL') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title">Top revendas</h2>
        <div class="table-wrap">
            <table class="data-table compact">
                <thead><tr><th>#</th><th>Revenda</th><th class="num">Propostas</th><th class="num">Mensal (BRL)</th></tr></thead>
                <tbody>
                    <?php if (empty($metrics['top_resellers'])): ?>
                        <tr class="empty-row"><td colspan="4">Sem dados no período.</td></tr>
                    <?php else: foreach ($metrics['top_resellers'] as $i => $r): ?>
                        <tr>
                            <td><span class="rank rank-<?= $i < 3 ? $i + 1 : 'n' ?>"><?= $i + 1 ?></span></td>
                            <td><strong><?= htmlspecialchars($r['reseller_name']) ?></strong></td>
                            <td class="num"><?= (int)$r['total'] ?></td>
                            <td class="num"><?= $money((float)$r['total_brl'], 'BRL') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="card">
    <h2 class="card-title">SKUs mais utilizados</h2>
    <div class="table-wrap">
        <table class="data-table compact">
            <thead><tr><th>#</th><th>SKU</th><th class="num">Usos</th><th class="num">Propostas</th><th class="num">Líquido (USD)</th></tr></thead>
            <tbody>
                <?php if (empty($metrics['top_skus'])): ?>
                    <tr class="empty-row"><td colspan="5">Sem dados no período.</td></tr>
                <?php else: foreach ($metrics['top_skus'] as $i => $s): ?>
                    <tr>
                        <td><span class="rank rank-<?= $i < 3 ? $i + 1 : 'n' ?>"><?= $i + 1 ?></span></td>
                        <td><strong><?= htmlspecialchars($s['sku_name']) ?></strong><br>
                            <span class="muted"><?= htmlspecialchars($s['sku_code']) ?></span></td>
                        <td class="num"><?= (int)$s['uses'] ?></td>
                        <td class="num"><?= (int)$s['proposals'] ?></td>
                        <td class="num"><?= $money((float)$s['net_usd'], 'USD') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2 class="card-title">Propostas recentes</h2>
    <div class="table-wrap">
        <table class="data-table compact">
            <thead><tr><th>#</th><th>Cliente</th><th>Revenda</th><th>Tipo</th><th>Consultor</th><th class="num">Mensal (BRL)</th><th>Data</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($metrics['recent'])): ?>
                    <tr class="empty-row"><td colspan="8">Nenhuma proposta encontrada.</td></tr>
                <?php else: foreach ($metrics['recent'] as $p): ?>
                    <tr>
                        <td>#<?= (int)$p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['end_customer_name']) ?></strong></td>
                        <td><?= htmlspecialchars($p['reseller_name']) ?></td>
                        <td>
                            <span class="badge <?= $p['pricing_type'] === 'STANDARD' ? 'badge-blue' : 'badge-amber' ?>">
                                <?= $p['pricing_type'] === 'STANDARD' ? 'Standard' : 'Non-Std' ?>
                            </span>
                            <?php if ((int)$p['deal_registration'] === 1): ?><span class="badge badge-green">DR</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['user_name']) ?></td>
                        <td class="num"><?= $money((float)$p['total_monthly_brl'], 'BRL') ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($p['created_at']))) ?></td>
                        <td style="text-align:right;white-space:nowrap">
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>">Ver</a>
                            <a class="btn btn-ghost btn-sm" href="/proposals/<?= (int)$p['id'] ?>/pdf" target="_blank" rel="noopener">PDF</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
