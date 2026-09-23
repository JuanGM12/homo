SET @period_contract_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'aoat_periods'
      AND COLUMN_NAME = 'contract_number'
);
SET @period_contract_sql := IF(
    @period_contract_exists = 0,
    'ALTER TABLE aoat_periods ADD COLUMN contract_number VARCHAR(40) NULL AFTER name',
    'SELECT 1'
);
PREPARE period_contract_stmt FROM @period_contract_sql;
EXECUTE period_contract_stmt;
DEALLOCATE PREPARE period_contract_stmt;

UPDATE aoat_periods SET contract_number = '4600018640' WHERE name = '2026-1';
UPDATE aoat_periods SET contract_number = '4600019278' WHERE name = '2026-2';

SET @asi_period_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'asistencia_actividades'
      AND COLUMN_NAME = 'period_id'
);
SET @asi_period_col_sql := IF(
    @asi_period_col_exists = 0,
    'ALTER TABLE asistencia_actividades ADD COLUMN period_id BIGINT UNSIGNED NULL AFTER advisor_user_id',
    'SELECT 1'
);
PREPARE asi_period_col_stmt FROM @asi_period_col_sql;
EXECUTE asi_period_col_stmt;
DEALLOCATE PREPARE asi_period_col_stmt;

SET @asi_period_idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'asistencia_actividades'
      AND INDEX_NAME = 'idx_asistencia_actividades_period'
);
SET @asi_period_idx_sql := IF(
    @asi_period_idx_exists = 0,
    'ALTER TABLE asistencia_actividades ADD KEY idx_asistencia_actividades_period (period_id)',
    'SELECT 1'
);
PREPARE asi_period_idx_stmt FROM @asi_period_idx_sql;
EXECUTE asi_period_idx_stmt;
DEALLOCATE PREPARE asi_period_idx_stmt;

SET @asi_period_fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'asistencia_actividades'
      AND CONSTRAINT_NAME = 'fk_asistencia_actividades_period'
);
SET @asi_period_fk_sql := IF(
    @asi_period_fk_exists = 0,
    'ALTER TABLE asistencia_actividades ADD CONSTRAINT fk_asistencia_actividades_period FOREIGN KEY (period_id) REFERENCES aoat_periods (id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE asi_period_fk_stmt FROM @asi_period_fk_sql;
EXECUTE asi_period_fk_stmt;
DEALLOCATE PREPARE asi_period_fk_stmt;

-- Solo rellena filas SIN periodo. Nunca pisa un period_id ya asignado (incluido 2026-2).
-- El histórico de asistencia es anterior a 2026-09-18; lo nuevo nulo, si existiera, va a 2026-2.
UPDATE asistencia_actividades
SET period_id = (SELECT id FROM aoat_periods WHERE name = '2026-1' LIMIT 1)
WHERE period_id IS NULL
  AND (created_at IS NULL OR created_at < '2026-09-18 00:00:00');

UPDATE asistencia_actividades
SET period_id = (SELECT id FROM aoat_periods WHERE name = '2026-2' LIMIT 1)
WHERE period_id IS NULL
  AND created_at >= '2026-09-18 00:00:00';
