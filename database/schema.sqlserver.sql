-- =====================================================
-- Google SecOps Calculator — Schema (Azure SQL / SQL Server)
-- Banco: sql-db-secopscalculator (Azure SQL Database)
-- Execute conectado ao banco de dados de destino.
-- =====================================================

-- ---------- Users (SSO Microsoft) ----------
IF OBJECT_ID(N'dbo.users', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.users (
        id         BIGINT IDENTITY(1,1) PRIMARY KEY,
        azure_oid  NVARCHAR(64)  NOT NULL UNIQUE,
        email      NVARCHAR(255) NOT NULL UNIQUE,
        name       NVARCHAR(255) NOT NULL,
        role       NVARCHAR(10)  NOT NULL DEFAULT 'user'
                   CONSTRAINT ck_users_role CHECK (role IN ('user','admin')),
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;
GO

-- ---------- Auditoria de privilégios (quem promoveu/removeu admin) ----------
IF OBJECT_ID(N'dbo.admin_role_audit', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.admin_role_audit (
        id             BIGINT IDENTITY(1,1) PRIMARY KEY,
        actor_user_id  BIGINT NULL,
        actor_email    NVARCHAR(255) NOT NULL,
        target_user_id BIGINT NOT NULL,
        target_email   NVARCHAR(255) NOT NULL,
        action         NVARCHAR(10) NOT NULL
                       CONSTRAINT ck_role_audit_action CHECK (action IN ('grant','revoke')),
        ip_address     NVARCHAR(64) NULL,
        created_at     DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
    CREATE INDEX idx_role_audit_created ON dbo.admin_role_audit (created_at DESC);
    CREATE INDEX idx_role_audit_target  ON dbo.admin_role_audit (target_user_id);
END;
GO

-- ---------- SKUs / Preços ----------
IF OBJECT_ID(N'dbo.skus', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.skus (
        id                BIGINT IDENTITY(1,1) PRIMARY KEY,
        sku_code          NVARCHAR(100) NOT NULL UNIQUE,
        name              NVARCHAR(150) NOT NULL,
        plan_description  NVARCHAR(150) NOT NULL,
        price_usd_tb_year DECIMAL(12,2) NOT NULL,
        active            BIT NOT NULL DEFAULT 1,
        created_at        DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at        DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;
GO

-- ---------- Propostas ----------
IF OBJECT_ID(N'dbo.proposals', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.proposals (
        id                      BIGINT IDENTITY(1,1) PRIMARY KEY,
        user_id                 BIGINT NOT NULL,
        reseller_name           NVARCHAR(255) NOT NULL,
        end_customer_name       NVARCHAR(255) NOT NULL,
        pricing_type            NVARCHAR(20) NOT NULL
                                CONSTRAINT ck_proposals_pricing CHECK (pricing_type IN ('STANDARD','NON_STANDARD')),
        deal_registration       BIT NOT NULL DEFAULT 0,
        contract_years          TINYINT NOT NULL,
        dollar_rate             DECIMAL(10,4) NOT NULL,
        discount_total_pct      DECIMAL(5,2) NOT NULL,
        discount_td_pct         DECIMAL(5,2) NOT NULL,
        discount_reseller_pct   DECIMAL(5,2) NOT NULL,
        discount_additional_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
        total_usd               DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_monthly_brl       DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at              DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at              DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT fk_proposal_user FOREIGN KEY (user_id) REFERENCES dbo.users(id)
    );
    CREATE INDEX idx_reseller ON dbo.proposals (reseller_name);
    CREATE INDEX idx_customer ON dbo.proposals (end_customer_name);
    CREATE INDEX idx_created  ON dbo.proposals (created_at);
END;
GO

-- ---------- Itens da Proposta ----------
IF OBJECT_ID(N'dbo.proposal_items', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.proposal_items (
        id              BIGINT IDENTITY(1,1) PRIMARY KEY,
        proposal_id     BIGINT NOT NULL,
        sku_id          BIGINT NOT NULL,
        solution        NVARCHAR(50) NOT NULL DEFAULT 'SecOps',
        version         NVARCHAR(100) NOT NULL,
        tb_per_year     DECIMAL(12,4) NOT NULL,
        gb_per_year     DECIMAL(14,2) NOT NULL,
        unit_price_usd  DECIMAL(12,2) NOT NULL,
        gross_total_usd DECIMAL(14,2) NOT NULL,
        net_total_usd   DECIMAL(14,2) NOT NULL,
        monthly_brl     DECIMAL(14,2) NOT NULL,
        created_at      DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT fk_item_proposal FOREIGN KEY (proposal_id) REFERENCES dbo.proposals(id) ON DELETE CASCADE,
        CONSTRAINT fk_item_sku      FOREIGN KEY (sku_id)      REFERENCES dbo.skus(id)
    );
END;
GO

-- ---------- Trigger: updated_at automático ----------
IF OBJECT_ID(N'dbo.trg_users_updated', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_users_updated;
GO
CREATE TRIGGER dbo.trg_users_updated ON dbo.users AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE u SET updated_at = SYSUTCDATETIME()
    FROM dbo.users u JOIN inserted i ON i.id = u.id;
END;
GO

IF OBJECT_ID(N'dbo.trg_skus_updated', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_skus_updated;
GO
CREATE TRIGGER dbo.trg_skus_updated ON dbo.skus AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE s SET updated_at = SYSUTCDATETIME()
    FROM dbo.skus s JOIN inserted i ON i.id = s.id;
END;
GO

IF OBJECT_ID(N'dbo.trg_proposals_updated', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_proposals_updated;
GO
CREATE TRIGGER dbo.trg_proposals_updated ON dbo.proposals AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE p SET updated_at = SYSUTCDATETIME()
    FROM dbo.proposals p JOIN inserted i ON i.id = p.id;
END;
GO
