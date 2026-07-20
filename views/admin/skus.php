<?php /** @var array $skus @var string $csrf @var bool $canEdit */
$fmtUsd  = fn(float $v) => 'USD ' . number_format($v, 2, ',', '.');
$canEdit = !empty($canEdit);
?>
<header class="page-head">
    <div>
        <h1>SKUs &amp; Preços</h1>
        <p class="muted">
            <?= $canEdit
                ? 'Cadastre, edite ou remova SKUs utilizados na composição das propostas.'
                : 'Consulta dos SKUs e preços utilizados nas propostas (somente leitura).' ?>
        </p>
    </div>
    <?php if ($canEdit): ?>
        <button type="button" id="btn-new-sku" class="btn btn-primary">＋ Novo SKU</button>
    <?php else: ?>
        <span class="badge badge-blue">Somente leitura</span>
    <?php endif; ?>
</header>

<?php if ($canEdit): ?>
<section class="card is-collapsible" id="new-sku">
    <h2 class="card-title">Novo SKU</h2>
    <form method="post" action="/admin/skus" class="grid-4 gap">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="field"><span>Código (SKU)</span><input type="text" name="sku_code" required></label>
        <label class="field"><span>Nome</span><input type="text" name="name" required></label>
        <label class="field"><span>Descrição do Plano</span><input type="text" name="plan_description" value="Annual Plan (Monthly Payment)"></label>
        <label class="field"><span>Preço USD/TB/ano</span><input type="number" name="price_usd_tb_year" step="0.01" min="0" required></label>
        <label class="field toggle-field">
            <span>Ativo</span>
            <label class="switch">
                <input type="checkbox" name="active" checked>
                <span class="slider"></span>
                <span class="switch-label" data-on="Ativo" data-off="Inativo">Ativo</span>
            </label>
        </label>
        <div class="form-actions span-4">
            <button type="submit" class="btn btn-primary">Salvar SKU</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Plano</th>
                    <th class="num">Preço USD/TB/ano</th>
                    <th>Status</th>
                    <?php if ($canEdit): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($skus)): ?>
                    <tr class="empty-row"><td colspan="<?= $canEdit ? 6 : 5 ?>">Nenhum SKU cadastrado.</td></tr>
                <?php elseif ($canEdit): foreach ($skus as $s): ?>
                <tr>
                    <form method="post" action="/admin/skus/<?= (int)$s['id'] ?>" style="display:contents">
                        <input type="hidden" name="_csrf"   value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="_method" value="PUT">
                        <td><input type="text" name="sku_code" value="<?= htmlspecialchars($s['sku_code']) ?>" required></td>
                        <td><input type="text" name="name"     value="<?= htmlspecialchars($s['name']) ?>" required></td>
                        <td><input type="text" name="plan_description" value="<?= htmlspecialchars($s['plan_description']) ?>"></td>
                        <td class="num"><input type="number" name="price_usd_tb_year" step="0.01" min="0" value="<?= htmlspecialchars($s['price_usd_tb_year']) ?>" required></td>
                        <td>
                            <label class="switch small">
                                <input type="checkbox" name="active" <?= (int)$s['active'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td class="actions">
                            <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    </form>
                    <form method="post" action="/admin/skus/<?= (int)$s['id'] ?>" style="display:inline"
                          data-confirm="<?= htmlspecialchars('Remover SKU ' . $s['name'] . '?', ENT_QUOTES) ?>">
                        <input type="hidden" name="_csrf"   value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-ghost btn-sm text-danger">Excluir</button>
                    </form>
                        </td>
                </tr>
                <?php endforeach; else: foreach ($skus as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['sku_code']) ?></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= htmlspecialchars($s['plan_description']) ?></td>
                    <td class="num"><?= $fmtUsd((float)$s['price_usd_tb_year']) ?></td>
                    <td>
                        <span class="badge <?= (int)$s['active'] ? 'badge-green' : 'badge-amber' ?>">
                            <?= (int)$s['active'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
