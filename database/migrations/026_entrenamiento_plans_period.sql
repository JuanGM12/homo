SET @ent_period_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrenamiento_plans'
      AND COLUMN_NAME = 'period_id'
);
SET @ent_period_col_sql := IF(
    @ent_period_col_exists = 0,
    'ALTER TABLE entrenamiento_plans ADD COLUMN period_id BIGINT UNSIGNED NULL AFTER user_id',
    'SELECT 1'
);
PREPARE ent_period_col_stmt FROM @ent_period_col_sql;
EXECUTE ent_period_col_stmt;
DEALLOCATE PREPARE ent_period_col_stmt;

SET @ent_period_idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrenamiento_plans'
      AND INDEX_NAME = 'idx_entrenamiento_plans_period'
);
SET @ent_period_idx_sql := IF(
    @ent_period_idx_exists = 0,
    'ALTER TABLE entrenamiento_plans ADD KEY idx_entrenamiento_plans_period (period_id)',
    'SELECT 1'
);
PREPARE ent_period_idx_stmt FROM @ent_period_idx_sql;
EXECUTE ent_period_idx_stmt;
DEALLOCATE PREPARE ent_period_idx_stmt;

SET @ent_period_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entrenamiento_plans'
      AND CONSTRAINT_NAME = 'fk_entrenamiento_plans_period'
);
SET @ent_period_fk_sql := IF(
    @ent_period_fk_exists = 0,
    'ALTER TABLE entrenamiento_plans ADD CONSTRAINT fk_entrenamiento_plans_period FOREIGN KEY (period_id) REFERENCES aoat_periods (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE ent_period_fk_stmt FROM @ent_period_fk_sql;
EXECUTE ent_period_fk_stmt;
DEALLOCATE PREPARE ent_period_fk_stmt;

UPDATE entrenamiento_plans
SET period_id = (SELECT id FROM aoat_periods WHERE name = '2026-1' LIMIT 1)
WHERE period_id IS NULL;
