<?php
/**
 * @var array $proposal
 * @var array $items
 * @var callable $fmt   fn(float $v, string $ccy) => string
 * @var string $issueDate
 * @var string $validUntil
 */

/** Embute imagem local como data-URI (evita problemas de caminho no Dompdf). */
$embed = static function (string $absPath): ?string {
    if (!is_file($absPath)) {
        return null;
    }
    $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'         => 'image/gif',
        'svg'         => 'image/svg+xml',
        default       => 'image/png',
    };
    return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($absPath));
};

$imgDir       = __DIR__ . '/../../public/assets/img';
$logoSecOps   = $embed($imgDir . '/logo.png');
$logoTdSynnex = $embed($imgDir . '/td-synnex.png');

$pricingLabel = $proposal['pricing_type'] === 'STANDARD' ? 'Standard' : 'Non-Standard';
$drLabel      = (int)$proposal['deal_registration'] === 1 ? 'Sim' : 'Não';
$proposalNo   = str_pad((string)$proposal['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Proposta #<?= $proposalNo ?></title>
<style>
    @page { margin: 112px 54px 72px 54px; }

    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        color: #2D3748;
        font-size: 10px;
        line-height: 1.5;
        margin: 0;
    }

    /* ---------- Header (todas as páginas) ---------- */
    header {
        position: fixed;
        top: -94px; left: 0; right: 0;
        height: 82px;
        padding: 20px 54px 0 54px;
    }
    header .brand-bar { display: table; width: 100%; }
    header .brand-bar .left,
    header .brand-bar .right {
        display: table-cell;
        vertical-align: middle;
    }
    header .brand-bar .right { text-align: right; }
    header .brand-bar img { max-height: 36px; max-width: 190px; width: auto; }
    header .brand-bar .td-synnex-logo { max-height: 52px; max-width: 250px; }
    header .brand-bar .wordmark {
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 2.5px;
        color: #1A1A1A;
    }
    header .brand-bar .wordmark small {
        display: block; font-size: 7.5px; letter-spacing: 3px;
        color: #6B7280; font-weight: 400; margin-top: 3px;
    }
    header .divider {
        border-top: 1px solid #E2E8F0;
        margin-top: 14px;
    }

    /* ---------- Footer (todas as páginas) ---------- */
    footer {
        position: fixed;
        bottom: -54px; left: 0; right: 0;
        height: 44px;
        padding: 10px 54px 0 54px;
        border-top: 1px solid #E2E8F0;
        font-size: 8.5px;
        color: #718096;
    }
    footer .row { display: table; width: 100%; }
    footer .row > div { display: table-cell; vertical-align: middle; width: 33.33%; }
    footer .row .center { text-align: center; }
    footer .row .right  { text-align: right; }
    .pagenum:before   { content: counter(page); }
    .pagecount:before { content: counter(pages); }

    /* ---------- Tipografia executiva ---------- */
    h1 { font-size: 22px; font-weight: 700; color: #102C4E; margin: 0; letter-spacing: -0.3px; }
    h2 {
        font-size: 10.5px; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase;
        color: #102C4E;
        margin: 28px 0 12px 0;
        padding-bottom: 6px;
        border-bottom: 1.5px solid #102C4E;
    }
    h2 .tick { display: none; }
    p { margin: 0; }
    .muted { color: #718096; }
    strong { font-weight: 700; }

    /* ---------- Cover ---------- */
    .cover {
        padding: 0 0 16px 0;
        border-bottom: 1px solid #E2E8F0;
        margin: 0 0 24px 0;
    }
    .cover {
        display: table;
        width: 100%;
    }
    .cover-left {
        display: table-cell;
        width: 60%;
        vertical-align: top;
    }
    .cover-right {
        display: table-cell;
        width: 40%;
        vertical-align: top;
        text-align: right;
    }
    .cover-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: #C79A3A; /* Acento dourado executivo, discreto */
        margin-bottom: 6px;
    }
    .cover-main-text {
        font-size: 24px;
        font-weight: 800;
        color: #102C4E;
        text-transform: uppercase;
        letter-spacing: 1px;
        line-height: 1;
        margin: 0 0 6px 0;
        padding-top: 8px;
        border-top: 2px solid #C79A3A;
        display: inline-block;
    }
    .cover-sub-text {
        font-size: 10px;
        color: #718096;
        margin: 0;
    }
    
    /* Introdução acolhedora */
    .intro-block {
        margin: 20px 0 10px 0;
        font-size: 11px;
        color: #2D3748;
    }
    .intro-block p {
        margin-bottom: 8px;
    }

    /* ---------- Grid chave/valor ---------- */
    .kv { width: 100%; border-collapse: collapse; }
    .kv td {
        padding: 6px 0;
        border-bottom: 1px solid #F0F0F0;
        font-size: 10.5px;
        vertical-align: top;
    }
    .kv td.k {
        width: 40%;
        color: #6B7280;
        text-transform: uppercase;
        font-size: 8.5px;
        letter-spacing: 1.5px;
        font-weight: 600;
        padding-right: 20px;
    }
    .kv td.v { color: #1A1A1A; font-weight: 500; }

    /* ---------- Descontos ---------- */
    .discount { width: 100%; border-collapse: collapse; margin-top: 2px; }
    .discount td { width: 33.33%; padding: 12px 8px; text-align: center; border-bottom: 2px solid #1D4E89; background: #F5F8FC; }
    .discount td + td { border-left: 1px solid #E2E8F0; }
    .discount td .lbl {
        display: block; font-size: 8.5px; letter-spacing: 1.5px;
        color: #5B6675; text-transform: uppercase; margin-bottom: 6px;
    }
    .discount td.total .lbl { color: #5B6675; }
    .discount td .val { font-size: 17px; font-weight: 700; color: #102C4E; }
    .discount td.total .val { font-size: 20px; color: #1D4E89; }

    /* ---------- Tabela de itens ---------- */
    .items { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .items thead th {
        text-align: left;
        font-size: 9px; font-weight: 700; letter-spacing: 1px;
        color: #FFFFFF; text-transform: uppercase;
        background: #102C4E;
        padding: 11px 12px;
    }
    .items thead th:first-child { border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
    .items thead th:last-child  { border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
    .items thead th.num { text-align: right; }
    .items tbody td {
        padding: 11px 12px;
        border-bottom: 1px solid #E2E8F0;
        vertical-align: middle;
        font-size: 10px;
        color: #2D3748;
    }
    .items tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .items tbody td .prod { font-weight: 700; color: #1A202C; }
    .items tbody td .sub  { color: #718096; font-size: 8.5px; margin-top: 2px; }
    
    /* Observação integrada na própria tabela estilo o exemplo J.E */
    .items .table-obs-row td {
        padding: 10px 12px;
        background: #F8FAFC;
        font-size: 9px;
        color: #4A5568;
        border-bottom: 1px solid #E2E8F0;
    }
    .items .table-obs-row strong { color: #1A202C; }

    .items tfoot td {
        padding: 12px;
        background: #102C4E;
        font-size: 11px;
        font-weight: 700; color: #FFFFFF;
        letter-spacing: 0.5px;
    }
    .items tfoot td.num { text-align: right; font-variant-numeric: tabular-nums; color: #FFFFFF; }

    /* ---------- Resumo financeiro ---------- */
    .totals { width: 100%; border-collapse: collapse; }
    .totals td { padding: 8px 0; font-size: 10px; border-bottom: 1px solid #E2E8F0; }
    .totals td.k {
        color: #4A5568; text-transform: uppercase;
        font-size: 8px; letter-spacing: 1px; font-weight: 700;
    }
    .totals td.v { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; color: #1A202C; }
    .totals { width: 64%; margin: 16px 0 0 36%; }
    .totals .grand td {
        border-top: 1px solid #1D4E89;
        border-bottom: 2px solid #1D4E89;
        background: #F5F8FC;
        padding: 11px 8px;
        font-size: 12px; font-weight: 700; color: #1D4E89;
    }
    .totals .grand td.k { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #1D4E89; }

    /* ---------- Detalhamento em 3 Cards horizontais estilo J.E ---------- */
    .blocks-container {
        display: table;
        width: 100%;
        margin-top: 14px;
    }
    .block-card {
        display: table-cell;
        width: 33.33%;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 12px;
        vertical-align: top;
    }
    .block-card + .block-card {
        padding-left: 12px; /* Dompdf table-cell gap hack */
    }
    /* Para criar o espaçamento real entre as células sem quebrar a renderização do Dompdf */
    .block-card-wrapper {
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        padding: 12px;
        min-height: 100px;
        background: #FFFFFF;
    }
    .block-card-inner {
        font-size: 9px;
        color: #4A5568;
        line-height: 1.4;
    }
    .block-title {
        font-size: 10px;
        font-weight: 700;
        color: #1A202C;
        text-align: center;
        margin-bottom: 8px;
        border-bottom: 1px solid #E2E8F0;
        padding-bottom: 4px;
    }

    /* ---------- Observações ---------- */
    .terms { padding: 0; }
    .terms ul { margin: 0 0 0 15px; padding: 0; }
    .terms li { margin-bottom: 4px; font-size: 9.5px; color: #4A5568; }
    .terms li strong { color: #1A202C; }

    /* ---------- Assinatura ---------- */
    .sign { width: 100%; margin-top: 40px; border-collapse: collapse; page-break-inside: avoid; }
    .sign td { width: 50%; padding: 0 32px; text-align: center; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #718096; padding-top: 8px; }
    .sign .name { font-size: 11px; font-weight: 700; color: #1A202C; }
    .sign .role { font-size: 8px; color: #718096; text-transform: uppercase; letter-spacing: 1px; margin-top: 3px; }
</style>
</head>
<body>

<header>
    <div class="brand-bar">
        <div class="left">
            <?php if ($logoSecOps): ?>
                <img src="<?= $logoSecOps ?>" alt="Google SecOps Calculator">
            <?php else: ?>
                <span class="wordmark">SECOPS<small>CALCULATOR</small></span>
            <?php endif; ?>
        </div>
        <div class="right">
            <?php if ($logoTdSynnex): ?>
                <img class="td-synnex-logo" src="<?= $logoTdSynnex ?>" alt="TD SYNNEX">
            <?php else: ?>
                <span class="wordmark" style="color:#102C4E">TD SYNNEX<small>Brasil</small></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="divider"></div>
</header>

<footer>
    <div class="row">
        <div>Proposta Nº <strong style="color:#1A1A1A">#<?= $proposalNo ?></strong></div>
        <div class="center">Gerada por <?= htmlspecialchars($proposal['user_name']) ?></div>
        <div class="right">Página <span class="pagenum"></span> de <span class="pagecount"></span></div>
    </div>
</footer>

<main>
    <!-- Capa Editorial baseada no modelo J.E -->
    <div class="cover">
        <div class="cover-left">
            <div class="cover-title">PROPOSTA</div>
            <div class="cover-main-text">COMERCIAL</div>
            <div class="cover-sub-text">Nº <?= $proposalNo ?> &ndash; <?= htmlspecialchars($issueDate) ?></div>
        </div>
        <div class="cover-right">
            <!-- Espaço reservado para manter o formato harmônico com o cabeçalho original -->
        </div>
    </div>

    <h2><span class="tick"></span>Detalhes da Proposta</h2>
    <table class="kv">
        <tr><td class="k">Revenda</td>              <td class="v"><?= htmlspecialchars($proposal['reseller_name']) ?></td></tr>
        <tr><td class="k">Cliente Final</td>        <td class="v"><?= htmlspecialchars($proposal['end_customer_name']) ?></td></tr>
        <tr><td class="k">Tipo de Precificação</td> <td class="v"><?= $pricingLabel ?></td></tr>
        <tr><td class="k">Deal Registration</td>    <td class="v"><?= $drLabel ?></td></tr>
        <tr><td class="k">Tempo de Contrato</td>    <td class="v"><?= (int)$proposal['contract_years'] ?> ano(s) · <?= (int)$proposal['contract_years'] * 12 ?> meses</td></tr>
        <tr><td class="k">Câmbio (Dólar Google)</td><td class="v">R$ <?= number_format((float)$proposal['dollar_rate'], 4, ',', '.') ?></td></tr>
    </table>

    <h2>Serviços propostos</h2>
    <table class="items">
        <thead>
            <tr>
                <th style="width:44%">Descrição do Produto</th>
                <th class="num" style="width:18%">Quantidade</th>
                <th class="num" style="width:19%">Custo Total (USD)</th>
                <th class="num" style="width:19%">Mensal (BRL)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td>
                    <div class="prod"><?= htmlspecialchars($i['sku_name']) ?></div>
                    <div class="sub"><?= htmlspecialchars($i['plan_description']) ?></div>
                    <?php if ((float)($proposal['discount_additional_pct'] ?? 0) > 0): ?>
                        <div class="sub">Desconto adicional (MSRP): <?= number_format((float)$proposal['discount_additional_pct'], 2, ',', '.') ?>%</div>
                    <?php endif; ?>
                </td>
                <td class="num">
                    <?= number_format((float)$i['tb_per_year'], 2, ',', '.') ?> TB/ano
                    <div class="sub"><?= number_format((float)$i['gb_per_year'], 2, ',', '.') ?> GB</div>
                </td>
                <td class="num"><?= $fmt((float)$i['net_total_usd'], 'USD') ?></td>
                <td class="num"><?= $fmt((float)$i['monthly_brl'], 'BRL') ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="table-obs-row">
                <td colspan="4">
                    <strong>Observação:</strong> O suporte técnico inicial, faturamento local via TD SYNNEX e monitoramento contínuo já estão inclusos nas condições desta proposta de licenciamento.
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align: right; padding-right: 12px; color: #FFFFFF; font-weight: 700;">Total</td>
                <td class="num"><?= $fmt((float)$proposal['total_usd'], 'USD') ?></td>
                <td class="num"><?= $fmt((float)$proposal['total_monthly_brl'], 'BRL') ?></td>
            </tr>
        </tfoot>
    </table>

    <h2 style="margin-top:28px;">Detalhamento da Proposta</h2>
    <div class="blocks-container">
        <div class="block-card">
            <div class="block-card-wrapper">
                <div class="block-title">Condições de Pagamento</div>
                <div class="block-card-inner">
                    - Faturamento local TD SYNNEX<br>
                    - Prazo padrão de <strong>30 DDL</strong><br>
                    - Faturamento direto opcional com repasse ajustado via parceiro
                </div>
            </div>
        </div>
        <div class="block-card" style="padding-left: 10px;">
            <div class="block-card-wrapper">
                <div class="block-title">Prazo de Validade</div>
                <div class="block-card-inner">
                    Esta proposta é válida até:<br>
                    <strong><?= htmlspecialchars($validUntil) ?></strong><br>
                    <span style="font-size:8px;color:#718096">*Sujeita a reparações em caso de oscilações cambiais extremas.</span>
                </div>
            </div>
        </div>
        <div class="block-card" style="padding-left: 10px;">
            <div class="block-card-wrapper">
                <div class="block-title">Contatos e Faturamento</div>
                <div class="block-card-inner">
                    - Atendimento Comercial TD SYNNEX<br>
                    - CNPJ: 28.268.233/0007-84<br>
                    - Suporte Engenharia Google Cloud
                </div>
            </div>
        </div>
    </div>

    <table class="sign">
        <tr>
            <td>
                <div class="line">
                    <div class="name"><?= htmlspecialchars($proposal['user_name']) ?></div>
                    <div class="role">TD SYNNEX Brasil</div>
                </div>
            </td>
            <td>
                <div class="line">
                    <div class="name"><?= htmlspecialchars($proposal['reseller_name']) ?></div>
                    <div class="role">Revenda</div>
                </div>
            </td>
        </tr>
    </table>
</main>

</body>
</html>
