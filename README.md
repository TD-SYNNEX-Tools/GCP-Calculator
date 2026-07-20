# SecOps Calculator — Calculadora de Descontos e Propostas Google SecOps

Aplicação web B2B para o time comercial gerar propostas de **Google SecOps** com
cálculo automático de descontos, versionamento de SKUs e histórico auditável por
usuário autenticado via **Microsoft Entra ID (SSO)**.

---

## ✨ Recursos

- **SSO Microsoft (OAuth 2.0 / OpenID Connect)** com `thenetworg/oauth2-azure`.
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
- Tela **Todas as Propostas** com filtros (revenda, cliente, data) e coluna **"Gerado por"**.
- CRUD editável de **SKUs e Preços** (persistência em MySQL).
- UI em **Enterprise SaaS moderno** (Inter/Roboto, Google Blue, Material 3 vibes).

---

## 🧱 Stack

| Camada     | Tecnologia                                          |
|------------|-----------------------------------------------------|
| Backend    | PHP 8.2 (`declare(strict_types=1)`, enums, readonly)|
| Banco      | MySQL 8 (PDO)                                       |
| Frontend   | HTML5 + CSS3 custom + JS vanilla                    |
| Auth       | Microsoft Entra ID via `thenetworg/oauth2-azure`    |
| Deps       | Composer                                            |

---

## 📁 Estrutura

```
secops-calculator/
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/{css/style.css, js/app.js}
├── src/
│   ├── Controllers/  (Auth, Proposal, Sku, Dashboard, Base)
│   ├── Models/       (Proposal, ProposalItem, Sku, User)
│   ├── Services/     (DiscountCalculator, PricingService, AzureAuthService, PricingType)
│   ├── Core/         (Router, Database, Session)
│   └── Config/config.php
├── views/
│   ├── layouts/{header,footer}.php
│   ├── auth/login.php
│   ├── proposals/{create,list,show}.php
│   └── admin/skus.php
├── database/{schema.sql, seeds.sql}
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

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seeds.sql
```

### 4. Configurar variáveis de ambiente

Copie `.env.example` para `.env` e preencha:

```env
AZURE_TENANT_ID=<seu-tenant-id>
AZURE_CLIENT_ID=<app-registration-client-id>
AZURE_CLIENT_SECRET=<client-secret>
AZURE_REDIRECT_URI=http://localhost:8000/auth/callback

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=secops_calculator
DB_USER=root
DB_PASS=

APP_URL=http://localhost:8000
APP_TIMEZONE=America/Sao_Paulo
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
5. **Salvar proposta** persiste o registro com o autor SSO.
6. **Todas as Propostas** lista tudo com filtros e coluna *Gerado por*.
7. **SKUs & Preços** permite editar preços USD/TB/ano ou cadastrar novos SKUs.

---

## 🔒 Segurança

- Sessão com cookie `HttpOnly` + `SameSite=Lax`.
- CSRF token verificado em rotas de mutação de SKUs e no POST de proposta (header `X-CSRF-Token`).
- Todas as queries usam **PDO com prepared statements**.
- Escapes com `htmlspecialchars` em todas as views.
- Headers `X-Content-Type-Options`, `X-Frame-Options` e `Referrer-Policy` no `.htaccess`.

---

## ✅ Critérios de aceite implementados

- [x] Login SSO Microsoft funcional, registrando o autor de cada proposta.
- [x] Descontos corretos nos 4 cenários (validados via `match` em `DiscountCalculator`).
- [x] Conversão TB → GB precisa (10 TB = 10.240 GB).
- [x] Adicionar/remover itens dinamicamente com recálculo em tempo real.
- [x] Listagem de propostas com coluna "Gerado por".
- [x] CRUD de SKUs editável e persistido.
- [x] Interface responsiva (desktop-first, funcional em tablet).
- [x] README com instruções completas.

