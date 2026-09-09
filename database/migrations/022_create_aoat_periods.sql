CREATE TABLE IF NOT EXISTS aoat_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aoat_periods_name (name),
    KEY idx_aoat_periods_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO aoat_periods (name, active)
VALUES ('2026-1', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO aoat_periods (name, active)
VALUES ('2026-2', 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @aoat_period_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'aoat_records'
      AND COLUMN_NAME = 'period_id'
);
SET @aoat_period_col_sql := IF(
    @aoat_period_col_exists = 0,
    'ALTER TABLE aoat_records ADD COLUMN period_id BIGINT UNSIGNED NULL AFTER user_id',
    'SELECT 1'
);
PREPARE aoat_period_col_stmt FROM @aoat_period_col_sql;
EXECUTE aoat_period_col_stmt;
DEALLOCATE PREPARE aoat_period_col_stmt;

SET @aoat_period_idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'aoat_records'
      AND INDEX_NAME = 'idx_aoat_records_period'
);
SET @aoat_period_idx_sql := IF(
    @aoat_period_idx_exists = 0,
    'ALTER TABLE aoat_records ADD KEY idx_aoat_records_period (period_id)',
    'SELECT 1'
);
PREPARE aoat_period_idx_stmt FROM @aoat_period_idx_sql;
EXECUTE aoat_period_idx_stmt;
DEALLOCATE PREPARE aoat_period_idx_stmt;

SET @aoat_period_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'aoat_records'
      AND CONSTRAINT_NAME = 'fk_aoat_records_period'
);
SET @aoat_period_fk_sql := IF(
    @aoat_period_fk_exists = 0,
    'ALTER TABLE aoat_records ADD CONSTRAINT fk_aoat_records_period FOREIGN KEY (period_id) REFERENCES aoat_periods (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE aoat_period_fk_stmt FROM @aoat_period_fk_sql;
EXECUTE aoat_period_fk_stmt;
DEALLOCATE PREPARE aoat_period_fk_stmt;

UPDATE aoat_records
SET period_id = (SELECT id FROM aoat_periods WHERE name = '2026-1' LIMIT 1)
WHERE period_id IS NULL;
