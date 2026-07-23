# SecOps Calculator — Calculadora de Descontos e Propostas Google SecOps

Aplicação web B2B para o time comercial gerar propostas de **Google SecOps** com
cálculo automático de descontos, versionamento de SKUs e histórico auditável por
usuário autenticado via **Microsoft Entra ID (SSO)**.

---

## ✨ Recursos

- **SSO Microsoft (OAuth 2.0 / OpenID Connect)** com `thenetworg/oauth2-azure`, validação de `state` e allowlist de domínios.
- Cálculo **em tempo real** dos 4 cenários de desconto SecOps:
  | Cenário | TOTAL | TD | REVENDA |
  |---------|:----:|:--:|:-------:|
  | Standard + DR      | **27%** | 10% | 17% |
  | Standard sem DR    | **17%** |  7% | 10% |
  | Non-Standard + DR  | **22%** |  7% | 15% |
  | Non-Standard sem DR| **12%** |  7% |  5% |
- Conversão automática **1 TB = 1024 GB** (10 TB = 10.240 GB).
- Composição de propostas com múltiplos itens (SecOps Standard / Enterprise / Enterprise Plus).
- Custo mensal em BRL usando o **Dólar Google** informado.
- **Geração de PDF** da proposta (via `dompdf`, com download remoto desabilitado).
- Tela **Todas as Propostas** (admin) com:
  - **Busca global** (revenda, cliente ou `#número`) + filtros por período.
  - **Ordenação por coluna** (whitelist server-side) e **paginação** (`OFFSET/FETCH`).
  - **Dashboard** com KPIs, mix de precificação e rankings de usuários/SKUs.
- **Dashboard executivo** (`/dashboard`) com série histórica, distribuições e rankings.
- **Administração de usuários** — promover/rebaixar admin com trilha de auditoria.
- CRUD editável de **SKUs e Preços**.
- **Modo escuro** persistente (`localStorage` + `prefers-color-scheme`, sem flash).
- UI **Enterprise SaaS** responsiva (tabela vira cartões no mobile).

---

## 🧱 Stack

| Camada     | Tecnologia                                                     |
|------------|----------------------------------------------------------------|
| Backend    | PHP 8.2+ (`declare(strict_types=1)`, enums, readonly)          |
| Banco      | **Azure SQL Database / SQL Server** (PDO `sqlsrv`) — MySQL opcional em dev |
| Frontend   | HTML5 + CSS3 custom (variáveis de tema) + JS vanilla           |
| Auth       | Microsoft Entra ID via `thenetworg/oauth2-azure`               |
| Segredos   | **Azure Key Vault** (opcional) via `KeyVaultService`           |
| PDF        | `dompdf/dompdf`                                                 |
| Deps       | Composer                                                        |

---

## 📁 Estrutura

```
secops-calculator/
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/{css/style.css, js/app.js, js/theme-init.js, img/}
├── src/
│   ├── Controllers/  (Auth, Proposal, Sku, Dashboard, User, Base)
│   ├── Models/       (Proposal, ProposalItem, Sku, User, AuditLog)
│   ├── Services/     (DiscountCalculator, PricingService, PricingType,
│   │                  AzureAuthService, KeyVaultService, PdfService)
│   ├── Core/         (Router, Database, Session)
│   └── Config/config.php
├── views/
│   ├── layouts/{header,footer}.php
│   ├── auth/login.php
│   ├── proposals/{create,list,show,pdf}.php
│   └── admin/{skus,users}.php
├── database/{schema.sql, schema.sqlserver.sql, seeds.sql, seeds.sqlserver.sql}
├── composer.json
├── .env.example
└── README.md
```

---

## 🚀 Instalação

### 1. Pré-requisitos

- PHP **8.2+** com extensões `pdo_mysql`, `openssl`, `mbstring`, `curl`, `json`.
- Composer 2.x
- MySQL 8

### 2. Clonar e instalar dependências

```bash
git clone <repo> secops-calculator
cd secops-calculator
composer install
```

### 3. Criar banco de dados

**Azure SQL / SQL Server** (produção — requer extensão `pdo_sqlsrv` + ODBC Driver 18):

```bash
# Execute conectado ao banco de destino (ex.: via sqlcmd ou Azure Data Studio)
sqlcmd -S <servidor>.database.windows.net -d <db> -U <user> -i database/schema.sqlserver.sql
sqlcmd -S <servidor>.database.windows.net -d <db> -U <user> -i database/seeds.sqlserver.sql
```

**MySQL** (desenvolvimento local — opcional):

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seeds.sql
```

### 4. Configurar variáveis de ambiente

Copie `.env.example` para `.env` e preencha (o `.env` está no `.gitignore`):

```env
AZURE_TENANT_ID=<seu-tenant-id>
AZURE_CLIENT_ID=<app-registration-client-id>
AZURE_CLIENT_SECRET=<client-secret>       # de preferência via Key Vault
AZURE_REDIRECT_URI=http://localhost:8000/auth/callback
AZURE_ALLOWED_DOMAINS=suaempresa.com      # allowlist de domínios (opcional)

# Azure SQL Database (SQL Server)
DB_DRIVER=sqlsrv
DB_HOST=<servidor>.database.windows.net
DB_PORT=1433
DB_NAME=<db>
DB_USER=<user>
DB_PASS=                                  # de preferência via Key Vault
DB_ENCRYPT=true
DB_TRUST_CERT=false

# Azure Key Vault (opcional): se preenchido, DB_PASS e AZURE_CLIENT_SECRET
# são lidos do Vault em tempo de execução (o App precisa da role "Key Vault Secrets User").
KEYVAULT_URI=
KV_SECRET_DB_PASS=db-pass
KV_SECRET_AZURE_CLIENT_SECRET=azure-client-secret

APP_ENV=development
APP_URL=http://localhost:8000
APP_TIMEZONE=America/Sao_Paulo
APP_AUTH_BYPASS=false                     # NUNCA true em produção
APP_ADMIN_EMAILS=admin@suaempresa.com     # bootstrap de administradores
```

#### Configurando o App Registration no Azure

1. Acesse **Entra ID → App registrations → New registration**.
2. Redirect URI (Web): `http://localhost:8000/auth/callback`.
3. Em **Certificates & secrets**, crie um **Client secret** e copie o valor para `AZURE_CLIENT_SECRET`.
4. Em **API permissions**, adicione (delegated): `openid`, `profile`, `email`, `User.Read`.
5. Copie **Application (client) ID** e **Directory (tenant) ID** para o `.env`.

### 5. Rodar em desenvolvimento

Com o servidor embutido do PHP (aponta para `public/`):

```bash
php -S localhost:8000 -t public
```

Acesse **http://localhost:8000** e faça login com sua conta Microsoft.

> Em produção, aponte o DocumentRoot do Apache/Nginx para `public/`.
> O arquivo `public/.htaccess` já contém as regras de rewrite para Apache.

---

## 🧪 Fluxo de uso

1. **Login** com Microsoft (SSO).
2. **Nova Proposta** → preencha revenda, cliente, tipo de precificação, DR, anos e dólar.
3. Escolha o SKU (Standard / Enterprise / Enterprise Plus), informe TB/ano e clique **＋ Adicionar Item**.
4. Todos os totais e o painel de desconto (TOTAL / TD / REVENDA) atualizam **em tempo real**.
5. **Salvar proposta** persiste o registro com o autor SSO; **Exportar PDF** gera o documento.
6. **Todas as Propostas** (admin) traz **dashboard**, **busca global**, **ordenação por coluna**,
   **paginação** e a coluna *Gerado por*.
7. **SKUs & Preços** permite editar preços USD/TB/ano ou cadastrar novos SKUs.
8. **Usuários** (admin) gerencia perfis de acesso.
9. Alterne **modo claro/escuro** pelo botão no rodapé da barra lateral — a preferência é persistida.

---

## 🔒 Segurança

- **Autenticação**: SSO Microsoft com validação de `state` (anti-CSRF no OAuth),
  allowlist de domínios e **regeneração de ID de sessão** pós-login (anti-fixation, CWE-384).
- **Autorização**: `requireAuth`/`requireAdmin` em todas as rotas sensíveis; privilégios
  são a fonte de verdade no banco, com **trilha de auditoria** de mudanças de perfil.
- **CSRF**: token verificado com `hash_equals` em **todas** as mutações
  (proposta, SKUs, usuários, logout) via `_csrf`/`X-CSRF-Token`.
- **Sessão**: cookie `HttpOnly` + `SameSite=Lax` + `Secure` sob HTTPS
  (detecta `X-Forwarded-Proto` atrás de proxy/Azure App Service).
- **Injeção SQL**: 100% **PDO prepared statements**; `ORDER BY` da listagem usa
  **whitelist** de colunas (sem interpolação de entrada).
- **XSS**: saída escapada com `htmlspecialchars` nas views + **Content-Security-Policy**
  restritiva (`script-src 'self'`, sem inline).
- **Headers**: CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` e `HSTS` (sob HTTPS), definidos em PHP e no `.htaccess`.
- **Segredos**: `.env` no `.gitignore`; suporte a **Azure Key Vault** para `DB_PASS`
  e `AZURE_CLIENT_SECRET`.
- **Bypass de dev**: bloqueado por padrão e **desabilitado à força** quando `APP_ENV=production`.
- **PDF**: `dompdf` com `isRemoteEnabled=false` (mitiga SSRF / leitura de arquivos remotos).

---

## ✅ Critérios de aceite implementados

- [x] Login SSO Microsoft funcional, registrando o autor de cada proposta.
- [x] Descontos corretos nos 4 cenários (validados via `match` em `DiscountCalculator`).
- [x] Conversão TB → GB precisa (10 TB = 10.240 GB).
- [x] Adicionar/remover itens dinamicamente com recálculo em tempo real.
- [x] Listagem de propostas com dashboard, busca global, ordenação e paginação.
- [x] Exportação de proposta em PDF.
- [x] CRUD de SKUs editável e persistido.
- [x] Administração de usuários com trilha de auditoria.
- [x] Modo escuro persistente (claro/escuro), sem flash de tema.
- [x] Interface responsiva (tabela vira cartões no mobile).
- [x] README com instruções completas.

