/* ============================================================
   SecOps Calculator — Frontend Logic
   ============================================================ */
'use strict';

const GB_PER_TB = 1024;

// ---------- Utilidades de formatação ----------
const fmtUsd = (v) => 'USD ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtBrl = (v) => 'BRL ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtGb  = (v) => Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' GB';

// ---------- Toast host ----------
function toast(message, type = 'success', timeout = 3200) {
    const host = document.getElementById('toast-host');
    if (!host) return;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    host.appendChild(el);
    setTimeout(() => el.remove(), timeout);
}

// ---------- Tema claro/escuro ----------
(function initTheme() {
    const btn = document.getElementById('theme-toggle');
    if (!btn) return;
    const apply = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    };
    // Estado inicial coerente com o que o script do <head> já aplicou.
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    apply(current);
    btn.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('theme', next); } catch (e) { /* ignore */ }
        apply(next);
    });
})();

// ---------- Navegação mobile (drawer lateral) ----------
(function initMobileNav() {
    const toggle   = document.getElementById('mobile-nav-toggle');
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('mobile-nav-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    const open = () => {
        sidebar.classList.add('is-open');
        backdrop.hidden = false;
        // Força reflow para permitir a transição de opacidade do backdrop.
        void backdrop.offsetWidth;
        backdrop.classList.add('is-open');
        document.body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        setTimeout(() => { if (!sidebar.classList.contains('is-open')) backdrop.hidden = true; }, 250);
    };
    const isOpen = () => sidebar.classList.contains('is-open');

    toggle.addEventListener('click', () => (isOpen() ? close() : open()));
    backdrop.addEventListener('click', close);
    // Fecha ao navegar por um link do menu.
    sidebar.querySelectorAll('a').forEach((a) => a.addEventListener('click', close));
    // Fecha com Esc.
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen()) close(); });
    // Fecha automaticamente ao voltar para o desktop.
    window.matchMedia('(min-width: 861px)').addEventListener('change', (e) => { if (e.matches) close(); });
})();

// ---------- Toggle switch (label dinâmica) ----------
document.querySelectorAll('.switch input[type="checkbox"]').forEach((cb) => {
    const label = cb.parentElement.querySelector('.switch-label');
    const sync = () => {
        if (!label) return;
        label.textContent = cb.checked
            ? (label.dataset.on || 'On')
            : (label.dataset.off || 'Off');
    };
    cb.addEventListener('change', sync);
    sync();
});

// ---------- Tabela de descontos oficial ----------
function computeDiscount(pricingType, dealRegistration) {
    if (pricingType === 'STANDARD'     &&  dealRegistration) return { total: 27, td: 10, reseller: 17 };
    if (pricingType === 'STANDARD'     && !dealRegistration) return { total: 17, td:  7, reseller: 10 };
    if (pricingType === 'NON_STANDARD' &&  dealRegistration) return { total: 22, td:  7, reseller: 15 };
    return { total: 12, td: 7, reseller: 5 };
}

// ---------- Página: Nova Proposta ----------
const form = document.getElementById('proposal-form');
if (form) initProposalForm();

function initProposalForm() {
    const isEdit = form.dataset.mode === 'edit';
    const items = []; // { sku_id, sku_name, plan, tb, gb, unit, gross, net, monthly }

    const $ = (sel) => form.querySelector(sel);
    const skuSelect  = $('#sku-select');
    const tbInput    = $('#tb-input');
    const gbHint     = $('#gb-hint');
    const btnAdd     = $('#btn-add-item');
    const btnSave    = document.getElementById('btn-save');
    const btnReset   = document.getElementById('btn-reset');
    const discAddInput = form.querySelector('[name="discount_additional"]');
    const tbody      = $('#items-body');
    const totalUsdEl = $('#total-usd');
    const totalBrlEl = $('#total-brl');
    const previewEls = {
        gross:   form.querySelector('[data-role="gross"]'),
        net:     form.querySelector('[data-role="net"]'),
        monthly: form.querySelector('[data-role="monthly"]'),
    };
    const discountEls = {
        total:    form.querySelector('#discount-panel [data-role="total"]'),
        td:       form.querySelector('#discount-panel [data-role="td"]'),
        reseller: form.querySelector('#discount-panel [data-role="reseller"]'),
    };

    // --- Leitura de estado do formulário ---
    function readState() {
        return {
            reseller_name:     form.reseller_name.value.trim(),
            end_customer_name: form.end_customer_name.value.trim(),
            pricing_type:      form.pricing_type.value,
            deal_registration: form.deal_registration.checked,
            contract_years:    parseInt(form.contract_years.value, 10) || 1,
            dollar_rate:       parseFloat(form.dollar_rate.value) || 0,
            discount_additional: form.pricing_type.value === 'NON_STANDARD'
                ? Math.min(100, Math.max(0, parseFloat(discAddInput.value) || 0))
                : 0,
            tb_per_year:       parseFloat(tbInput.value) || 0,
            sku_id:            parseInt(skuSelect.value, 10) || 0,
            sku_price:         parseFloat(skuSelect.selectedOptions[0]?.dataset.price || '0'),
            sku_name:          skuSelect.selectedOptions[0]?.textContent.trim() || '',
        };
    }

    // --- Cálculo em tempo real ---
    function recomputePreview() {
        const s = readState();
        const disc = computeDiscount(s.pricing_type, s.deal_registration);

        // Campo de desconto adicional só é habilitado para Non-Standard.
        const isNonStd = s.pricing_type === 'NON_STANDARD';
        discAddInput.disabled = !isNonStd;
        if (!isNonStd) discAddInput.value = '0';

        const effTotal = disc.total + s.discount_additional;

        discountEls.total.textContent    = effTotal + '%';
        discountEls.td.textContent       = disc.td + '%';
        discountEls.reseller.textContent = disc.reseller + '%';

        const gb = s.tb_per_year * GB_PER_TB;
        gbHint.textContent = `${s.tb_per_year.toLocaleString('pt-BR')} TB = ${fmtGb(gb)}`;

        const gross    = s.sku_price * s.tb_per_year * s.contract_years;
        const net      = gross * (1 - effTotal / 100);
        const months   = Math.max(1, s.contract_years * 12);
        const monthly  = (net * s.dollar_rate) / months;

        previewEls.gross.textContent   = s.tb_per_year > 0 ? fmtUsd(gross) : '–';
        previewEls.net.textContent     = s.tb_per_year > 0 ? fmtUsd(net)   : '–';
        previewEls.monthly.textContent = s.tb_per_year > 0 && s.dollar_rate > 0 ? fmtBrl(monthly) : '–';

        // Ao mudar precificação/DR/anos/dólar/desconto adicional, recalcular itens existentes
        items.forEach((it) => {
            const g = it.unit * it.tb * s.contract_years;
            const n = g * (1 - effTotal / 100);
            it.gross   = g;
            it.net     = n;
            it.monthly = (n * s.dollar_rate) / months;
        });
        renderItems();
        toggleSave();
        saveDraft();
    }

    function toggleSave() {
        const s = readState();
        const ok = s.reseller_name && s.end_customer_name && s.dollar_rate > 0 && items.length > 0;
        btnSave.disabled = !ok;
    }

    function renderItems() {
        if (items.length === 0) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="5">Nenhum item adicionado.</td></tr>';
            totalUsdEl.textContent = fmtUsd(0);
            totalBrlEl.textContent = fmtBrl(0);
            return;
        }
        tbody.innerHTML = '';
        let sumUsd = 0, sumBrl = 0;
        items.forEach((it, idx) => {
            sumUsd += it.net;
            sumBrl += it.monthly;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(it.sku_name)}</strong>
                    <div class="muted">${escapeHtml(it.plan || '')}</div>
                </td>
                <td class="num">
                    ${it.tb.toLocaleString('pt-BR')} TB/ano
                    <div class="muted">${fmtGb(it.gb)}</div>
                </td>
                <td class="num">${fmtUsd(it.net)}</td>
                <td class="num">${fmtBrl(it.monthly)}</td>
                <td class="num">
                    <button type="button" class="btn btn-ghost btn-sm text-danger" data-idx="${idx}">Remover</button>
                </td>`;
            tbody.appendChild(tr);
        });
        totalUsdEl.textContent = fmtUsd(sumUsd);
        totalBrlEl.textContent = fmtBrl(sumBrl);

        tbody.querySelectorAll('button[data-idx]').forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                const i = parseInt(ev.currentTarget.dataset.idx, 10);
                items.splice(i, 1);
                renderItems();
                toggleSave();
                saveDraft();
            });
        });
    }

    // --- Adicionar item ---
    btnAdd.addEventListener('click', () => {
        const s = readState();
        if (!s.sku_id || s.tb_per_year <= 0) {
            toast('Informe uma volumetria válida em TB/ano.', 'error');
            return;
        }
        const disc = computeDiscount(s.pricing_type, s.deal_registration);
        const effTotal = disc.total + s.discount_additional;
        const gross = s.sku_price * s.tb_per_year * s.contract_years;
        const net   = gross * (1 - effTotal / 100);
        const months = Math.max(1, s.contract_years * 12);

        items.push({
            sku_id:   s.sku_id,
            sku_name: s.sku_name,
            plan:     'Annual Plan (Monthly Payment)',
            tb:       s.tb_per_year,
            gb:       s.tb_per_year * GB_PER_TB,
            unit:     s.sku_price,
            gross,
            net,
            monthly: (net * s.dollar_rate) / months,
        });
        toast(`Item ${s.sku_name} adicionado.`, 'success');
        renderItems();
        toggleSave();
        saveDraft();
    });

    // --- Salvar proposta ---
    btnSave.addEventListener('click', async () => {
        if (btnSave.disabled) return;
        const s = readState();
        const payload = {
            reseller_name:     s.reseller_name,
            end_customer_name: s.end_customer_name,
            pricing_type:      s.pricing_type,
            deal_registration: s.deal_registration,
            contract_years:    s.contract_years,
            dollar_rate:       s.dollar_rate,
            discount_additional: s.discount_additional,
            items: items.map((it) => ({ sku_id: it.sku_id, tb_per_year: it.tb })),
        };
        const saveLabel = isEdit ? 'Salvar alterações' : 'Salvar proposta';
        const saveUrl   = isEdit ? (form.dataset.updateUrl || '/proposals') : '/proposals';
        btnSave.disabled = true;
        btnSave.textContent = 'Salvando...';
        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token':  form.dataset.csrf,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Falha ao salvar');
            toast(isEdit ? 'Proposta atualizada!' : 'Proposta salva!', 'success');
            clearDraft();
            setTimeout(() => { window.location.href = data.redirect; }, 500);
        } catch (e) {
            toast(e.message, 'error');
            btnSave.disabled = false;
            btnSave.textContent = saveLabel;
        }
    });

    // --- Persistência local (mantém os dados ao atualizar a página) ---
    const STORAGE_KEY = 'secops_proposal_draft';

    function saveDraft() {
        if (isEdit) return; // em edição não persistimos rascunho local
        const draft = {
            fields: {
                reseller_name:       form.reseller_name.value,
                end_customer_name:   form.end_customer_name.value,
                pricing_type:        form.pricing_type.value,
                deal_registration:   form.deal_registration.checked,
                contract_years:      form.contract_years.value,
                dollar_rate:         form.dollar_rate.value,
                discount_additional: discAddInput.value,
                sku_id:              skuSelect.value,
                tb_per_year:         tbInput.value,
            },
            items,
        };
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(draft)); } catch (e) { /* storage indisponível */ }
    }

    function restoreDraft() {
        let raw = null;
        try { raw = localStorage.getItem(STORAGE_KEY); } catch (e) { return; }
        if (!raw) return;
        let draft;
        try { draft = JSON.parse(raw); } catch (e) { return; }
        const f = draft.fields || {};
        if (f.reseller_name       != null) form.reseller_name.value     = f.reseller_name;
        if (f.end_customer_name   != null) form.end_customer_name.value = f.end_customer_name;
        if (f.pricing_type        != null) form.pricing_type.value      = f.pricing_type;
        if (f.deal_registration   != null) form.deal_registration.checked = !!f.deal_registration;
        if (f.contract_years      != null) form.contract_years.value    = f.contract_years;
        if (f.dollar_rate         != null) form.dollar_rate.value       = f.dollar_rate;
        if (f.discount_additional != null) discAddInput.value           = f.discount_additional;
        if (f.sku_id              != null) skuSelect.value              = f.sku_id;
        if (f.tb_per_year         != null) tbInput.value                = f.tb_per_year;
        if (Array.isArray(draft.items)) {
            items.length = 0;
            draft.items.forEach((it) => items.push(it));
        }
        // Ressincroniza as labels dos toggles com o estado restaurado
        document.querySelectorAll('.switch input[type="checkbox"]')
            .forEach((cb) => cb.dispatchEvent(new Event('change')));
    }

    function clearDraft() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* storage indisponível */ }
    }

    // --- Carrega itens existentes ao editar ---
    function loadEditItems() {
        const node = document.getElementById('edit-items');
        if (!node) return;
        try {
            const arr = JSON.parse(node.textContent || '[]');
            if (Array.isArray(arr)) {
                items.length = 0;
                arr.forEach((it) => items.push(it));
            }
        } catch (e) { /* JSON inválido */ }
    }

    // --- Resetar proposta ---
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            clearDraft();
            form.reset();
            items.length = 0;
            document.querySelectorAll('.switch input[type="checkbox"]')
                .forEach((cb) => cb.dispatchEvent(new Event('change')));
            recomputePreview();
            toast('Proposta resetada.', 'success');
        });
    }

    // --- Listeners que disparam recomputo em tempo real ---
    form.addEventListener('input',  recomputePreview);
    form.addEventListener('change', recomputePreview);
    if (isEdit) {
        loadEditItems();
    } else {
        restoreDraft();
    }
    recomputePreview();
}

// ---------- Helpers ----------
function escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

// ---------- Página: SKUs & Preços ----------
// Handlers movidos do HTML inline (onclick/onsubmit) para cá, permitindo
// uma Content-Security-Policy estrita (script-src 'self').
const btnNewSku = document.getElementById('btn-new-sku');
if (btnNewSku) {
    btnNewSku.addEventListener('click', () => {
        document.getElementById('new-sku')?.classList.toggle('is-open');
    });
}

document.querySelectorAll('form[data-confirm]').forEach((frm) => {
    frm.addEventListener('submit', (ev) => {
        if (!window.confirm(frm.dataset.confirm)) {
            ev.preventDefault();
        }
    });
});
