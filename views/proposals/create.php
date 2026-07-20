<?php /** @var array $skus @var string $csrf */
$mode   = $mode ?? 'create';
$isEdit = $mode === 'edit';
$p      = $proposal ?? [];
$editItems = $items ?? [];
$pricingType = $p['pricing_type'] ?? 'STANDARD';
$years  = (string)($p['contract_years'] ?? '3');
$dr     = !empty($p['deal_registration']);
$dollar = $p['dollar_rate'] ?? '5.0000';
$addDisc = $p['discount_additional_pct'] ?? '0';
$sel = fn($a, $b) => ((string)$a === (string)$b) ? 'selected' : '';
?>
<header class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar Proposta #' . (int)$p['id'] : 'Nova Proposta' ?></h1>
        <p class="muted"><?= $isEdit
            ? 'Ajuste os dados e salve as alterações.'
            : 'Preencha os dados, adicione itens e visualize o cálculo em tempo real.' ?></p>
    </div>
    <div style="display:flex; gap:8px">
        <?php if ($isEdit): ?>
            <a href="/proposals/<?= (int)$p['id'] ?>" class="btn btn-ghost">Cancelar</a>
        <?php else: ?>
            <button type="button" id="btn-reset" class="btn btn-ghost">Resetar proposta</button>
        <?php endif; ?>
        <button type="button" id="btn-save" class="btn btn-primary" disabled><?= $isEdit ? 'Salvar alterações' : 'Salvar proposta' ?></button>
    </div>
</header>

<form id="proposal-form" data-csrf="<?= htmlspecialchars($csrf) ?>" data-mode="<?= $isEdit ? 'edit' : 'create' ?>"<?php if ($isEdit): ?> data-update-url="/proposals/<?= (int)$p['id'] ?>/update"<?php endif; ?> autocomplete="off">
    <!-- ------ Dados gerais ------ -->
    <section class="card">
        <h2 class="card-title">Dados da Proposta</h2>

        <div class="form-grid form-grid-3">
            <label class="field">
                <span>Nome da Revenda</span>
                <input type="text" name="reseller_name" value="<?= htmlspecialchars((string)($p['reseller_name'] ?? '')) ?>" required>
            </label>
            <label class="field">
                <span>Cliente Final</span>
                <input type="text" name="end_customer_name" value="<?= htmlspecialchars((string)($p['end_customer_name'] ?? '')) ?>" required>
            </label>
            <label class="field">
                <span>Tipo de Precificação</span>
                <select name="pricing_type" required>
                    <option value="STANDARD" <?= $sel('STANDARD', $pricingType) ?>>Standard</option>
                    <option value="NON_STANDARD" <?= $sel('NON_STANDARD', $pricingType) ?>>Non-Standard</option>
                </select>
            </label>

            <label class="field">
                <span>Tempo de Contrato</span>
                <select name="contract_years" required>
                    <option value="1" <?= $sel('1', $years) ?>>1 ano</option>
                    <option value="2" <?= $sel('2', $years) ?>>2 anos</option>
                    <option value="3" <?= $sel('3', $years) ?>>3 anos</option>
                    <option value="4" <?= $sel('4', $years) ?>>4 anos</option>
                    <option value="5" <?= $sel('5', $years) ?>>5 anos</option>
                </select>
            </label>

            <label class="field">
                <span>Dólar Google (USD → BRL)</span>
                <input type="number" name="dollar_rate" step="0.0001" min="0" value="<?= htmlspecialchars((string)$dollar) ?>" required>
            </label>

            <label class="field" id="additional-discount-field">
                <span>Desconto adicional sobre MSRP (%)</span>
                <input type="number" name="discount_additional" step="0.01" min="0" max="100" value="<?= htmlspecialchars((string)$addDisc) ?>" <?= $pricingType === 'NON_STANDARD' ? '' : 'disabled' ?>>
            </label>

            <label class="field toggle-field">
                <span>Deal Registration</span>
                <label class="switch">
                    <input type="checkbox" name="deal_registration" <?= $dr ? 'checked' : '' ?>>
                    <span class="slider" aria-hidden="true"></span>
                    <span class="switch-label" data-on="Habilitado" data-off="Desabilitado"><?= $dr ? 'Habilitado' : 'Desabilitado' ?></span>
                </label>
            </label>
        </div>

        <div class="discount-panel" id="discount-panel">
            <div class="pill pill-total">
                <span>Desconto Total</span>
                <strong data-role="total">27%</strong>
            </div>
            <div class="pill">
                <span>TD</span>
                <strong data-role="td">10%</strong>
            </div>
            <div class="pill">
                <span>Revenda</span>
                <strong data-role="reseller">17%</strong>
            </div>
        </div>
    </section>

    <!-- ------ Configuração da Proposta ------ -->
    <section class="card">
        <h2 class="card-title">Configuração da Proposta</h2>

        <div class="config-row">
            <label class="field">
                <span>Solução</span>
                <select name="solution" disabled>
                    <option value="SecOps" selected>SecOps</option>
                </select>
            </label>

            <label class="field">
                <span>Versão</span>
                <select name="sku_id" id="sku-select">
                    <?php foreach ($skus as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" data-price="<?= (float)$s['price_usd_tb_year'] ?>">
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Volumetria (TB/ano)</span>
                <input type="number" name="tb_per_year" id="tb-input" min="0" step="0.01" value="10">
            </label>

            <button type="button" id="btn-add-item" class="btn btn-primary btn-icon add-item-btn" aria-label="Adicionar item">
                <span aria-hidden="true">＋</span> Adicionar
            </button>
        </div>

        <div class="config-footer">
            <div class="conversion-hint">
                Conversão: <strong id="gb-hint">10 TB = 10.240 GB</strong>
            </div>

            <div class="live-preview" id="live-preview">
                <div><span>Custo Bruto (USD)</span><strong data-role="gross">–</strong></div>
                <div><span>Custo Líquido (USD)</span><strong data-role="net">–</strong></div>
                <div><span>Mensal (BRL)</span><strong data-role="monthly">–</strong></div>
            </div>
        </div>
    </section>

    <!-- ------ Itens ------ -->
    <section class="card">
        <h2 class="card-title">Itens da Proposta</h2>

        <div class="table-wrap">
            <table class="data-table" id="items-table">
                <thead>
                    <tr>
                        <th>Descrição do Produto</th>
                        <th class="num">Quantidade</th>
                        <th class="num">Custo Total Item (USD)</th>
                        <th class="num">Custo Mensal (BRL)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <tr class="empty-row"><td colspan="5">Nenhum item adicionado.</td></tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Totais</th>
                        <th class="num"></th>
                        <th class="num" id="total-usd">USD 0,00</th>
                        <th class="num" id="total-brl">BRL 0,00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</form>
<?php if ($isEdit): ?>
<script type="application/json" id="edit-items"><?= json_encode(array_map(fn($i) => [
    'sku_id'  => (int)$i['sku_id'],
    'sku_name'=> $i['sku_name'] ?? $i['version'] ?? '',
    'plan'    => $i['plan_description'] ?? 'Annual Plan (Monthly Payment)',
    'tb'      => (float)$i['tb_per_year'],
    'gb'      => (float)$i['gb_per_year'],
    'unit'    => (float)$i['unit_price_usd'],
    'gross'   => (float)$i['gross_total_usd'],
    'net'     => (float)$i['net_total_usd'],
    'monthly' => (float)$i['monthly_brl'],
], $editItems), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
<?php endif; ?>
