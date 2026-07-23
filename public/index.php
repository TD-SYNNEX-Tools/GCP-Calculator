<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ProposalController;
use App\Controllers\SkuController;
use App\Controllers\UserController;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// ---------- ENV ----------
$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

// ---------- Key Vault (opcional) ----------
// Se KEYVAULT_URI estiver definido, carrega segredos do Azure Key Vault
// e injeta em $_ENV antes de montar a configuração. Em caso de falha,
// a aplicação continua usando os valores do .env.
$vaultUri = $_ENV['KEYVAULT_URI'] ?? '';
if ($vaultUri !== '') {
    try {
        $kv = new App\Services\KeyVaultService(
            $vaultUri,
            $_ENV['AZURE_TENANT_ID']     ?? '',
            $_ENV['AZURE_CLIENT_ID']     ?? '',
            $_ENV['AZURE_CLIENT_SECRET'] ?? ''
        );
        $secretMap = [
            'DB_PASS'             => $_ENV['KV_SECRET_DB_PASS']             ?? 'db-pass',
            'AZURE_CLIENT_SECRET' => $_ENV['KV_SECRET_AZURE_CLIENT_SECRET'] ?? 'azure-client-secret',
        ];
        foreach ($secretMap as $envKey => $secretName) {
            try {
                $_ENV[$envKey] = $kv->getSecret($secretName);
            } catch (Throwable $e) {
                error_log('Key Vault: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Key Vault init falhou: ' . $e->getMessage());
    }
}

$config = require $rootDir . '/src/Config/config.php';

date_default_timezone_set($config['app']['timezone']);
Session::start($config['app']['session']);

// ---------- Security headers ----------
// Aplicados a todas as respostas. Reforçam a proteção contra clickjacking,
// MIME sniffing, vazamento de referer e injeção de conteúdo (XSS). São
// definidos em PHP para funcionarem também no servidor embutido (`php -S`),
// que ignora o .htaccess.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: default-src 'self'; base-uri 'self'; "
        . "object-src 'none'; frame-ancestors 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "script-src 'self'; connect-src 'self'; "
        . "form-action 'self' https://login.microsoftonline.com"
    );
    if (Session::isHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------- DB ----------
$db = Database::connection($config['db']);

// ---------- Router ----------
$router = new Router();

// Home & Auth
$auth = fn() => new AuthController(
    $db,
    $config['azure'],
    $config['app']['auth_bypass'],
    $config['app']['admin_emails']
);
$router->get('/',              fn() => (new DashboardController())->home());
$router->get('/dashboard',      fn() => (new DashboardController($db))->index());
$router->get('/login',         fn() => $auth()->showLogin());
$router->get('/auth/login',    fn() => $auth()->login());
$router->get('/auth/callback', fn() => $auth()->callback());
$router->post('/auth/logout',  fn() => $auth()->logout());
$router->get('/auth/dev-login',  fn() => $auth()->devLogin());
$router->post('/auth/dev-login', fn() => $auth()->devLogin());

// Proposals
$router->get('/proposals/create',     fn() => (new ProposalController($db))->create());
$router->get('/proposals',            fn() => (new ProposalController($db))->list());
$router->get('/admin/proposals',      fn() => (new ProposalController($db))->adminList());
$router->get('/proposals/{id}',       fn($p) => (new ProposalController($db))->show($p));
$router->get('/proposals/{id}/edit',  fn($p) => (new ProposalController($db))->edit($p));
$router->get('/proposals/{id}/pdf',   fn($p) => (new ProposalController($db))->pdf($p));
$router->post('/proposals/preview',   fn() => (new ProposalController($db))->preview());
$router->post('/proposals/{id}/update', fn($p) => (new ProposalController($db))->update($p));
$router->post('/proposals',           fn() => (new ProposalController($db))->store());

// SKUs
$router->get('/admin/skus',           fn() => (new SkuController($db))->index());
$router->post('/admin/skus',          fn() => (new SkuController($db))->store());
$router->put('/admin/skus/{id}',      fn($p) => (new SkuController($db))->update($p));
$router->delete('/admin/skus/{id}',   fn($p) => (new SkuController($db))->delete($p));

// Usuários / Administradores
$router->get('/admin/users',            fn() => (new UserController($db))->index());
$router->post('/admin/users/{id}/role', fn($p) => (new UserController($db))->updateRole($p));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
