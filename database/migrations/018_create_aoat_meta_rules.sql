CREATE TABLE IF NOT EXISTS aoat_meta_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(50) NOT NULL,
    scope ENUM('per_territory', 'global_monthly') NOT NULL DEFAULT 'per_territory',
    target_value INT NOT NULL,
    month_from TINYINT UNSIGNED NOT NULL,
    month_to TINYINT UNSIGNED NOT NULL,
    rule_year SMALLINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_aoat_meta_rules_month_range CHECK (month_from >= 1 AND month_from <= 12 AND month_to >= month_from AND month_to <= 12),
    CONSTRAINT chk_aoat_meta_rules_target CHECK (target_value >= 0),
    KEY idx_aoat_meta_rules_role_year (role_key, rule_year, active),
    KEY idx_aoat_meta_rules_scope (scope, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO aoat_meta_rules (role_key, scope, target_value, month_from, month_to, rule_year, active, notes)
SELECT * FROM (
    SELECT 'psicologo' AS role_key, 'per_territory' AS scope, 2 AS target_value, 1 AS month_from, 3 AS month_to, NULL AS rule_year, 1 AS active, 'Meta territorial enero-marzo' AS notes
    UNION ALL
    SELECT 'psicologo', 'per_territory', 3, 4, 12, NULL, 1, 'Meta territorial desde abril'
    UNION ALL
    SELECT 'abogado', 'per_territory', 2, 1, 3, NULL, 1, 'Meta territorial enero-marzo'
    UNION ALL
    SELECT 'abogado', 'per_territory', 3, 4, 12, NULL, 1, 'Meta territorial desde abril'
    UNION ALL
    SELECT 'medico', 'global_monthly', 8, 1, 12, NULL, 1, 'Meta global mensual entre todos los médicos'
) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM aoat_meta_rules r
    WHERE r.role_key = seed.role_key
      AND r.scope = seed.scope
      AND r.target_value = seed.target_value
      AND r.month_from = seed.month_from
      AND r.month_to = seed.month_to
      AND ((r.rule_year IS NULL AND seed.rule_year IS NULL) OR r.rule_year = seed.rule_year)
);
