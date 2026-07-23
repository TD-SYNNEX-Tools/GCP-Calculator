<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(string $name = 'SECOPS_SID'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Detecta se a requisição é HTTPS, considerando o encerramento de TLS
     * feito por proxies reversos (ex.: Azure App Service), que encaminham a
     * requisição em HTTP puro para o container e sinalizam o protocolo
     * original via cabeçalho X-Forwarded-Proto.
     */
    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? 'off') === 'on') {
            return true;
        }
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($proto !== '') {
            // Pode chegar como lista "https,http"; considera o primeiro valor.
            return strtolower(trim(explode(',', $proto)[0])) === 'https';
        }
        return false;
    }

    /**
     * Regenera o ID da sessão preservando os dados. Deve ser chamado após
     * autenticação bem-sucedida ou mudança de privilégio para mitigar
     * fixação de sessão (CWE-384).
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }
}
