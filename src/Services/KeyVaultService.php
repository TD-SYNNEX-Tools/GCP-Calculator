<?php
declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

/**
 * Busca segredos do Azure Key Vault usando OAuth2 client credentials.
 *
 * Uso opcional: ativado quando a variável KEYVAULT_URI está definida.
 * Requer que o App Registration (client_id) tenha permissão de leitura
 * de segredos no Key Vault (RBAC "Key Vault Secrets User" ou access policy).
 */
final class KeyVaultService
{
    private Client $http;
    private ?string $token = null;

    /** @var array<string,string> cache em memória por requisição */
    private array $cache = [];

    public function __construct(
        private readonly string $vaultUri,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiVersion = '7.4',
    ) {
        if ($this->vaultUri === '' || $this->tenantId === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('KeyVaultService requer vaultUri, tenantId, clientId e clientSecret.');
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

        return $this->token = $token;
    }
}
