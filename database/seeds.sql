USE secops_calculator;

INSERT INTO skus (sku_code, name, plan_description, price_usd_tb_year, active) VALUES
    ('SECOPS-STD',       'SecOps Standard',        'Annual Plan (Monthly Payment)', 1950.00, 1),
    ('SECOPS-ENT',       'SecOps Enterprise',      'Annual Plan (Monthly Payment)', 2400.00, 1),
    ('SECOPS-ENT-PLUS',  'SecOps Enterprise Plus', 'Annual Plan (Monthly Payment)', 4600.00, 1)
ON DUPLICATE KEY UPDATE
    name              = VALUES(name),
    plan_description  = VALUES(plan_description),
    price_usd_tb_year = VALUES(price_usd_tb_year),
    active            = VALUES(active);
