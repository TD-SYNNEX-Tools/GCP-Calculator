<?php /** @var string $title @var bool $auth_bypass */ ?>
<section class="login-shell">
    <div class="login-card">
        <div class="login-brand">
            <img src="/assets/img/logo.png" alt="Google SecOps Calculator" class="login-logo">
            <p>Calculadora de Descontos e Propostas Google SecOps</p>
        </div>

        <a class="btn btn-primary btn-block" href="/auth/login">
            <svg width="18" height="18" viewBox="0 0 23 23" aria-hidden="true">
                <path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#7fba00" d="M12 1h10v10H12z"/>
                <path fill="#00a4ef" d="M1 12h10v10H1z"/><path fill="#ffb900" d="M12 12h10v10H12z"/>
            </svg>
            Entrar com Microsoft
        </a>

        <?php if (!empty($auth_bypass)): ?>
            <div class="login-divider"><span>ou</span></div>
            <a class="btn btn-ghost btn-block" href="/auth/dev-login">
                Entrar sem autenticação (Dev)
            </a>
            <p class="login-footnote">
                Bypass ativo via <code>APP_AUTH_BYPASS=true</code> no <code>.env</code>.
                Desabilite em produção.
            </p>
        <?php else: ?>
            <p class="login-footnote">
                Acesso restrito a usuários corporativos autenticados via Microsoft Entra ID.
            </p>
        <?php endif; ?>
    </div>
</section>
