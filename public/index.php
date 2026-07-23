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

// ---------- Tratamento global de erros ----------
// Garante que nenhuma exceção/erro fatal vaze como um "500 cru" para o
// usuário. A causa real é registrada em error_log (visível no Log Stream do
// App Service) e uma página 500 amigável é devolvida. Detalhes só aparecem
// fora de produção. Isso torna a aplicação resiliente a cold starts do
// container (driver pdo_sqlsrv ainda não carregado) e a falhas transitórias.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$renderFatal = static function (int $status, ?Throwable $e = null): void {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 15');
    }
    $isDev = in_array(strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')), ['local', 'dev', 'development'], true);
    $detail = ($isDev && $e !== null)
        ? '<pre style="text-align:left;white-space:pre-wrap;color:#b00">'
            . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine())
            . '</pre>'
        : '';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Serviço temporariamente indisponível</title></head>'
        . '<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
        . '<main style="text-align:center;max-width:32rem;padding:2rem">'
        . '<h1 style="font-size:1.5rem;margin:0 0 .5rem">Serviço temporariamente indisponível</h1>'
        . '<p style="color:#94a3b8">Não foi possível concluir sua solicitação agora. '
        . 'Tente novamente em alguns instantes.</p>' . $detail
        . '</main></body></html>';
};

set_exception_handler(static function (Throwable $e) use ($renderFatal): void {
    error_log('[unhandled] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    $renderFatal(500, $e);
});

register_shutdown_function(static function () use ($renderFatal): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[fatal] ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
        $renderFatal(500);
    }
});

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
// Provedor preguiçoso (lazy): a conexão só é aberta quando uma rota que
// realmente precisa do banco é despachada. Assim, páginas sem BD (home,
// login, fluxo de auth) e o roteamento/404 não falham durante um cold start
// ou indisponibilidade transitória do banco. Falhas ficam contidas à rota e
// são tratadas pelo handler global acima.
$db = static fn(): \PDO => Database::connection($config['db']);

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
$router->get('/dashboard',      fn() => (new DashboardController($db()))->index());
$router->get('/login',         fn() => $auth()->showLogin());
$router->get('/auth/login',    fn() => $auth()->login());
$router->get('/auth/callback', fn() => $auth()->callback());
$router->post('/auth/logout',  fn() => $auth()->logout());
$router->get('/auth/dev-login',  fn() => $auth()->devLogin());
$router->post('/auth/dev-login', fn() => $auth()->devLogin());

// Proposals
$router->get('/proposals/create',     fn() => (new ProposalController($db()))->create());
$router->get('/proposals',            fn() => (new ProposalController($db()))->list());
$router->get('/admin/proposals',      fn() => (new ProposalController($db()))->adminList());
$router->get('/proposals/{id}',       fn($p) => (new ProposalController($db()))->show($p));
$router->get('/proposals/{id}/edit',  fn($p) => (new ProposalController($db()))->edit($p));
$router->get('/proposals/{id}/pdf',   fn($p) => (new ProposalController($db()))->pdf($p));
$router->post('/proposals/preview',   fn() => (new ProposalController($db()))->preview());
$router->post('/proposals/{id}/update', fn($p) => (new ProposalController($db()))->update($p));
$router->post('/proposals',           fn() => (new ProposalController($db()))->store());

// SKUs
$router->get('/admin/skus',           fn() => (new SkuController($db()))->index());
$router->post('/admin/skus',          fn() => (new SkuController($db()))->store());
$router->put('/admin/skus/{id}',      fn($p) => (new SkuController($db()))->update($p));
$router->delete('/admin/skus/{id}',   fn($p) => (new SkuController($db()))->delete($p));

// Usuários / Administradores
$router->get('/admin/users',            fn() => (new UserController($db()))->index());
$router->post('/admin/users/{id}/role', fn($p) => (new UserController($db()))->updateRole($p));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
