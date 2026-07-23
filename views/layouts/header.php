<?php
use App\Core\Session;
$user  = Session::get('user');
$title = $title ?? 'SecOps Calculator';
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = fn(string $p) => str_starts_with($path, $p) ? 'is-active' : '';
$errorFlash   = Session::flash('error');
$successFlash = Session::flash('success');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <script src="/assets/js/theme-init.js"></script>
    <title><?= htmlspecialchars($title) ?> · SecOps Calculator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <?php if ($user): ?>
    <header class="mobile-topbar">
        <button type="button" class="mobile-nav-toggle" id="mobile-nav-toggle"
                aria-label="Abrir menu de navegação" aria-controls="sidebar" aria-expanded="false">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
        <a href="/proposals/create" class="mobile-brand" aria-label="Google SecOps Calculator">
            <img src="/assets/img/logo.png" alt="Google SecOps Calculator" class="mobile-brand-logo">
        </a>
    </header>
    <div class="mobile-nav-backdrop" id="mobile-nav-backdrop" hidden></div>
    <aside class="sidebar" id="sidebar" aria-label="Navegação principal">
        <a href="/proposals/create" class="brand" aria-label="Google SecOps Calculator">
            <img src="/assets/img/logo.png" alt="Google SecOps Calculator" class="brand-logo">
        </a>

        <nav class="nav">
            <?php if (!empty($user['is_admin'])): ?>
            <a href="/dashboard" class="nav-item <?= $isActive('/dashboard') ?>">
                <span class="nav-icon" aria-hidden="true">▤</span> Dashboard
            </a>
            <?php endif; ?>
            <a href="/proposals/create" class="nav-item <?= $isActive('/proposals/create') ?>">
                <span class="nav-icon" aria-hidden="true">＋</span> Nova Proposta
            </a>
            <a href="/proposals" class="nav-item <?= ($path === '/proposals') ? 'is-active' : '' ?>">
                <span class="nav-icon" aria-hidden="true">≡</span> Minhas Propostas
            </a>
            <?php if (!empty($user['is_admin'])): ?>
            <a href="/admin/proposals" class="nav-item <?= $isActive('/admin/proposals') ?>">
                <span class="nav-icon" aria-hidden="true">⚑</span> Todas as Propostas
            </a>
            <?php endif; ?>
            <a href="/admin/skus" class="nav-item <?= $isActive('/admin/skus') ?>">
                <span class="nav-icon" aria-hidden="true">◧</span> SKUs & Preços
            </a>
            <?php if (!empty($user['is_admin'])): ?>
            <a href="/admin/users" class="nav-item <?= $isActive('/admin/users') ?>">
                <span class="nav-icon" aria-hidden="true">◎</span> Administradores
            </a>
            <?php endif; ?>
        </nav>

        <div class="user-card">
            <div class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($user['name'] ?? '?', 0, 1))) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
                <span><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Alternar tema claro/escuro" title="Alternar tema">
                <span class="theme-toggle-icon" aria-hidden="true"></span>
            </button>
            <form method="post" action="/auth/logout" class="logout-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
                <button type="submit" class="logout" aria-label="Sair">Sair</button>
            </form>
        </div>
    </aside>
    <?php endif; ?>

    <main class="content">
        <?php if ($errorFlash): ?>
            <div class="toast toast-error" role="alert"><?= htmlspecialchars($errorFlash) ?></div>
        <?php endif; ?>
        <?php if ($successFlash): ?>
            <div class="toast toast-success" role="status"><?= htmlspecialchars($successFlash) ?></div>
        <?php endif; ?>
