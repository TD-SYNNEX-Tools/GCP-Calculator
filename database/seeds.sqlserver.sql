-- Seeds — Azure SQL / SQL Server
-- Upsert idempotente dos SKUs padrão (MERGE = ON DUPLICATE KEY do MySQL).

MERGE dbo.skus AS target
USING (VALUES
    ('SECOPS-STD',      'SecOps Standard',        'Annual Plan (Monthly Payment)', 1950.00, 1),
    ('SECOPS-ENT',      'SecOps Enterprise',      'Annual Plan (Monthly Payment)', 2400.00, 1),
    ('SECOPS-ENT-PLUS', 'SecOps Enterprise Plus', 'Annual Plan (Monthly Payment)', 4600.00, 1)
) AS src (sku_code, name, plan_description, price_usd_tb_year, active)
    ON target.sku_code = src.sku_code
WHEN MATCHED THEN
    UPDATE SET name = src.name,
               plan_description  = src.plan_description,
               price_usd_tb_year = src.price_usd_tb_year,
               active            = src.active
WHEN NOT MATCHED THEN
    INSERT (sku_code, name, plan_description, price_usd_tb_year, active)
    VALUES (src.sku_code, src.name, src.plan_description, src.price_usd_tb_year, src.active);
