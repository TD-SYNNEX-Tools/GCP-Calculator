<?php
declare(strict_types=1);

return [
    'app' => [
        'env'         => $_ENV['APP_ENV']         ?? 'production',
        'url'         => $_ENV['APP_URL']         ?? 'http://localhost:8000',
        'timezone'    => $_ENV['APP_TIMEZONE']    ?? 'America/Sao_Paulo',
        'session'     => $_ENV['SESSION_NAME']    ?? 'SECOPS_SID',
        'auth_bypass' => filter_var($_ENV['APP_AUTH_BYPASS'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        // E-mails com privilégio de administrador (separados por vírgula).
        'admin_emails' => array_filter(array_map('trim', explode(',', $_ENV['APP_ADMIN_EMAILS'] ?? ''))),
    ],
    'db' => [
        'driver'     => $_ENV['DB_DRIVER'] ?? 'sqlsrv',
        'host'       => $_ENV['DB_HOST'] ?? 'srv-db-secops.database.windows.net',
        'port'       => (int)($_ENV['DB_PORT'] ?? 1433),
        'name'       => $_ENV['DB_NAME'] ?? 'sql-db-secopscalculator',
        'user'       => $_ENV['DB_USER'] ?? 'tdsynnex',
        'pass'       => $_ENV['DB_PASS'] ?? '',
        // Azure SQL exige conexão criptografada e certificado válido.
        'encrypt'    => filter_var($_ENV['DB_ENCRYPT'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'trust_cert' => filter_var($_ENV['DB_TRUST_CERT'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    ],
    'azure' => [
        'tenant_id'     => $_ENV['AZURE_TENANT_ID']     ?? '',
        'client_id'     => $_ENV['AZURE_CLIENT_ID']     ?? '',
        'client_secret' => $_ENV['AZURE_CLIENT_SECRET'] ?? '',
        'redirect_uri'  => $_ENV['AZURE_REDIRECT_URI']  ?? 'http://localhost:8000/auth/callback',
        'post_logout_redirect_uri' => ($_ENV['APP_URL'] ?? 'http://localhost:8000') . '/login',
        'scopes'        => ['openid', 'profile', 'email', 'User.Read'],
        // Lista de domínios permitidos (separados por vírgula) para restringir logins.
        // Ex.: AZURE_ALLOWED_DOMAINS=contoso.com,partner.org
        'allowed_domains' => array_filter(array_map('trim', explode(',', $_ENV['AZURE_ALLOWED_DOMAINS'] ?? ''))),
    ],
];
