<?php /** @var array $proposal @var array $items */
$fmt = fn(float $v, string $ccy) => $ccy . ' ' . number_format($v, 2, ',', '.');
?>
<header class="page-head">
    <div>
        <h1>Proposta #<?= (int)$proposal['id'] ?></h1>
        <p class="muted">
            Gerada por <strong><?= htmlspecialchars($proposal['user_name']) ?></strong>
            em <?= htmlspecialchars(date('d/m/Y H:i', strtotime($proposal['created_at']))) ?>.
        </p>
    </div>
    <div style="display:flex; gap:8px">
        <a href="/proposals" class="btn btn-ghost">← Voltar</a>
        <a href="/proposals/<?= (int)$proposal['id'] ?>/edit" class="btn btn-ghost">✎ Editar</a>
        <a href="/proposals/<?= (int)$proposal['id'] ?>/pdf" target="_blank" rel="noopener" class="btn btn-primary">
            ⬇ Baixar PDF (TD SYNNEX)
        </a>
    </div>
</header>

<section class="card">
    <div class="grid-3 gap">
        <div><span class="muted">Revenda</span><strong><?= htmlspecialchars($proposal['reseller_name']) ?></strong></div>
        <div><span class="muted">Cliente Final</span><strong><?= htmlspecialchars($proposal['end_customer_name']) ?></strong></div>
        <div><span class="muted">Contrato</span><strong><?= (int)$proposal['contract_years'] ?> ano(s)</strong></div>
        <div><span class="muted">Tipo</span><strong><?= $proposal['pricing_type'] === 'STANDARD' ? 'Standard' : 'Non-Standard' ?></strong></div>
        <div><span class="muted">Deal Registration</span><strong><?= (int)$proposal['deal_registration'] ? 'Sim' : 'Não' ?></strong></div>
        <div><span class="muted">Dólar Google</span><strong>R$ <?= number_format((float)$proposal['dollar_rate'], 4, ',', '.') ?></strong></div>
    </div>

    <div class="discount-panel" style="margin-top:20px">
        <div class="pill pill-total"><span>Desconto Total</span><strong><?= number_format((float)$proposal['discount_total_pct'], 0) ?>%</strong></div>
        <div class="pill"><span>TD</span><strong><?= number_format((float)$proposal['discount_td_pct'], 0) ?>%</strong></div>
        <div class="pill"><span>Revenda</span><strong><?= number_format((float)$proposal['discount_reseller_pct'], 0) ?>%</strong></div>
    </div>
</section>

<section class="card">
    <h2 class="card-title">Itens</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Descrição do Produto</th>
                    <th class="num">Quantidade</th>
                    <th class="num">Custo Total Item (USD)</th>
                    <th class="num">Custo Mensal (BRL)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($i['sku_name']) ?></strong>
                            <div class="muted"><?= htmlspecialchars($i['plan_description']) ?></div>
                        </td>
                        <td class="num"><?= number_format((float)$i['tb_per_year'], 2, ',', '.') ?> TB/ano
                            <div class="muted"><?= number_format((float)$i['gb_per_year'], 2, ',', '.') ?> GB</div>
                        </td>
                        <td class="num"><?= $fmt((float)$i['net_total_usd'], 'USD') ?></td>
                        <td class="num"><?= $fmt((float)$i['monthly_brl'], 'BRL') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Totais</th>
                    <th class="num"></th>
                    <th class="num"><?= $fmt((float)$proposal['total_usd'], 'USD') ?></th>
                    <th class="num"><?= $fmt((float)$proposal['total_monthly_brl'], 'BRL') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
