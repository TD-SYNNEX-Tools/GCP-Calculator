<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

/**
 * Busca segredos do Azure Key Vault.
 *
 * Dois modos de autenticação, escolhidos automaticamente:
 *  - **Managed Identity** (recomendado em produção no Azure App Service):
 *    usado quando nenhum client secret é fornecido. Não requer segredo em
 *    arquivo — a identidade gerenciada do recurso obtém o token.
 *  - **Client credentials** (fallback para desenvolvimento local): usado
 *    quando um client secret é fornecido.
 *
 * Uso opcional: ativado quando a variável KEYVAULT_URI está definida.
 * Requer que a identidade (managed identity ou App Registration) tenha
 * permissão de leitura de segredos (RBAC "Key Vault Secrets User").
 */
final class KeyVaultService
{
    private Client $http;
    private ?string $token = null;

    /** @var array<string,string> cache em memória por requisição */
    private array $cache = [];

    /** Quando true, autentica via Managed Identity em vez de client secret. */
    private readonly bool $useManagedIdentity;

    public function __construct(
        private readonly string $vaultUri,
        private readonly string $tenantId = '',
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $apiVersion = '7.4',
    ) {
        if ($this->vaultUri === '') {
            throw new RuntimeException('KeyVaultService requer vaultUri.');
        }

        $this->useManagedIdentity = $this->clientSecret === '';

        // No modo client credentials todos os campos são obrigatórios.
        if (!$this->useManagedIdentity
            && ($this->tenantId === '' || $this->clientId === '')) {
            throw new RuntimeException('KeyVaultService (client credentials) requer tenantId e clientId.');
        }

        $this->http = new Client(['timeout' => 10]);
    }

    /**
     * Retorna o valor de um segredo. Lança RuntimeException se não encontrado.
     */
    public function getSecret(string $name): string
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $url = rtrim($this->vaultUri, '/') . '/secrets/' . rawurlencode($name) . '?api-version=' . $this->apiVersion;

        try {
            $res  = $this->http->get($url, ['headers' => ['Authorization' => 'Bearer ' . $this->getToken()]]);
            $data = json_decode((string)$res->getBody(), true);
        } catch (Throwable $e) {
            throw new RuntimeException("Falha ao ler segredo '{$name}' do Key Vault: " . $e->getMessage(), 0, $e);
        }

        $value = is_array($data) ? ($data['value'] ?? null) : null;
        if (!is_string($value)) {
            throw new RuntimeException("Segredo '{$name}' não encontrado no Key Vault.");
        }

        return $this->cache[$name] = $value;
    }

    private function getToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        return $this->token = $this->useManagedIdentity
            ? $this->getManagedIdentityToken()
            : $this->getClientCredentialsToken();
    }

    /**
     * Token via OAuth2 client credentials (desenvolvimento local).
     */
    private function getClientCredentialsToken(): string
    {
        $url = 'https://login.microsoftonline.com/' . $this->tenantId . '/oauth2/v2.0/token';

        $res  = $this->http->post($url, [
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'https://vault.azure.net/.default',
            ],
        ]);
        $data = json_decode((string)$res->getBody(), true);

        $token = is_array($data) ? ($data['access_token'] ?? null) : null;
        if (!is_string($token)) {
            throw new RuntimeException('Falha ao obter token de acesso para o Key Vault.');
        }

        return $token;
    }

    /**
     * Token via Managed Identity. Prefere o endpoint do Azure App Service
     * (IDENTITY_ENDPOINT + IDENTITY_HEADER) e recorre ao IMDS como fallback.
     * Se $clientId estiver definido, seleciona uma identidade user-assigned.
     */
    private function getManagedIdentityToken(): string
    {
        $resource = 'https://vault.azure.net';

        $identityEndpoint = $_ENV['IDENTITY_ENDPOINT'] ?? getenv('IDENTITY_ENDPOINT') ?: '';
        $identityHeader   = $_ENV['IDENTITY_HEADER']   ?? getenv('IDENTITY_HEADER')   ?: '';

        if ($identityEndpoint !== '' && $identityHeader !== '') {
            // App Service / Functions managed identity.
            $query = ['resource' => $resource, 'api-version' => '2019-08-01'];
            if ($this->clientId !== '') {
                $query['client_id'] = $this->clientId;
            }
            $res = $this->http->get($identityEndpoint, [
                'headers' => ['X-IDENTITY-HEADER' => $identityHeader],
                'query'   => $query,
            ]);
        } else {
            // Fallback: IMDS (VMs / outros ambientes Azure).
            $query = ['resource' => $resource, 'api-version' => '2018-02-01'];
            if ($this->clientId !== '') {
                $query['client_id'] = $this->clientId;
            }
            $res = $this->http->get('http://169.254.169.254/metadata/identity/oauth2/token', [
                'headers' => ['Metadata' => 'true'],
                'query'   => $query,
            ]);
        }

        $data  = json_decode((string)$res->getBody(), true);
        $token = is_array($data) ? ($data['access_token'] ?? null) : null;
        if (!is_string($token)) {
            throw new RuntimeException('Falha ao obter token de Managed Identity para o Key Vault.');
        }

        return $token;
    }
}
