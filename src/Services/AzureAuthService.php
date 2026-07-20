<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Wrapper fino sobre thenetworg/oauth2-azure.
 * Retorna sempre nome + e-mail do usuário autenticado.
 */
final class AzureAuthService
{
    private Azure $provider;

    public function __construct(array $azureConfig)
    {
        if (empty($azureConfig['client_id']) || empty($azureConfig['client_secret']) || empty($azureConfig['tenant_id'])) {
            throw new RuntimeException('Credenciais do Azure AD não configuradas. Verifique o arquivo .env.');
        }

        $this->provider = new Azure([
            'clientId'     => $azureConfig['client_id'],
            'clientSecret' => $azureConfig['client_secret'],
            'redirectUri'  => $azureConfig['redirect_uri'],
            'tenant'       => $azureConfig['tenant_id'],
            'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
        ]);
        $this->provider->scope = $azureConfig['scopes'] ?? ['openid', 'profile', 'email', 'User.Read'];
    }

    public function getAuthorizationUrl(): array
    {
        // prompt=select_account força a escolha/re-autenticação da conta,
        // evitando login silencioso via sessão SSO após o logout.
        $url   = $this->provider->getAuthorizationUrl(['prompt' => 'select_account']);
        $state = $this->provider->getState();
        return ['url' => $url, 'state' => $state];
    }

    /**
     * @return array{oid:string,email:string,name:string}
     */
    public function handleCallback(string $code): array
    {
        /** @var \TheNetworg\OAuth2\Client\Token\AccessToken $token */
        $token  = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
        $claims = $token->getIdTokenClaims() ?? [];

        $email = $claims['email']
            ?? $claims['preferred_username']
            ?? $claims['upn']
            ?? '';
        $name  = $claims['name'] ?? '';
        $oid   = $claims['oid'] ?? $claims['sub'] ?? '';

        if ($oid === '' || $email === '') {
            throw new RuntimeException('id_token não contém identificação do usuário (oid/email).');
        }

        return [
            'oid'   => (string)$oid,
            'email' => (string)$email,
            'name'  => trim((string)$name) !== '' ? trim((string)$name) : (string)$email,
        ];
    }
}
