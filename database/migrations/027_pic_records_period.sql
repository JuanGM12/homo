SET @pic_period_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pic_records'
      AND COLUMN_NAME = 'period_id'
);
SET @pic_period_col_sql := IF(
    @pic_period_col_exists = 0,
    'ALTER TABLE pic_records ADD COLUMN period_id BIGINT UNSIGNED NULL AFTER user_id',
    'SELECT 1'
);
PREPARE pic_period_col_stmt FROM @pic_period_col_sql;
EXECUTE pic_period_col_stmt;
DEALLOCATE PREPARE pic_period_col_stmt;

SET @pic_period_idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pic_records'
      AND INDEX_NAME = 'idx_pic_records_period'
);
SET @pic_period_idx_sql := IF(
    @pic_period_idx_exists = 0,
    'ALTER TABLE pic_records ADD KEY idx_pic_records_period (period_id)',
    'SELECT 1'
);
PREPARE pic_period_idx_stmt FROM @pic_period_idx_sql;
EXECUTE pic_period_idx_stmt;
DEALLOCATE PREPARE pic_period_idx_stmt;

SET @pic_period_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pic_records'
      AND CONSTRAINT_NAME = 'fk_pic_records_period'
);
SET @pic_period_fk_sql := IF(
    @pic_period_fk_exists = 0,
    'ALTER TABLE pic_records ADD CONSTRAINT fk_pic_records_period FOREIGN KEY (period_id) REFERENCES aoat_periods (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE pic_period_fk_stmt FROM @pic_period_fk_sql;
EXECUTE pic_period_fk_stmt;
DEALLOCATE PREPARE pic_period_fk_stmt;

UPDATE pic_records
SET period_id = (SELECT id FROM aoat_periods WHERE name = '2026-1' LIMIT 1)
WHERE period_id IS NULL;
