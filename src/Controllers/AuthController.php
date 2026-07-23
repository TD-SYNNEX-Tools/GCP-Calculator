<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\User;
use App\Services\AzureAuthService;
use Closure;
use PDO;
use Throwable;

final class AuthController extends BaseController
{
    /**
     * @param Closure(): PDO $db Provedor preguiçoso de conexão. A conexão só
     *   é aberta quando realmente necessária (callback/devLogin), mantendo a
     *   tela de login (showLogin) e o início do SSO (login) independentes do
     *   banco — o que evita erro 500 na página de login durante cold starts
     *   ou indisponibilidade transitória do BD.
     */
    public function __construct(
        private readonly Closure $db,
        private readonly array $azureConfig,
        private readonly bool $authBypass = false,
        private readonly array $adminEmails = [],
    ) {}

    private function isAdminEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        foreach ($this->adminEmails as $admin) {
            if (strtolower(trim((string)$admin)) === $email) {
                return true;
            }
        }
        return false;
    }

    public function showLogin(): void
    {
        if (Session::has('user')) {
            $this->redirect('/proposals/create');
        }
        $this->view('auth/login', [
            'title'        => 'Login',
            'auth_bypass'  => $this->authBypass,
        ]);
    }

    public function login(): void
    {
        try {
            $svc  = new AzureAuthService($this->azureConfig);
            $data = $svc->getAuthorizationUrl();
            Session::set('oauth_state', $data['state']);
            $this->redirect($data['url']);
        } catch (Throwable $e) {
            error_log('Auth login: ' . $e->getMessage());
            Session::flash('error', 'Falha ao iniciar o login SSO. Tente novamente.');
            $this->redirect('/login');
        }
    }

    public function callback(): void
    {
        $code  = $_GET['code']  ?? null;
        $state = $_GET['state'] ?? null;

        if (!is_string($code) || !is_string($state) || $state !== Session::get('oauth_state')) {
            Session::flash('error', 'Estado OAuth inválido. Tente novamente.');
            $this->redirect('/login');
            return;
        }
        Session::forget('oauth_state');

        try {
            $svc     = new AzureAuthService($this->azureConfig);
            $profile = $svc->handleCallback($code);

            // Verifica domínio do e-mail contra a lista de domínios permitidos (se houver)
            $allowed = $this->azureConfig['allowed_domains'] ?? [];
            if (!empty($allowed)) {
                $email = $profile['email'] ?? '';
                $domain = '';
                if (is_string($email) && strpos($email, '@') !== false) {
                    $parts = explode('@', $email);
                    $domain = strtolower(array_pop($parts));
                }

                $ok = false;
                foreach ($allowed as $d) {
                    if ($d === '') {
                        continue;
                    }
                    if (strtolower($d) === $domain) {
                        $ok = true;
                        break;
                    }
                }

                if (!$ok) {
                    Session::flash('error', 'Domínio não autorizado para acesso.');
                    $this->redirect('/login');
                    return;
                }
            }

            $userModel = new User(($this->db)());
            $userId    = $userModel->upsertFromAzure($profile['oid'], $profile['email'], $profile['name']);

            // Bootstrap de administradores: e-mails listados em APP_ADMIN_EMAILS
            // são promovidos automaticamente no banco, que passa a ser a fonte de
            // verdade dos privilégios. Novos admins são atribuídos pelo painel.
            if ($this->isAdminEmail($profile['email']) && $userModel->getRole($userId) !== 'admin') {
                $userModel->setRole($userId, 'admin');
            }
            $isAdmin = $userModel->getRole($userId) === 'admin';

            // Renova o ID da sessão após autenticação para prevenir fixação (CWE-384).
            Session::regenerate();
            Session::set('user', [
                'id'       => $userId,
                'name'     => $profile['name'],
                'email'    => $profile['email'],
                'is_admin' => $isAdmin,
            ]);

            $this->redirect('/proposals/create');
        } catch (Throwable $e) {
            error_log('Auth callback: ' . $e->getMessage());
            Session::flash('error', 'Não foi possível concluir o login SSO. Tente novamente.');
            $this->redirect('/login');
        }
    }

    public function logout(): void
    {
        // Logout altera estado (encerra a sessão): exige token CSRF válido
        // e só é aceito via POST, mitigando logout forçado (CWE-352).
        $this->requireCsrf();

        // Determina se a sessão atual é do login de desenvolvimento.
        $user  = Session::get('user');
        $isDev = is_array($user) && (($user['email'] ?? '') === 'dev@localhost');

        Session::destroy();

        // Login de dev ou Azure não configurado: encerra apenas a sessão local.
        if ($isDev || empty($this->azureConfig['tenant_id'])) {
            $this->redirect('/login');
            return;
        }

        // Encerra também a sessão no Microsoft Entra ID (end-session endpoint),
        // garantindo que um novo login exija autenticação novamente.
        $tenant     = $this->azureConfig['tenant_id'];
        $postLogout = (string)($this->azureConfig['post_logout_redirect_uri'] ?? '');
        $url = 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/logout';
        if ($postLogout !== '') {
            $url .= '?post_logout_redirect_uri=' . urlencode($postLogout);
        }
        $this->redirect($url);
    }

    /**
     * Login sem SSO — apenas para desenvolvimento local.
     * Requer APP_AUTH_BYPASS=true no .env.
     */
    public function devLogin(): void
    {
        // Defesa em profundidade: o bypass nunca é permitido em produção,
        // mesmo que APP_AUTH_BYPASS seja habilitado por engano.
        $env = strtolower((string)($_ENV['APP_ENV'] ?? 'production'));
        if (!$this->authBypass || $env === 'production') {
            http_response_code(403);
            echo 'Bypass de autenticação desabilitado.';
            return;
        }

        $userModel = new User(($this->db)());
        $userId    = $userModel->upsertFromAzure(
            oid:   'dev-local-user',
            email: 'dev@localhost',
            name:  'Usuário Dev'
        );

        // Renova o ID da sessão após autenticação para prevenir fixação (CWE-384).
        Session::regenerate();
        Session::set('user', [
            'id'       => $userId,
            'name'     => 'Usuário Dev',
            'email'    => 'dev@localhost',
            'is_admin' => true,
        ]);

        $this->redirect('/proposals/create');
    }
}
